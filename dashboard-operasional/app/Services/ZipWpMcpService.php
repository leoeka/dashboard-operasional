<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ZipWpMcpService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.zipwp.mcp_url');
        $this->token = config('services.zipwp.token');
    }

    protected function headers(?string $sessionId = null): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => 'Bearer ' . $this->token,
        ];

        if ($sessionId) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return $headers;
    }

    /**
     * Handshake MCP (initialize + notifications/initialized),
     * session id di-cache supaya tidak handshake ulang tiap request.
     */
    protected function getSessionId(bool $forceNew = false): string
    {
        if ($forceNew) {
            Cache::forget('zipwp_mcp_session');
        }

        return Cache::remember('zipwp_mcp_session', now()->addMinutes(30), function () {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->retry(2, 1000)
                ->post($this->baseUrl, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'initialize',
                    'params' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => new \stdClass(),
                        'clientInfo' => [
                            'name' => 'laravel-app',
                            'version' => '1.0.0',
                        ],
                    ],
                ]);

            $sessionId = $response->header('Mcp-Session-Id');

            if (!$sessionId) {
                Log::error('ZipWP MCP: gagal mendapatkan session id', ['body' => $response->body()]);
                throw new \RuntimeException('Gagal terhubung ke ZipWP.');
            }

            // Konfirmasi handshake selesai (best-effort, boleh gagal diam-diam)
            Http::withHeaders($this->headers($sessionId))
                ->timeout(15)
                ->post($this->baseUrl, [
                    'jsonrpc' => '2.0',
                    'method' => 'notifications/initialized',
                ]);

            return $sessionId;
        });
    }

    protected function callTool(string $toolName, array $arguments = [], bool $isRetry = false): array
    {
        $sessionId = $this->getSessionId();

        $response = Http::withHeaders($this->headers($sessionId))
            ->timeout(120) // dinaikkan dari 30 supaya create-ai-site + polling tidak timeout
            ->retry(2, 1000)
            ->post($this->baseUrl, [
                'jsonrpc' => '2.0',
                'id' => random_int(1000, 999999),
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => $arguments,
                ],
            ]);

        $json = $response->json();

        // Session kadaluarsa / invalid -> handshake ulang sekali
        if (!$isRetry && (($json['error'] ?? null) || $response->status() === 401 || $response->status() === 404)) {
            $this->getSessionId(forceNew: true);
            return $this->callTool($toolName, $arguments, isRetry: true);
        }

        $text = $json['result']['content'][0]['text'] ?? null;
        $isError = $json['result']['isError'] ?? false;

        // Ada text tapi isError = true — ZipWP kasih pesan error
        if ($text && $isError) {
            $errorMessage = trim($text);

            Log::error("ZipWP MCP [{$toolName}] error: {$errorMessage}", [
                'tool' => $toolName,
                'arguments' => $arguments,
            ]);

            // Deteksi jenis error supaya pesan ke user lebih informatif
            $lowerMsg = strtolower($errorMessage);

            if (str_contains($lowerMsg, 'limit reached') || str_contains($lowerMsg, 'plan')) {
                throw new \RuntimeException("Kuota ZipWP habis: {$errorMessage}. Silakan upgrade plan atau hapus site lama di dashboard ZipWP.");
            }

            if (str_contains($lowerMsg, 'unauthorized') || str_contains($lowerMsg, 'invalid token')) {
                throw new \RuntimeException("Token ZipWP tidak valid atau sudah kadaluarsa. Periksa konfigurasi ZIPWP_TOKEN di .env.");
            }

            if (str_contains($lowerMsg, 'not found') || str_contains($lowerMsg, 'uuid')) {
                throw new \RuntimeException("ZipWP: resource tidak ditemukan — {$errorMessage}");
            }

            // Error lain yang belum dikenali
            throw new \RuntimeException("ZipWP [{$toolName}] gagal: {$errorMessage}");
        }

        // Tidak ada text sama sekali (response kosong/format salah)
        if (!$text) {
            Log::warning("ZipWP MCP [{$toolName}]: response kosong atau format tidak dikenal", [
                'tool' => $toolName,
                'body' => $json,
            ]);
            return [];
        }

        // Sukses — decode JSON dari text
        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("ZipWP MCP [{$toolName}]: response bukan JSON valid", [
                'tool' => $toolName,
                'raw' => $text,
            ]);
            return [];
        }

        return $decoded ?? [];
    }

    public function listTemplates(?string $search = null, int $page = 1, int $perPage = 15): array
    {
        $args = ['page' => $page, 'per_page' => $perPage];

        if ($search) {
            $args['search'] = $search;
        }

        return $this->callTool('list-templates', $args);
    }
    public function listAvailableTools(): array
    {
        $sessionId = $this->getSessionId();

        $response = Http::withHeaders($this->headers($sessionId))
            ->timeout(30)
            ->post($this->baseUrl, [
                'jsonrpc' => '2.0',
                'id' => random_int(1000, 999999),
                'method' => 'tools/list',
                'params' => new \stdClass(),
            ]);

        return $response->json('result.tools', []);
    }

    public function createAiSite(array $params): array
    {
        $required = ['business_name', 'business_desc', 'business_category_name', 'template'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' wajib diisi untuk create-ai-site.");
            }
        }

        return $this->callTool('create-ai-site', $params);
    }

    /**
     * Cek progress pembuatan site. Panggil ini berulang (misal tiap 10 detik)
     * setelah createAiSite(), sampai status jadi "active" atau muncul error.
     */
    public function getSiteProgress(string $siteUuid): array
    {
        return $this->callTool('get-site-progress', ['site_uuid' => $siteUuid]);
    }

    /**
     * List semua site di team ZipWP kamu — berguna buat cek ulang status
     * atau ambil URL site yang sudah jadi tanpa perlu simpan UUID manual.
     */
    public function listSites(?string $search = null, ?string $status = null, int $page = 1, int $perPage = 15): array
    {
        $args = ['page' => $page, 'per_page' => $perPage];

        if ($search) {
            $args['search'] = $search;
        }

        if ($status) {
            $args['status'] = $status;
        }

        return $this->callTool('list-sites', $args);
    }
}
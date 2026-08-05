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
            ->timeout(30)
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

        if (!$text) {
            Log::warning('ZipWP MCP: response tidak sesuai format', ['body' => $json]);
            return [];
        }

        return json_decode($text, true) ?? [];
    }

    public function listTemplates(?string $search = null, int $page = 1, int $perPage = 15): array
    {
        $args = ['page' => $page, 'per_page' => $perPage];

        if ($search) {
            $args['search'] = $search;
        }

        return $this->callTool('list-templates', $args);
    }
}
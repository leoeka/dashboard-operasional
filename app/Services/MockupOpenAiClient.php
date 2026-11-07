<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MockupOpenAiClient
{
    private const IMAGE_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * @param  list<array{label: string, path: string, mime: string}>  $images
     * @return array{
     *     response_id: string|null,
     *     output_text: string,
     *     images: list<array{base64: string, revised_prompt: string}>,
     *     usage: array<string, int>
     * }
     */
    public function generate(string $brief, array $images): array
    {
        $key = config('services.openai.key');

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('OPENAI_API_KEY belum diatur pada server.');
        }

        $timeout = max(30, (int) config('services.mockup.openai.timeout', 2100));
        $input = [[
            'role' => 'user',
            'content' => $this->inputContent($brief, $images),
        ]];

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->connectTimeout(20)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.mockup.openai.model', 'gpt-6-astra'),
                    'input' => $input,
                    'tools' => [[
                        'type' => 'image_generation',
                        'model' => config('services.mockup.openai.image_model', 'gpt-image-2.5-sunburst'),
                        'quality' => 'high',
                    ]],
                    'tool_choice' => 'auto',
                    'max_output_tokens' => max(1000, (int) config('services.mockup.openai.max_output_tokens', 50000)),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'mockup_candidate',
                            'strict' => true,
                            'schema' => $this->outputSchema(),
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Koneksi ke OpenAI gagal atau melewati batas waktu.', 0, $e);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message');
            $detail = is_string($message) && $message !== ''
                ? ': ' . mb_substr($message, 0, 300)
                : '';

            throw new RuntimeException('OpenAI API gagal (HTTP ' . $response->status() . ')' . $detail . '.');
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new RuntimeException('OpenAI mengembalikan respons yang bukan JSON valid.');
        }

        if (($body['status'] ?? 'completed') !== 'completed') {
            throw new RuntimeException('Respons OpenAI tidak selesai. Status: ' . (string) ($body['status'] ?? 'tidak diketahui') . '.');
        }

        $outputText = $this->outputText($body);

        if ($outputText === null) {
            throw new RuntimeException('Respons OpenAI tidak berisi keluaran teks kandidat.');
        }

        $generatedImages = [];

        foreach ($body['output'] ?? [] as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'image_generation_call') {
                continue;
            }

            if (($item['status'] ?? 'completed') !== 'completed' || !is_string($item['result'] ?? null)) {
                throw new RuntimeException('OpenAI menghasilkan gambar yang tidak selesai atau tidak memiliki data.');
            }

            $generatedImages[] = [
                'base64' => $item['result'],
                'revised_prompt' => is_string($item['revised_prompt'] ?? null) ? $item['revised_prompt'] : '',
            ];
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        return [
            'response_id' => is_string($body['id'] ?? null) ? $body['id'] : null,
            'output_text' => $outputText,
            'images' => $generatedImages,
            'usage' => [
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * @param  list<array{label: string, path: string, mime: string}>  $images
     * @return list<array<string, string>>
     */
    private function inputContent(string $brief, array $images): array
    {
        $content = [[
            'type' => 'input_text',
            'text' => $brief,
        ]];
        $totalBytes = 0;
        $maxBytes = max(1024, (int) config('services.mockup.openai.max_input_image_bytes', 12000000));

        foreach ($images as $image) {
            $label = $image['label'] ?? '';
            $path = $image['path'] ?? '';
            $mime = $image['mime'] ?? '';

            if (!is_string($label) || preg_match('/^[a-z0-9][a-z0-9-]*$/', $label) !== 1) {
                throw new RuntimeException('Nama aset gambar tidak valid untuk dikirim ke OpenAI.');
            }

            if (!is_string($path) || !is_file($path) || !is_readable($path)) {
                throw new RuntimeException('Berkas gambar input tidak bisa dibaca: ' . $label . '.');
            }

            if (!in_array($mime, self::IMAGE_MIME_TYPES, true)) {
                throw new RuntimeException('Format gambar input tidak didukung untuk aset ' . $label . '. Gunakan PNG, JPG, atau WebP.');
            }

            $bytes = filesize($path);

            if (!is_int($bytes) || $bytes <= 0) {
                throw new RuntimeException('Berkas gambar input kosong atau ukurannya tidak diketahui: ' . $label . '.');
            }

            $totalBytes += $bytes;

            if ($totalBytes > $maxBytes) {
                throw new RuntimeException('Total gambar input melebihi batas ' . number_format($maxBytes / 1000000, 1) . ' MB. Kurangi atau perkecil aset lalu coba lagi.');
            }

            $data = file_get_contents($path);

            if (!is_string($data)) {
                throw new RuntimeException('Gambar input gagal dibaca: ' . $label . '.');
            }

            $content[] = [
                'type' => 'input_text',
                'text' => 'Aset gambar "' . $label . '" (' . $mime . '). Gunakan sesuai instruksi brief dan jangan mengarang isi visual yang tidak terlihat.',
            ];
            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:' . $mime . ';base64,' . base64_encode($data),
                'detail' => 'high',
            ];
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function outputText(array $body): ?string
    {
        if (is_string($body['output_text'] ?? null)) {
            return $body['output_text'];
        }

        foreach ($body['output'] ?? [] as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (!is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI menolak membuat kandidat: ' . mb_substr((string) ($content['refusal'] ?? ''), 0, 300));
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'files' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                        ],
                        'required' => ['path', 'content'],
                        'additionalProperties' => false,
                    ],
                ],
                'generated_images' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['files', 'generated_images'],
            'additionalProperties' => false,
        ];
    }
}

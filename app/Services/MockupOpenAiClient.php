<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien tipis ke Responses API: satu permintaan non-streaming, satu jawaban
 * yang bisa berupa teks dan/atau panggilan alat. Tidak ada ingatan di sini -
 * seluruh jejak percakapan dipegang pemanggil sebagai daftar input, supaya
 * satu klien bisa dipakai untuk banyak kandidat tanpa saling mencampur.
 */
class MockupOpenAiClient
{
    public function send(string $instructions, array $input, array $tools): MockupTurn
    {
        $key = config('services.openai.key');

        if (empty($key)) {
            throw ProviderException::missingKey('openai');
        }

        $response = $this->post($key, $instructions, $input, $tools);

        if ($response->json('status') === 'failed') {
            throw ProviderException::invalidResponse(
                'openai',
                'Responses API status=failed: ' . $response->json('error.message', 'tanpa pesan')
            );
        }

        return $this->turnFrom($response);
    }

    private function post(string $key, string $instructions, array $input, array $tools): Response
    {
        try {
            $response = Http::withToken($key)
                ->timeout(180)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.mockup_model', 'gpt-5-mini'),
                    'instructions' => $instructions,
                    'input' => $input,
                    'tools' => $tools,
                    'store' => false,
                ]);
        } catch (Throwable $e) {
            throw ProviderException::fromThrowable('openai', $e);
        }

        if ($response->failed()) {
            throw ProviderException::fromResponse('openai', $response);
        }

        return $response;
    }

    private function turnFrom(Response $response): MockupTurn
    {
        $text = null;
        $toolCalls = [];

        foreach ($response->json('output', []) as $item) {
            if ($item['type'] === 'function_call') {
                $toolCalls[] = [
                    'name' => $item['name'],
                    'arguments' => $item['arguments'] ?? '{}',
                    'callId' => $item['call_id'],
                ];
            }

            if ($item['type'] === 'message') {
                foreach ($item['content'] as $part) {
                    if ($part['type'] === 'output_text') {
                        $text = ($text ?? '') . $part['text'];
                    }
                }
            }
        }

        return new MockupTurn($text, $toolCalls);
    }
}

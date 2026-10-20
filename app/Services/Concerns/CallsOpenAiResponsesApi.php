<?php

namespace App\Services\Concerns;

use App\Exceptions\ProviderException;
use Illuminate\Support\Facades\Http;

/**
 * One transport for OpenAI Responses API calls whose answer is a large JSON body.
 *
 * Both big answers this app asks for (the website content and the WordPress
 * theme manifest) are streamed for the same reason: a plain request returns
 * zero bytes until the whole answer is done, and the infrastructure in front of
 * the API kills a connection that quiet well before the model finishes. The
 * parser therefore lives here once — a second copy in a second service is a
 * copy that can drift from the one that was actually debugged against the real
 * API.
 */
trait CallsOpenAiResponsesApi
{
    /**
     * POST /v1/responses with stream=true and return the accumulated text.
     *
     * @param  array<string,mixed>  $request  Full request body (model, input, max_output_tokens, ...).
     */
    protected function streamOpenAiText(string $apiKey, array $request, int $timeout): string
    {
        $response = Http::timeout($timeout)
            ->withToken($apiKey)
            ->accept('text/event-stream')
            ->asJson()
            ->post('https://api.openai.com/v1/responses', $request);

        if (! $response->successful()) {
            throw ProviderException::fromResponse('openai', $response);
        }

        // Laravel's HTTP fake and some PSR stream wrappers do not expose
        // incremental reads consistently. The request is still sent with
        // stream=true; consume its SSE body once headers arrive, then parse
        // complete event lines from the response body.
        $text = '';
        $completed = false;

        foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                continue;
            }

            $event = json_decode($payload, true);
            if (! is_array($event)) {
                continue;
            }

            $eventType = $event['type'] ?? '';

            if ($eventType === 'response.output_text.delta' && isset($event['delta'])) {
                $text .= $event['delta'];
            } elseif ($eventType === 'response.completed') {
                $completed = true;
            } elseif ($eventType === 'response.failed' || $eventType === 'error') {
                $message = data_get($event, 'response.error.message')
                    ?? ($event['message'] ?? json_encode($event));
                throw new \RuntimeException('OpenAI stream error: '.$message);
            } elseif ($eventType === 'response.incomplete') {
                $reason = data_get($event, 'response.incomplete_details.reason', 'unknown');
                throw ProviderException::invalidResponse('openai', 'Respons GPT terpotong ('.$reason.').');
            }
        }

        if (! $completed || $text === '') {
            throw ProviderException::invalidResponse('openai', 'Respons GPT tidak selesai atau kosong.');
        }

        return $text;
    }
}

<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * One AI provider going wrong, classified into something the pipeline can act
 * on and a person can read.
 *
 * Every provider reports trouble differently — OpenAI returns 429 both for "too
 * many requests this minute" and for "your credit ran out", Anthropic puts
 * billing problems in a 400, Gemini's SDK throws strings. Those need to mean
 * different things to us: a rate limit is worth retrying in a minute, an
 * exhausted balance is not worth retrying at all until somebody tops it up.
 *
 * Nothing here ever carries a key. `sanitise()` scrubs anything that looks like
 * a credential out of provider messages before they reach a log or the
 * database, because provider errors sometimes echo the request back.
 */
class ProviderException extends \RuntimeException
{
    public const MISSING_API_KEY = 'missing_api_key';
    public const AUTHENTICATION_FAILED = 'authentication_failed';
    public const QUOTA_EXHAUSTED = 'quota_exhausted';
    public const RATE_LIMITED = 'rate_limited';
    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const INVALID_RESPONSE = 'invalid_response';

    public const CODES = [
        self::MISSING_API_KEY,
        self::AUTHENTICATION_FAILED,
        self::QUOTA_EXHAUSTED,
        self::RATE_LIMITED,
        self::PROVIDER_UNAVAILABLE,
        self::INVALID_RESPONSE,
    ];

    public function __construct(
        public readonly string $provider,
        public readonly string $errorCode,
        string $message = '',
        public readonly ?string $detail = null,
    ) {
        parent::__construct($message !== '' ? $message : self::messageFor($provider, $errorCode));
    }

    public static function missingKey(string $provider): self
    {
        return new self($provider, self::MISSING_API_KEY);
    }

    public static function invalidResponse(string $provider, string $detail = ''): self
    {
        return new self($provider, self::INVALID_RESPONSE, '', self::sanitise($detail));
    }

    /**
     * Classifies a failed HTTP response.
     *
     * The status alone is not enough: 429 means a rate limit OR an exhausted
     * balance depending on the body, and several providers report billing
     * problems as a plain 400. So the body is inspected for the words that
     * actually distinguish them.
     */
    public static function fromResponse(string $provider, Response $response): self
    {
        $status = $response->status();
        $body = strtolower((string) $response->body());

        $mentionsBilling = (bool) array_filter(
            ['quota', 'billing', 'credit', 'insufficient_quota', 'exceeded your current quota', 'balance'],
            fn (string $needle) => str_contains($body, $needle)
        );

        $code = match (true) {
            $status === 401, $status === 403 => self::AUTHENTICATION_FAILED,
            $status === 402 => self::QUOTA_EXHAUSTED,
            $status === 429 => $mentionsBilling ? self::QUOTA_EXHAUSTED : self::RATE_LIMITED,
            $status === 400 && $mentionsBilling => self::QUOTA_EXHAUSTED,
            $status >= 500 => self::PROVIDER_UNAVAILABLE,
            default => self::INVALID_RESPONSE,
        };

        return new self($provider, $code, '', self::sanitise('HTTP ' . $status . ': ' . $response->body()));
    }

    /**
     * Classifies a thrown error — a connection failure, or an SDK that reports
     * trouble as an exception rather than a response (Gemini's does).
     */
    public static function fromThrowable(string $provider, \Throwable $e): self
    {
        if ($e instanceof self) {
            return $e;
        }

        $message = strtolower($e->getMessage());
        $mentions = fn (array $needles) => (bool) array_filter($needles, fn ($n) => str_contains($message, $n));

        $code = match (true) {
            $mentions(['quota', 'billing', 'credit', 'balance']) => self::QUOTA_EXHAUSTED,
            $mentions(['rate limit', 'rate-limit', 'too many requests', '429']) => self::RATE_LIMITED,
            $mentions(['api key', 'unauthorized', 'unauthenticated', 'permission denied', 'invalid authentication', '401', '403']) => self::AUTHENTICATION_FAILED,
            $mentions(['timed out', 'timeout', 'curl error', 'connection', 'overloaded', 'unavailable', '503', '500']) => self::PROVIDER_UNAVAILABLE,
            default => self::INVALID_RESPONSE,
        };

        return new self($provider, $code, '', self::sanitise($e->getMessage()));
    }

    /** Whether trying again, unchanged, could plausibly succeed. */
    public function isTransient(): bool
    {
        return in_array($this->errorCode, [self::RATE_LIMITED, self::PROVIDER_UNAVAILABLE], true);
    }

    /** Safe to log and to store: provider, classification, and a scrubbed detail. */
    public function context(): array
    {
        return array_filter([
            'provider' => $this->provider,
            'error_code' => $this->errorCode,
            'detail' => $this->detail,
        ]);
    }

    private static function messageFor(string $provider, string $code): string
    {
        $name = match ($provider) {
            'gemini' => 'Gemini',
            'openai' => 'OpenAI',
            'anthropic' => 'Claude',
            default => ucfirst($provider),
        };

        return match ($code) {
            self::MISSING_API_KEY => "API key {$name} belum diisi di .env. Isi kuncinya lalu jalankan ulang — tahap yang sudah selesai tidak akan diulang.",
            self::AUTHENTICATION_FAILED => "Kredensial {$name} ditolak. Periksa API key-nya lalu jalankan ulang — tahap yang sudah selesai tidak akan diulang.",
            self::QUOTA_EXHAUSTED => "Kuota/saldo {$name} habis. Isi ulang lalu jalankan ulang — proses lanjut dari tahap yang gagal, tidak dari awal.",
            self::RATE_LIMITED => "{$name} sedang membatasi jumlah permintaan. Tunggu sebentar lalu jalankan ulang — proses lanjut dari tahap yang gagal.",
            self::PROVIDER_UNAVAILABLE => "{$name} sedang tidak dapat dihubungi. Coba lagi beberapa saat — proses lanjut dari tahap yang gagal.",
            self::INVALID_RESPONSE => "{$name} mengembalikan respons yang tidak bisa dipakai. Jalankan ulang tahap ini.",
            default => "{$name} gagal diproses.",
        };
    }

    /**
     * Removes anything credential-shaped before a message is stored or logged,
     * and caps the length so a huge provider payload never lands in the
     * database. Provider errors do sometimes quote the request back.
     */
    public static function sanitise(string $detail): string
    {
        $patterns = [
            '/\bsk-[A-Za-z0-9_\-]{8,}/',          // OpenAI
            '/\bAIza[A-Za-z0-9_\-]{8,}/',          // Google
            '/\bsk-ant-[A-Za-z0-9_\-]{8,}/',       // Anthropic
            '/\bAQ\.[A-Za-z0-9_\-]{8,}/',          // Google OAuth-style token
            '/(?i)(bearer|api[_-]?key|x-api-key|authorization)\s*[:=]\s*\S+/',
        ];

        $clean = preg_replace($patterns, '[redacted]', $detail) ?? '';

        return mb_substr(trim($clean), 0, 500);
    }
}

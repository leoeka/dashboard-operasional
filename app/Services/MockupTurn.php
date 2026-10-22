<?php

namespace App\Services;

/**
 * Satu jawaban OpenAI dalam loop alat: teks akhir (bila ada) plus daftar
 * panggilan alat yang harus dijalankan mejanya sebelum ronde berikutnya.
 */
class MockupTurn
{
    /**
     * @param list<array{name: string, arguments: string, callId: string}> $toolCalls
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls,
    ) {}
}

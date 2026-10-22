<?php

namespace App\Services;

use RuntimeException;

/**
 * Satu loop per kandidat: kirim brief + aturan ke OpenAI bersama definisi
 * alat, jalankan setiap panggilan alat di meja kerja kandidat, ulang sampai
 * otak berhenti memanggil alat. Batas ronde ada supaya loop tidak
 * menggantung; angkanya disetel lewat MOCKUP_MAX_TOOL_ROUNDS.
 *
 * act() memajukan satu ronde dan mengembalikan [teks, input] — kandidat
 * berjalan dalam beberapa babak (rencana, kritik, markah) dan setiap babak
 * melanjutkan percakapan yang sama, jadi hitungan ronde sengaja menumpuk
 * di seluruh babak.
 */
class MockupToolLoop
{
    private int $roundsUsed = 0;

    public function __construct(
        private MockupOpenAiClient $client,
        private MockupToolBox $tools,
        private int $maxRounds,
    ) {}

    public static function make(MockupOpenAiClient $client, MockupToolBox $tools): self
    {
        return new self($client, $tools, (int) config('services.mockup.max_tool_rounds', 12));
    }

    public function run(string $instructions, string $task): string
    {
        [$text] = $this->act($instructions, [[
            'type' => 'message',
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => $task]],
        ]]);

        return $text;
    }

    /**
     * @param list<array<string, mixed>> $input
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    public function act(string $instructions, array $input): array
    {
        $tools = $this->tools->definitions();

        while (true) {
            $this->roundsUsed++;

            if ($this->roundsUsed > $this->maxRounds) {
                throw new RuntimeException('Loop alat mentok di batas ronde ' . $this->maxRounds . '.');
            }

            $turn = $this->client->send($instructions, $input, $tools);

            if ($turn->toolCalls === []) {
                if ($turn->text === null || trim($turn->text) === '') {
                    throw new RuntimeException('OpenAI menjawab tanpa teks dan tanpa panggilan alat.');
                }

                return [$turn->text, $input];
            }

            foreach ($turn->toolCalls as $call) {
                $input[] = [
                    'type' => 'function_call',
                    'name' => $call['name'],
                    'arguments' => $call['arguments'],
                    'call_id' => $call['callId'],
                ];
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $call['callId'],
                    'output' => $this->tools->execute($call['name'], $call['arguments']),
                ];
            }
        }
    }
}

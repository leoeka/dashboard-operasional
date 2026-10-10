<?php

namespace App\Services\Concerns;

use App\Exceptions\ProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Used by AI WordPress builders to catch a *syntactically* broken
 * `.php` file before it's shipped in a bundle. This project has repeatedly
 * hit AI-generated output that doesn't
 * match the exact shape/validity a consumer needed; for a WordPress theme
 * specifically, one bad PHP file (e.g. header.php) is fatal for every
 * visitor, and WordPress's own template loader has no error boundary
 * around it.
 *
 * Runs `php -l` via a subprocess. A build stops if the syntax check cannot run.
 */
trait LintsGeneratedPhp
{
    private function isValidPhpSyntax(string $code): bool
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'exito-php-lint-');
        if ($tmpPath === false) {
            throw new \RuntimeException('PHP lint gagal menyiapkan file sementara; build dihentikan.');
        }

        try {
            if (file_put_contents($tmpPath, $code) === false) {
                throw new \RuntimeException('PHP lint gagal menulis file sementara; build dihentikan.');
            }

            $result = Process::run(['php', '-l', $tmpPath]);

            return $result->successful();
        } catch (\Throwable $e) {
            Log::error('PHP lint gagal dijalankan; WordPress build dihentikan.', [
                'error' => ProviderException::sanitise($e->getMessage()),
            ]);

            throw new \RuntimeException('PHP lint tidak dapat dijalankan; build dihentikan.', previous: $e);
        } finally {
            @unlink($tmpPath);
        }
    }
}

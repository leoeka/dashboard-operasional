<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Used by ClaudeWordPressBuilderService to catch a *syntactically* broken
 * `.php` file before it's shipped in a bundle. This project has repeatedly
 * hit AI-generated output that doesn't
 * match the exact shape/validity a consumer needed; for a WordPress theme
 * specifically, one bad PHP file (e.g. header.php) is fatal for every
 * visitor, and WordPress's own template loader has no error boundary
 * around it (unlike the plugin's page-import logic, which is deliberately
 * wrapped in try/catch — see BundleExporterService::pluginBootstrap()).
 *
 * Runs `php -l` via a subprocess; if that's unavailable in this
 * environment (exec/proc_open disabled), it fails open — treats the file
 * as valid rather than blocking the build over a check it couldn't run.
 */
trait LintsGeneratedPhp
{
    private function isValidPhpSyntax(string $code): bool
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'exito-php-lint-');
        if ($tmpPath === false) {
            return true;
        }

        try {
            file_put_contents($tmpPath, $code);
            $result = Process::run(['php', '-l', $tmpPath]);
            return $result->successful();
        } catch (\Throwable $e) {
            Log::info('PHP-lint AI-generated file dilewati (php -l tidak tersedia di environment ini).', [
                'error' => $e->getMessage(),
            ]);
            return true;
        } finally {
            @unlink($tmpPath);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Support\PageSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Symfony\Component\Process\ExecutableFinder;

class CheckCpanelReadiness extends Command
{
    private int $failures = 0;

    private int $warnings = 0;

    protected $signature = 'deployment:check-cpanel';

    protected $description = 'Memeriksa prasyarat deploy dan fitur mockup di cPanel';

    public function handle(PageSnapshot $snapshot): int
    {
        $this->failures = 0;
        $this->warnings = 0;

        $this->check('PHP 8.2+', PHP_VERSION_ID >= 80200, 'Upgrade PHP melalui MultiPHP Manager cPanel.');

        foreach (['curl', 'dom', 'fileinfo', 'mbstring', 'openssl', 'pdo_mysql', 'xml', 'zip'] as $extension) {
            $this->check('Ekstensi PHP ' . $extension, extension_loaded($extension), 'Aktifkan ekstensi ini di Select PHP Version cPanel.');
        }

        $this->check('APP_ENV production', app()->environment('production'), 'Atur APP_ENV=production di .env server.');
        $this->check('APP_DEBUG nonaktif', !config('app.debug'), 'Atur APP_DEBUG=false di .env server.');
        $this->check('APP_KEY terisi', is_string(config('app.key')) && trim((string) config('app.key')) !== '', 'Jalankan php artisan key:generate hanya saat instalasi pertama.');
        $this->check('APP_URL HTTPS', str_starts_with((string) config('app.url'), 'https://'), 'Atur APP_URL ke alamat HTTPS aplikasi.');
        $this->check('Database MySQL', config('database.default') === 'mysql', 'Atur DB_CONNECTION=mysql dan kredensial database cPanel.');
        $this->check('Queue database', config('queue.default') === 'database', 'Atur QUEUE_CONNECTION=database agar job aplikasi diproses worker.');

        foreach ([
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            $this->check(
                'Folder writable: ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $directory),
                is_dir($directory) && is_writable($directory),
                'Pastikan folder tersedia dan dapat ditulis oleh akun cPanel.'
            );
        }

        $this->check('proc_open untuk Node.js/Chromium', function_exists('proc_open'), 'Worker queue memerlukan proc_open untuk menjalankan Browsershot, Node.js, dan Chromium saat membuat preview mockup.');

        $nodeBinary = (string) config('services.browsershot.node_binary', 'node');
        $nodePath = str_contains($nodeBinary, DIRECTORY_SEPARATOR)
            ? (is_file($nodeBinary) ? $nodeBinary : false)
            : (new ExecutableFinder)->find($nodeBinary);
        $this->check('Node.js dapat ditemukan', is_string($nodePath), 'Atur BROWSERSHOT_NODE_BINARY ke path absolut Node.js dari Terminal cPanel.');

        $nodeModules = (string) config('services.browsershot.node_module_path', base_path('node_modules'));
        $this->check(
            'Paket Puppeteer terpasang',
            is_file($nodeModules . '/puppeteer/package.json'),
            'Install dependency npm production atau arahkan BROWSERSHOT_NODE_MODULE_PATH ke node_modules yang berisi Puppeteer.'
        );

        $manifest = public_path('build/manifest.json');
        $this->check('Aset Vite sudah dibangun', is_file($manifest), 'Jalankan npm ci dan npm run build, lalu deploy folder public/build.');

        $storageLink = public_path('storage');
        $this->check(
            'Link public/storage tersedia',
            is_link($storageLink) && is_dir($storageLink),
            'Jalankan php artisan storage:link. Jika symlink dilarang, minta solusi aman ke provider.',
            warning: true
        );

        try {
            DB::connection()->getPdo();
            $this->check('Database dapat dihubungi', true);

            if (config('queue.default') === 'database') {
                foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
                    $this->check('Tabel queue ' . $table, Schema::hasTable($table), 'Jalankan php artisan migrate --force.');
                }
            }

            if (config('session.driver') === 'database') {
                $this->check('Tabel sessions', Schema::hasTable('sessions'), 'Jalankan php artisan migrate --force.');
            }

            if (config('cache.default') === 'database') {
                foreach (['cache', 'cache_locks'] as $table) {
                    $this->check('Tabel cache ' . $table, Schema::hasTable($table), 'Jalankan php artisan migrate --force.');
                }
            }
        } catch (PDOException) {
            $this->check('Database dapat dihubungi', false, 'Periksa DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, dan jalankan php artisan migrate --force.');
        }

        if (!is_string(config('services.openai.key')) || trim(config('services.openai.key')) === '') {
            $this->check('OPENAI_API_KEY tersedia', false, 'Tambahkan key melalui .env server jika fitur mockup OpenAI akan dipakai.', warning: true);
        } else {
            $this->check('OPENAI_API_KEY tersedia', true);
        }

        $this->check(
            'Worker queue dikonfigurasi',
            false,
            'Siapkan worker queue persisten atau cron yang disetujui provider untuk menjalankan job Laravel.',
            warning: true
        );
        $this->check(
            'Cron scheduler dikonfigurasi',
            false,
            'Tambahkan cron php artisan schedule:run setiap menit di cPanel.',
            warning: true
        );

        if (is_dir(storage_path('framework/cache')) && is_writable(storage_path('framework/cache'))) {
            $directory = storage_path('framework/cache/cpanel-check-' . bin2hex(random_bytes(6)));
            File::ensureDirectoryExists($directory);

            try {
                $htmlPath = $directory . '/index.html';
                $imagePath = $directory . '/preview.png';
                if (file_put_contents($htmlPath, '<!doctype html><html><body>cPanel readiness check</body></html>') === false) {
                    $this->check('HTML sementara dapat ditulis', false, 'Pastikan storage/framework/cache dapat ditulis.');
                } else {
                    $snapshot->captureFile($htmlPath, $imagePath);
                    $this->check('Node.js dan Chromium bisa merender', is_file($imagePath) && filesize($imagePath) > 0, 'Periksa path Node.js, Puppeteer, Chromium, dan izin folder temporary.');
                }
            } catch (\RuntimeException $e) {
                $this->check('Node.js dan Chromium bisa merender', false, $e->getMessage());
            } finally {
                File::deleteDirectory($directory);
            }
        }

        $this->newLine();
        $this->line("Ringkasan: {$this->failures} wajib diperbaiki, {$this->warnings} peringatan konfigurasi.");

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $label, bool $passed, ?string $remediation = null, bool $warning = false): void
    {
        if ($passed) {
            $this->components->twoColumnDetail($label, '<fg=green>OK</>');

            return;
        }

        $this->components->twoColumnDetail(
            $label,
            $warning ? '<fg=yellow>PERINGATAN</>' : '<fg=red>GAGAL</>'
        );

        if ($remediation !== null) {
            $this->line('  ' . $remediation);
        }

        if ($warning) {
            $this->warnings++;

            return;
        }

        $this->failures++;
    }
}

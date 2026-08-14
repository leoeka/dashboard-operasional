<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Command sekali-pakai buat dapatkan refresh token Google lewat OAuth
 * consent manual — dipakai buat setup Search Console (dan bisa dipakai
 * ulang buat scope Google lain nanti, misal GA4).
 *
 * Reuse GOOGLE_ADS_CLIENT_ID/SECRET yang sudah ada di .env — satu OAuth
 * Client ID boleh dipakai buat banyak scope/API, tidak perlu bikin
 * client baru tiap API.
 */
class GoogleGetRefreshToken extends Command
{
    protected $signature = 'google:get-refresh-token {--scope=https://www.googleapis.com/auth/webmasters.readonly : Scope OAuth yang diminta}';

    protected $description = 'Dapatkan refresh token Google lewat OAuth consent manual (sekali jalan, buat Search Console/GA4/dst).';

    public function handle(): int
    {
        $clientId = config('services.google_ads.client_id');
        $clientSecret = config('services.google_ads.client_secret');

        if (!$clientId || !$clientSecret) {
            $this->error('GOOGLE_ADS_CLIENT_ID / GOOGLE_ADS_CLIENT_SECRET belum diisi di .env — command ini reuse OAuth Client ID yang sama dengan Google Ads, jadi wajib ada dulu.');
            return self::FAILURE;
        }

        $scope = $this->option('scope');
        // FIX: "http://localhost" polos suka ketabrak redirect bawaan
        // XAMPP (localhost/ -> localhost/dashboard/, yang membuang query
        // string ?code=... waktu redirect). Pakai path spesifik yang
        // TIDAK ADA di htdocs XAMPP, supaya yang muncul cuma halaman 404
        // biasa — 404 tidak redirect, jadi ?code=... di address bar tetap
        // utuh dan bisa dicopy manual.
        $redirectUri = 'http://localhost/oauth2callback';

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        $this->info('=== Langkah 1 ===');
        $this->line('Buka URL ini di browser, LOGIN pakai akun Google yang sudah punya akses ke Search Console client:');
        $this->newLine();
        $this->line($authUrl);
        $this->newLine();

        $this->info('=== Langkah 2 ===');
        $this->line('Setelah klik "Allow"/"Izinkan", browser akan diarahkan ke http://localhost/oauth2callback?code=xxxxx');
        $this->line('Halaman itu akan menampilkan "404 Not Found" dari Apache/XAMPP — itu NORMAL, abaikan pesan errornya.');
        $this->line('Yang penting: lihat address bar browser, copy nilai setelah "code=" sampai sebelum "&scope" (kalau ada).');
        $this->newLine();

        $code = $this->ask('=== Langkah 3 === Tempel code di sini');

        if (!$code) {
            $this->error('Code kosong, batal.');
            return self::FAILURE;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => trim($code),
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            $this->error('Gagal tukar code jadi token. Respons dari Google:');
            $this->line($response->body());
            $this->newLine();
            $this->comment('Penyebab paling umum: redirect_uri "http://localhost/oauth2callback" belum ditambahkan ke daftar "Authorized redirect URIs" di OAuth Client ID Anda (Google Cloud Console → APIs & Services → Credentials → edit OAuth Client). Tambahkan PERSIS string itu (bukan cuma "http://localhost"), lalu ulangi command ini.');
            return self::FAILURE;
        }

        $data = $response->json();

        $this->newLine();
        if (empty($data['refresh_token'])) {
            $this->error('Tidak ada refresh_token di respons. Biasanya karena akun ini SEBELUMNYA sudah pernah kasih izin ke app yang sama — coba cabut akses lama dulu di https://myaccount.google.com/permissions, baru ulangi command ini.');
            return self::FAILURE;
        }

        $this->info('BERHASIL! Refresh token-nya:');
        $this->line($data['refresh_token']);
        $this->newLine();
        $this->comment('Tempel ke .env sesuai kebutuhan, misalnya:');
        $this->comment('GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN=' . $data['refresh_token']);

        return self::SUCCESS;
    }
}
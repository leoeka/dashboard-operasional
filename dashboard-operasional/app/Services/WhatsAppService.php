<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Kirim pesan WhatsApp via Fonnte API.
     * Dokumentasi: https://docs.fonnte.com/api-send-message/
     */
    public function send(string $phone, string $message): bool
    {
        $token = config('services.fonnte.token');

        if (!$token) {
            Log::warning('WhatsApp (Fonnte): FONNTE_TOKEN belum diisi di .env, pesan tidak dikirim.', [
                'phone' => $phone,
            ]);
            return false;
        }

        $normalizedPhone = $this->normalizePhoneNumber($phone);

        try {
            $response = Http::withHeaders([
                // FIX: Fonnte TIDAK pakai "Bearer" — token dikirim langsung
                // apa adanya di header Authorization.
                'Authorization' => $token,
            ])->asForm()->post('https://api.fonnte.com/send', [
                'target' => $normalizedPhone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $result = $response->json();

            // FIX PENTING: Fonnte bisa balikin HTTP 200 tapi pesan TETAP
            // gagal terkirim (token invalid, nomor invalid, kuota habis,
            // dll) — sukses/gagal yang sebenarnya ada di field "status"
            // dalam body JSON, BUKAN dari status code HTTP semata.
            $success = $result['status'] ?? false;

            if (!$success) {
                Log::warning('WhatsApp (Fonnte): gagal mengirim pesan.', [
                    'phone' => $normalizedPhone,
                    'reason' => $result['reason'] ?? 'unknown',
                    'raw_response' => $result,
                ]);
                return false;
            }

            Log::info('WhatsApp (Fonnte): pesan berhasil dikirim / masuk antrian.', [
                'phone' => $normalizedPhone,
                'detail' => $result['detail'] ?? null,
                'message_id' => $result['id'] ?? null,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('WhatsApp (Fonnte) Exception: ' . $e->getMessage(), [
                'phone' => $normalizedPhone,
            ]);
            return false;
        }
    }

    /**
     * Normalisasi format nomor sebelum dikirim ke Fonnte. Fonnte sendiri
     * sudah otomatis ganti awalan "0" jadi countryCode (default 62), tapi
     * kita bersihkan dulu di sisi kita supaya data yang di-log/disimpan
     * konsisten, dan untuk berjaga-jaga kalau format input dari form ada
     * karakter aneh (spasi, tanda hubung, dll).
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Buang semua karakter selain angka dan tanda +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Buang tanda + di depan kalau ada (Fonnte terima tanpa +)
        $phone = ltrim($phone, '+');

        // Fonnte otomatis ganti "0" di depan jadi "62" lewat parameter
        // countryCode, tapi kita rapikan juga di sini biar konsisten kalau
        // dipakai/ditampilkan di tempat lain (log, dst).
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }
}
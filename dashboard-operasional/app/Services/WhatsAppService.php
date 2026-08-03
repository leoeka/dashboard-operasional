<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * TODO: ganti isi method ini dengan pemanggilan API WhatsApp sungguhan
     * (mis. Fonnte, Wablas, atau Twilio WhatsApp API) begitu provider sudah
     * dipilih dan API key/kredensial tersedia di .env.
     */
    public function send(string $phone, string $message): bool
    {
        Log::info("WA Reminder (belum terhubung provider asli) ke {$phone}: {$message}");

        return true; // dianggap "berhasil" sementara, supaya alur reminder tetap tercatat
    }
}
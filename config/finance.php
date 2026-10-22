<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finance timezone
    |--------------------------------------------------------------------------
    |
    | Zona waktu operasional penagihan dan perpanjangan, sengaja dipisah
    | dari app.timezone (UTC) agar perhitungan hari kalender H-30/H-7/H-1
    | akurat sesuai zona waktu bisnis lokal tanpa mengubah penyimpanan data UTC.
    |
    */

    'timezone' => env('FINANCE_TIMEZONE', env('BILLING_TIMEZONE', 'Asia/Makassar')),

    'payment' => [
        'bank_name'        => env('FINANCE_BANK_NAME', env('BILLING_BANK_NAME')),
        'bank_account'     => env('FINANCE_BANK_ACCOUNT', env('BILLING_BANK_ACCOUNT')),
        'account_holder'   => env('FINANCE_ACCOUNT_HOLDER', env('BILLING_ACCOUNT_HOLDER')),
        'contact_email'    => env('FINANCE_CONTACT_EMAIL', env('BILLING_CONTACT_EMAIL')),
        'contact_whatsapp' => env('FINANCE_CONTACT_WA', env('BILLING_CONTACT_WA')),
    ],

];


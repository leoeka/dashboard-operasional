<?php

namespace App\Services\Finance;

use App\Models\Invoice;

/**
 * Pembangkit nomor invoice berurutan yang mudah dibaca: INV-2026-000001.
 */
class InvoiceNumberGenerator
{
    private const PREFIX = 'INV';
    private const PAD = 6;

    public function next(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = self::PREFIX . '-' . $year . '-';

        $latest = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, self::PAD, '0', STR_PAD_LEFT);
    }
}


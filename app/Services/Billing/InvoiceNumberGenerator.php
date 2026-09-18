<?php

namespace App\Services\Billing;

use App\Models\Invoice;

/**
 * Readable, sequential invoice numbers: INV-2026-000001.
 *
 * Deliberately not count() + 1. Two runs of the scheduler starting at the same
 * moment would both count the same total and both propose the same number; the
 * count is a read that nothing holds still.
 *
 * Instead the uniqueness already on `invoices.invoice_number` is the authority.
 * This proposes the next number, and the caller inserts inside a retry loop
 * (see BillingRenewalService): if another process got there first the insert
 * fails on the unique index, the next number is proposed, and the loop tries
 * again. The database decides who wins, which is the only participant that can.
 *
 * Legacy project invoices keep their old INV-XXX-9999 format. They are matched
 * out by the prefix below, so the two schemes never interfere.
 */
class InvoiceNumberGenerator
{
    private const PREFIX = 'INV';
    private const PAD = 6;

    public function next(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = self::PREFIX . '-' . $year . '-';

        // Ordering by the number column itself, not by id: an invoice created
        // out of order (a manual back-dated one, say) must not make the next
        // number collide with an existing row.
        $latest = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, self::PAD, '0', STR_PAD_LEFT);
    }
}

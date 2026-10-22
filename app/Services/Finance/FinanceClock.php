<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jam kalender operasional untuk modul Finance.
 * Menggunakan config finance.timezone (fallback ke UTC) agar perhitungan H-30/H-7/H-1
 * sesuai dengan tanggal kalender bisnis lokal.
 */
class FinanceClock
{
    public function timezone(): string
    {
        return (string) config('finance.timezone', config('app.timezone', 'UTC'));
    }

    /** Waktu sekarang pada zona waktu finance. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /** Tanggal hari ini pada zona waktu finance. */
    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    /** Mengonversi tanggal ke tanggal kalender murni tanpa offset zona waktu. */
    public function businessDate(CarbonInterface|string $date): CarbonImmutable
    {
        $value = $date instanceof CarbonInterface ? $date->format('Y-m-d') : CarbonImmutable::parse($date)->format('Y-m-d');

        return CarbonImmutable::parse($value);
    }
}

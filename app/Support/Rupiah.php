<?php

namespace App\Support;

/**
 * Seluruh nominal di SIPMA memakai rupiah (tidak ada mata uang lain, tidak ada konversi kurs),
 * jadi formatnya cukup satu: Rp 1.500.000 tanpa desimal.
 */
final class Rupiah
{
    public static function format(int|float|string|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }
}

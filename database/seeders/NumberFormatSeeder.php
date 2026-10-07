<?php

namespace Database\Seeders;

use App\Enums\NumberType;
use App\Models\NumberFormat;
use Illuminate\Database\Seeder;

/**
 * Susunan nomor bawaan (Sistem → Penomoran), mis. LOA/SIPMA/2026/0001. Susunan yang sudah
 * diubah Super Admin tidak ditimpa saat seeder dijalankan ulang.
 */
class NumberFormatSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NumberType::cases() as $type) {
            NumberFormat::query()->firstOrCreate(['type' => $type], $type->defaultFormat());
        }
    }
}

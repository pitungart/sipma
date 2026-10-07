<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipe atribut penyusun nomor. Setiap nomor wajib punya tepat satu Nomor urut.
 */
enum NumberSegmentType: string implements HasLabel
{
    case Text = 'text';         // teks tetap: value
    case Sequence = 'sequence'; // nomor urut: length, reset, starts_at
    case Year = 'year';         // tahun terbit: digits 4 | 2
    case Month = 'month';       // bulan terbit: style number | roman

    public function getLabel(): string
    {
        return __("numbering.segment_types.{$this->value}");
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'lucide-type',
            self::Sequence => 'lucide-hash',
            self::Year => 'lucide-calendar',
            self::Month => 'lucide-calendar-range',
        };
    }

    /**
     * Nada warna tipe (kelas sipma-tone-*), agar atribut mudah dibedakan di daftar.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Text => 'primary',
            self::Sequence => 'success',
            self::Year => 'info',
            self::Month => 'warning',
        };
    }

    /**
     * Isian bawaan saat atribut baru dibuat atau tipenya diganti.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return ['type' => $this->value] + match ($this) {
            self::Text => ['value' => ''],
            self::Sequence => ['length' => 4, 'reset' => 'yearly', 'starts_at' => 1],
            self::Year => ['digits' => 4],
            self::Month => ['style' => 'number'],
        };
    }
}

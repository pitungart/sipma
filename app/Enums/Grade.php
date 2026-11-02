<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Skala nilai mata kuliah. SEMENTARA, menunggu konfirmasi KUI (apakah ada D+, dan apakah BIPA memakai
 * lulus/tidak lulus); skalanya hanya perlu diubah di sini. Lulus/Tidak lulus tidak punya bobot,
 * jadi tidak ikut IP.
 */
enum Grade: string implements HasLabel
{
    case A = 'A';
    case BPlus = 'B+';
    case B = 'B';
    case CPlus = 'C+';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case Pass = 'pass';
    case Fail = 'fail';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pass, self::Fail => __("enums.grade.{$this->value}"),
            default => $this->value,
        };
    }

    public function point(): ?float
    {
        return match ($this) {
            self::A => 4.0,
            self::BPlus => 3.5,
            self::B => 3.0,
            self::CPlus => 2.5,
            self::C => 2.0,
            self::D => 1.0,
            self::E => 0.0,
            self::Pass, self::Fail => null,
        };
    }
}

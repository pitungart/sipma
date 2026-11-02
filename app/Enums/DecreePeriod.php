<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Keadaan periode SK dibanding hari ini; dihitung dari tanggal, tidak disimpan.
 */
enum DecreePeriod: string implements HasColor, HasLabel
{
    case Upcoming = 'upcoming';
    case Current = 'current';
    case Ended = 'ended';

    public function getLabel(): string
    {
        return __("enums.decree_period.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Upcoming => 'info',
            self::Current => 'success',
            self::Ended => 'gray',
        };
    }
}

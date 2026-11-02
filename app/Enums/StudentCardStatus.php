<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status KTM digital. Warna mengikuti R-1.4 dan setiap status wajib punya label + ikon (R-1.5).
 */
enum StudentCardStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return __("enums.student_card_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'warning',
            self::Revoked => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'lucide-circle-check',
            self::Expired => 'lucide-clock',
            self::Revoked => 'lucide-circle-x',
        };
    }
}

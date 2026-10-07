<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Nilai kolom visa_statuses.status (dinamai berbeda agar tidak bentrok dengan model VisaStatus).
 * Warna mengikuti R-1.4 dan setiap status wajib punya label + ikon (R-1.5).
 */
enum VisaProcessStatus: string implements HasColor, HasIcon, HasLabel
{
    case NotStarted = 'not_started';
    case InProcess = 'in_process';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __("enums.visa_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::InProcess => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::NotStarted => 'lucide-circle-minus',
            self::InProcess => 'lucide-clock',
            self::Approved => 'lucide-circle-check',
            self::Rejected => 'lucide-circle-x',
        };
    }
}

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
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProcess => 'In Process',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
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
            self::NotStarted => 'heroicon-m-minus-circle',
            self::InProcess => 'heroicon-m-clock',
            self::Approved => 'heroicon-m-check-circle',
            self::Rejected => 'heroicon-m-x-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Warna mengikuti R-1.4 dan setiap status wajib punya label + ikon (R-1.5).
 */
enum DocumentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Revision = 'revision';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __("enums.document_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Approved => 'success',
            self::Revision, self::Rejected => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'lucide-clock',
            self::Approved => 'lucide-circle-check',
            self::Revision => 'lucide-triangle-alert',
            self::Rejected => 'lucide-circle-x',
        };
    }
}

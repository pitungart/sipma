<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Warna mengikuti pemetaan status template (sipma-desing-rules.md §1.7); label + ikon wajib.
 */
enum StudentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Revision = 'revision';
    case Approved = 'approved';
    case LoaIssued = 'loa_issued';

    public function getLabel(): string
    {
        return __("enums.student_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info', // cyan: baru diajukan
            self::InReview => 'primary', // indigo: sedang diverifikasi
            self::Revision => 'danger',
            self::Approved => 'warning', // kuning: menunggu pembayaran VA
            self::LoaIssued => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'lucide-square-pen',
            self::Submitted => 'lucide-send',
            self::InReview => 'lucide-clock',
            self::Revision => 'lucide-triangle-alert',
            self::Approved => 'lucide-circle-check',
            self::LoaIssued => 'lucide-file-check',
        };
    }

    /**
     * Data pendaftaran hanya boleh diubah pendaftar saat masih draft atau diminta revisi.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Revision], true);
    }
}

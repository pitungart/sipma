<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Warna mengikuti R-1.4 dan setiap status wajib punya label + ikon (R-1.5).
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
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::InReview => 'In Review',
            self::Revision => 'Revision Required',
            self::Approved => 'Approved',
            self::LoaIssued => 'LOA Issued',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted, self::InReview => 'info',
            self::Revision => 'danger',
            self::Approved, self::LoaIssued => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-pencil-square',
            self::Submitted => 'heroicon-m-paper-airplane',
            self::InReview => 'heroicon-m-clock',
            self::Revision => 'heroicon-m-exclamation-triangle',
            self::Approved => 'heroicon-m-check-circle',
            self::LoaIssued => 'heroicon-m-document-check',
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

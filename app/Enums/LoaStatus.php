<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Warna mengikuti R-1.4 dan setiap status wajib punya label + ikon (R-1.5).
 */
enum LoaStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Downloaded = 'downloaded';

    public function getLabel(): string
    {
        return __("enums.loa_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Uploaded, self::Downloaded => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'lucide-clock',
            self::Uploaded => 'lucide-file-check',
            self::Downloaded => 'lucide-download',
        };
    }

    /**
     * File LOA sudah diunggah KUI dan bisa diunduh mahasiswa/agen.
     */
    public function isAvailable(): bool
    {
        return $this !== self::Pending;
    }
}

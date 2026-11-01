<?php

namespace App\Filament\App\Concerns;

use Filament\Pages\Page;

/**
 * Halaman akademik portal (KTM, rubrik): hanya untuk mahasiswa yang sudah ditandai Aktif atau
 * Selesai (keputusan 1 November 2026). Sebelum itu menu tidak tampil dan URL-nya ditolak.
 *
 * @mixin Page
 */
trait RequiresAcademicAccess
{
    use InteractsWithApplication;

    public static function canAccess(): bool
    {
        return static::currentApplication()?->status->hasAcademicAccess() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}

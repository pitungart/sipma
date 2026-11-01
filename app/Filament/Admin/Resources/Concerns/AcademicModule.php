<?php

namespace App\Filament\Admin\Resources\Concerns;

use Filament\Resources\Resource;

/**
 * Resource/halaman tahap pasca-LOA (Mahasiswa Asing, KTM, rubrik, SK mengajar) di grup Akademik.
 *
 * @mixin Resource
 */
trait AcademicModule
{
    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.academic');
    }

    /**
     * Sentence case (R-2.14): label navigasi tidak di-Title Case-kan Filament.
     */
    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }
}

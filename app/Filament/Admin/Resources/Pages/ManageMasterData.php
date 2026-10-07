<?php

namespace App\Filament\Admin\Resources\Pages;

use Filament\Resources\Pages\ManageRecords;

/**
 * Halaman tunggal master data (template): breadcrumb "Data master / <entitas>", judul,
 * kalimat keterangan, tombol "Tambah data"; tambah/ubah berjalan di modal.
 */
abstract class ManageMasterData extends ManageRecords
{
    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.master_data'), static::getResource()::getPluralModelLabel()];
    }

    /**
     * Sentence case (R-2.14): Filament membentuk judul dengan Str::headline().
     */
    public function getTitle(): string
    {
        return static::getResource()::getPluralModelLabel();
    }

    public function getSubheading(): ?string
    {
        return static::getResource()::masterDescription();
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        return $resource::canCreate() ? [$resource::headerCreateAction()] : [];
    }
}

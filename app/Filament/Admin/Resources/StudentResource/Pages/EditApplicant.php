<?php

namespace App\Filament\Admin\Resources\StudentResource\Pages;

use App\Filament\Admin\Resources\StudentResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ubah data pendaftar oleh Super Admin, hanya selama draf atau perlu revisi (StudentResource::canEdit).
 */
class EditApplicant extends EditRecord
{
    protected static string $resource = StudentResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.applicant.edit_title', ['name' => $this->record->full_name]);
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('admin.groups.admissions'),
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            StudentResource::getUrl('view', ['record' => $this->record]) => $this->record->full_name,
            __('admin.applicant.actions.edit'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('view', ['record' => $this->record]);
    }
}

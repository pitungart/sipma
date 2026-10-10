<?php

namespace App\Filament\Agent\Resources\StudentResource\Pages;

use App\Filament\Agent\Resources\StudentResource;
use App\Models\Student;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ubah data mahasiswa selama draf atau perlu revisi (StudentResource::canEdit → StudentPolicy::update).
 *
 * @property Student $record
 */
class EditStudent extends EditRecord
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
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            StudentResource::getUrl('view', ['record' => $this->record]) => $this->record->full_name,
            __('admin.applicant.actions.edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return StudentResource::withCurrentPeriod($data, $this->record);
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('view', ['record' => $this->record]);
    }
}

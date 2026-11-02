<?php

namespace App\Filament\Admin\Resources\TeachingDecreeResource\Pages;

use App\Filament\Admin\Resources\TeachingDecreeResource;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages\Concerns\SavesDecreeDocument;
use Filament\Resources\Pages\EditRecord;

class EditTeachingDecree extends EditRecord
{
    use SavesDecreeDocument;

    protected static string $resource = TeachingDecreeResource::class;

    public function getTitle(): string
    {
        return __('admin.teaching_decree.edit');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullDocument($data);
    }

    protected function afterSave(): void
    {
        $this->storeDocument($this->record);
        $this->record->logActivity('updated');
    }
}

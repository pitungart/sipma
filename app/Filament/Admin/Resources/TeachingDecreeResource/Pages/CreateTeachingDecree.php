<?php

namespace App\Filament\Admin\Resources\TeachingDecreeResource\Pages;

use App\Filament\Admin\Resources\TeachingDecreeResource;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages\Concerns\SavesDecreeDocument;
use Filament\Resources\Pages\CreateRecord;

class CreateTeachingDecree extends CreateRecord
{
    use SavesDecreeDocument;

    protected static string $resource = TeachingDecreeResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('admin.teaching_decree.create');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$this->pullDocument($data), 'created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        $this->storeDocument($this->record);
        $this->record->logActivity('created');
    }
}

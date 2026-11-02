<?php

namespace App\Filament\Admin\Resources\TeachingDecreeResource\Pages;

use App\Filament\Admin\Resources\TeachingDecreeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeachingDecrees extends ListRecords
{
    protected static string $resource = TeachingDecreeResource::class;

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.academic'), TeachingDecreeResource::getPluralModelLabel()];
    }

    public function getTitle(): string
    {
        return TeachingDecreeResource::getPluralModelLabel();
    }

    public function getSubheading(): ?string
    {
        return TeachingDecreeResource::masterDescription();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('admin.teaching_decree.create'))
                ->icon('lucide-plus'),
        ];
    }
}

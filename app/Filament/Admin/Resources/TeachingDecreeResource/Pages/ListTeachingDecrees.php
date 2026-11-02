<?php

namespace App\Filament\Admin\Resources\TeachingDecreeResource\Pages;

use App\Filament\Admin\Resources\TeachingDecreeResource;
use App\Support\TeachingDecreeExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            Action::make('export')
                ->label(__('admin.teaching_decree.export'))
                ->icon('lucide-download')
                ->color('gray')
                // Ekspor = yang sedang dilihat: fakultas, pencarian, dan saringan aktif
                ->action(fn (): StreamedResponse => TeachingDecreeExport::download($this->getFilteredSortedTableQuery())),

            CreateAction::make()
                ->label(__('admin.teaching_decree.create'))
                ->icon('lucide-plus'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

/**
 * Halaman daftar penerimaan (template): tab status beserta jumlahnya ada DI DALAM kartu tabel,
 * bukan bar tab Filament di atasnya. Tab tetap memakai mekanisme bawaan (getTabs + ?activeTab=).
 */
abstract class ListWithStatusTabs extends ListRecords
{
    protected static string $view = 'filament.admin.list-with-status-tabs';

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.admissions'), static::getResource()::getPluralModelLabel()];
    }

    public function getTitle(): string
    {
        return static::getResource()::getPluralModelLabel();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => view('filament.admin.status-tabs', [
                'tabs' => collect($this->getCachedTabs())->map(fn ($tab, string $key): array => [
                    'key' => $key,
                    'label' => $tab->getLabel(),
                    'icon' => $tab->getIcon(),
                    'count' => $tab->getBadge(),
                    // Warna status (enum getColor); tab tanpa badgeColor ("Semua") = netral, aktifnya primary
                    'tone' => is_string($tab->getBadgeColor()) ? $tab->getBadgeColor() : null,
                    'active' => (string) $this->activeTab === $key,
                ]),
            ]));
    }
}

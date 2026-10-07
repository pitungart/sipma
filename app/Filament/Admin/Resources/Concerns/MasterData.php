<?php

namespace App\Filament\Admin\Resources\Concerns;

use Filament\Actions\CreateAction as PageCreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pola halaman master data dari template: satu halaman daftar, tambah/ubah lewat modal,
 * tab antar entitas di atas tabel, status sebagai sakelar langsung, aksi baris berupa ikon.
 *
 * @mixin Resource
 */
trait MasterData
{
    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.master_data');
    }

    /**
     * Sentence case (R-2.14): label navigasi tidak di-Title Case-kan Filament.
     */
    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * Satu kalimat di bawah judul halaman.
     */
    abstract public static function masterDescription(): string;

    /**
     * Lebar modal tambah/ubah; form yang panjang menimpanya.
     */
    public static function modalWidth(): MaxWidth
    {
        return MaxWidth::Large;
    }

    /**
     * Pengaturan bersama tabel: tab entitas di kepala kartu dan tombol tambah di empty state.
     */
    public static function masterTable(Table $table): Table
    {
        return $table
            ->header(fn () => view('filament.admin.master-tabs', ['tabs' => static::masterTabs()]))
            ->emptyStateIcon('lucide-database')
            ->emptyStateHeading(__('admin.master.empty_heading'))
            ->emptyStateDescription(static::canCreate() ? __('admin.master.empty_description') : __('admin.master.empty_description_readonly'))
            ->emptyStateActions(static::canCreate() ? [
                static::configureFormAction(CreateAction::make())
                    ->label(__('admin.master.add'))
                    ->color('gray'),
            ] : [])
            // Semua tabel master data mulai dari 10 baris per halaman
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }

    /**
     * Seluruh resource master data yang boleh dibuka pengguna, urut seperti sidebar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function masterTabs(): Collection
    {
        return collect(Filament::getCurrentPanel()->getResources())
            ->filter(fn (string $resource): bool => in_array(MasterData::class, class_uses_recursive($resource), true)
                && $resource::canViewAny())
            ->sortBy(fn (string $resource): int => $resource::getNavigationSort() ?? 0)
            ->map(fn (string $resource): array => [
                'label' => $resource::getPluralModelLabel(),
                'icon' => $resource::getNavigationIcon(),
                'url' => $resource::getUrl(),
                'count' => $resource::getEloquentQuery()->count(),
                'active' => $resource === static::class,
            ])
            ->values();
    }

    /**
     * Tombol "Tambah data" di kepala halaman.
     */
    public static function headerCreateAction(): PageCreateAction
    {
        return static::configureFormAction(PageCreateAction::make())
            ->label(__('admin.master.add'))
            ->icon('lucide-plus');
    }

    /**
     * Modal tambah/ubah ala template: tile ikon, judul, subjudul "Data master · <entitas>",
     * kepala & kaki menempel agar form panjang tetap bisa digulir.
     *
     * @template T of \Filament\Actions\CreateAction|\Filament\Tables\Actions\CreateAction|EditAction
     *
     * @param  T  $action
     * @return T
     */
    public static function configureFormAction($action)
    {
        $isEdit = $action instanceof EditAction;

        return $action
            ->modalHeading(__($isEdit ? 'admin.master.edit_heading' : 'admin.master.create_heading', [
                'label' => Str::lower(static::getModelLabel()),
            ]))
            ->modalDescription(__('admin.master.modal_subtitle', ['entity' => static::getPluralModelLabel()]))
            ->modalIcon(static::getNavigationIcon())
            ->modalIconColor('primary')
            ->modalWidth(static::modalWidth())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->modalCancelActionLabel(__('admin.master.cancel'))
            ->when(! $isEdit, fn ($action) => $action->createAnother(false));
    }

    public static function editAction(): EditAction
    {
        return static::configureFormAction(EditAction::make())
            ->iconButton()
            ->icon('lucide-pencil')
            ->color('gray')
            ->tooltip(__('admin.master.edit'));
    }

    /**
     * Konfirmasi hapus kecil di tengah (template): ikon merah, "Hapus X?", Batal / Ya, hapus.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->iconButton()
            ->icon('lucide-trash-2')
            ->color('gray')
            ->extraAttributes(['class' => 'sipma-row-delete'])
            ->tooltip(__('admin.master.delete'))
            ->modalHeading(fn (Model $record): string => __('admin.master.delete_heading', [
                'name' => static::getRecordTitle($record),
            ]))
            ->modalDescription(__('admin.master.delete_description'))
            ->modalIcon('lucide-trash-2')
            ->modalIconColor('danger')
            ->modalSubmitAction(fn ($action) => $action->color('danger'))
            ->modalWidth(MaxWidth::Medium)
            ->modalSubmitActionLabel(__('admin.master.delete_confirm'))
            ->modalCancelActionLabel(__('admin.master.cancel'));
    }

    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->iconButton()
            ->icon('lucide-undo-2')
            ->color('gray')
            ->tooltip(__('admin.master.restore'));
    }

    /**
     * Status aktif sebagai sakelar langsung di tabel; perubahan tersimpan seketika.
     */
    public static function activeColumn(string $name = 'is_active'): ToggleColumn
    {
        return ToggleColumn::make($name)
            ->label(__('admin.master.status'))
            ->onColor('primary')
            ->disabled(fn (Model $record): bool => ! static::canEdit($record))
            ->afterStateUpdated(fn (Model $record, bool $state) => Notification::make()
                ->success()
                ->title(__($state ? 'admin.master.activated' : 'admin.master.deactivated', [
                    'name' => static::getRecordTitle($record),
                ]))
                ->send());
    }

    /**
     * Sakelar "Aktif" di dalam modal: kartu bergaris dengan keterangan di bawah label.
     */
    public static function activeField(string $name = 'is_active'): Toggle
    {
        return Toggle::make($name)
            ->label(__('admin.common.is_active'))
            ->helperText(__('admin.master.active_hint'))
            ->default(true)
            ->columnSpanFull()
            ->extraFieldWrapperAttributes(['class' => 'sipma-toggle-card']);
    }
}

<?php

namespace App\Filament\Admin\Resources;

use App\Enums\MouStatus;
use App\Filament\Admin\Resources\AgentResource\Pages;
use App\Filament\Admin\Resources\Concerns\RunsWorkflow;
use App\Filament\Support\FormModal;
use App\Models\Agent;
use App\Workflow\MouWorkflow;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * UC-24 Kelola agen (lihat daftar) & UC-25 Verifikasi MOU — hanya Super Admin (AgentPolicy tidak
 * memberi viewAny). Agen dibuat lewat pendaftaran portal, jadi tidak ada tombol tambah.
 */
class AgentResource extends Resource
{
    use RunsWorkflow;

    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'lucide-handshake';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.admissions');
    }

    public static function getModelLabel(): string
    {
        return __('admin.agent.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.agent.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * MOU yang menunggu diperiksa.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Agent::query()->whereHas('latestMou', fn (Builder $q) => $q->where('status', MouStatus::Pending))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['latestMou', 'country'])->withCount('students');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $pendingMou = fn (Agent $record): bool => $record->latestMou?->status === MouStatus::Pending;

        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.agent.plural'))]))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-handshake')
            ->emptyStateHeading(__('admin.agent.empty_heading'))
            ->emptyStateDescription(__('admin.agent.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label(__('admin.agent.fields.company'))
                    ->description(fn (Agent $record): ?string => $record->email)
                    ->searchable(['company_name', 'email'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact')
                    ->label(__('admin.agent.fields.contact'))
                    ->state(fn (Agent $record): ?string => trim("{$record->first_name} {$record->last_name}") ?: null)
                    ->description(fn (Agent $record): ?string => $record->phone)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('country.name')
                    ->label(__('admin.agent.fields.country'))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('students_count')
                    ->label(__('admin.agent.fields.students'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('latestMou.status')
                    ->label(__('admin.agent.fields.mou'))
                    ->badge()
                    ->description(fn (Agent $record): ?string => $record->latestMou?->mou_number)
                    ->placeholder(__('admin.agent.no_mou')),

                Tables\Columns\TextColumn::make('latestMou.created_at')
                    ->label(__('admin.agent.fields.mou_uploaded_at'))
                    ->date('j M Y')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->actions([
                static::previewTableAction(fn (Agent $record): array => [
                    'url' => route('files.mou', $record->latestMou),
                    'image' => false,
                    'title' => 'MOU · '.$record->company_name,
                ])->visible(fn (Agent $record): bool => $record->latestMou !== null),

                Tables\Actions\Action::make('approveMou')
                    ->iconButton()
                    ->icon('lucide-check')
                    ->color('gray')
                    ->tooltip(__('admin.agent.actions.approve'))
                    ->visible($pendingMou)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Agent $record): string => __('admin.agent.approve_heading', ['name' => $record->company_name]))
                    ->modalDescription(__('admin.agent.approve_description'))
                    ->modalIcon('lucide-check')
                    ->modalIconColor('success')
                    ->modalSubmitActionLabel(__('admin.agent.actions.approve'))
                    ->action(fn (Agent $record) => static::runStep(
                        fn (MouWorkflow $w) => $w->approve($record->latestMou),
                        __('admin.agent.done.approved'),
                    )),

                FormModal::apply(Tables\Actions\Action::make('rejectMou'))
                    ->iconButton()
                    ->icon('lucide-x')
                    ->color('gray')
                    ->extraAttributes(['class' => 'sipma-row-delete'])
                    ->tooltip(__('admin.agent.actions.reject'))
                    ->visible($pendingMou)
                    ->modalHeading(fn (Agent $record): string => __('admin.agent.reject_heading', ['name' => $record->company_name]))
                    ->modalIcon('lucide-x')
                    ->modalIconColor('danger')
                    ->modalWidth(MaxWidth::Large)
                    ->modalSubmitActionLabel(__('admin.master.save'))
                    ->form([Textarea::make('note')->label(__('admin.agent.fields.reject_note'))->rows(3)->required()])
                    ->action(fn (Agent $record, array $data) => static::runStep(
                        fn (MouWorkflow $w) => $w->reject($record->latestMou, $data['note']),
                        __('admin.agent.done.rejected'),
                    )),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
        ];
    }
}

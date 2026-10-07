<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Concerns\RunsWorkflow;
use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Support\Rupiah;
use App\Workflow\StudentWorkflow;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * UC-19 Lihat pembayaran & UC-20 Verifikasi pembayaran — hanya Super Admin (PaymentPolicy
 * tidak memberi viewAny ke Admin Fakultas). Pembayaran dibuat pendaftar setelah disetujui.
 */
class PaymentResource extends Resource
{
    use RunsWorkflow;

    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'lucide-receipt';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.admissions');
    }

    public static function getModelLabel(): string
    {
        return __('admin.payment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payment.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Payment::query()->where('status', PaymentStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student:id,full_name,program_id', 'student.program:id,name', 'paymentAccount:id,bank_name']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.payment.plural'))]))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-receipt')
            ->emptyStateHeading(__('admin.payment.empty_heading'))
            ->emptyStateDescription(__('admin.payment.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label(__('admin.dashboard.applicant_name'))
                    ->description(fn (Payment $record): ?string => $record->student?->program?->name)
                    ->url(fn (Payment $record): ?string => $record->student ? StudentResource::getUrl('view', ['record' => $record->student]) : null)
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.payment.fields.type')),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.payment.fields.amount'))
                    ->formatStateUsing(fn (string $state): string => Rupiah::format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('va_number')
                    ->label(__('admin.payment.fields.account'))
                    ->description(fn (Payment $record): ?string => $record->paymentAccount?->bank_name)
                    ->placeholder(__('admin.payment.no_account'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.dashboard.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.payment.fields.uploaded_at'))
                    ->date('j M Y, H.i')
                    ->sortable(),
            ])
            ->actions([
                static::previewTableAction(fn (Payment $record): array => [
                    'url' => route('files.payment', $record),
                    'image' => ! str_ends_with((string) $record->proof_file, '.pdf'),
                    'title' => $record->type->getLabel().' · '.$record->student?->full_name,
                ]),

                Tables\Actions\Action::make('verify')
                    ->iconButton()
                    ->icon('lucide-check')
                    ->color('gray')
                    ->tooltip(__('admin.payment.actions.verify'))
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.payment.verify_heading'))
                    ->modalDescription(fn (Payment $record): string => __('admin.payment.verify_description', [
                        'type' => $record->type->getLabel(),
                        'amount' => Rupiah::format($record->amount),
                    ]))
                    ->modalIcon('lucide-check')
                    ->modalIconColor('success')
                    ->modalSubmitActionLabel(__('admin.payment.actions.verify'))
                    ->action(fn (Payment $record) => static::runStep(
                        fn (StudentWorkflow $w) => $w->verifyPayment($record),
                        __('admin.applicant.done.payment_verified'),
                    )),

                Tables\Actions\Action::make('reject')
                    ->iconButton()
                    ->icon('lucide-x')
                    ->color('gray')
                    ->extraAttributes(['class' => 'sipma-row-delete'])
                    ->tooltip(__('admin.payment.actions.reject'))
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Pending)
                    ->modalHeading(__('admin.payment.reject_heading'))
                    ->modalIcon('lucide-x')
                    ->modalIconColor('danger')
                    ->modalWidth(MaxWidth::Large)
                    ->modalSubmitActionLabel(__('admin.master.save'))
                    ->form([Textarea::make('note')->label(__('admin.payment.fields.rejection_note'))->rows(3)->required()])
                    ->action(fn (Payment $record, array $data) => static::runStep(
                        fn (StudentWorkflow $w) => $w->rejectPayment($record, $data['note']),
                        __('admin.applicant.done.payment_rejected'),
                    )),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}

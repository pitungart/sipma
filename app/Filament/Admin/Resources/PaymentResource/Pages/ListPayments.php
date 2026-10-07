<?php

namespace App\Filament\Admin\Resources\PaymentResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Filament\Admin\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListWithStatusTabs
{
    protected static string $resource = PaymentResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.payment.description');
    }

    /**
     * Mulai dari yang menunggu verifikasi: itulah pekerjaan staf di halaman ini.
     */
    public function getDefaultActiveTab(): string
    {
        return PaymentStatus::Pending->value;
    }

    public function getTabs(): array
    {
        $counts = Payment::query()->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            ...collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [
                $status->value => Tab::make($status->getLabel())
                    ->badge((int) ($counts[$status->value] ?? 0))
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status)),
            ])->all(),
            'all' => Tab::make(__('admin.applicant.tabs.all'))->badge($counts->sum()),
        ];
    }
}

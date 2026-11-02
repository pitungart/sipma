<?php

namespace App\Filament\Admin\Resources\StudentCardResource\Pages;

use App\Enums\StudentCardStatus;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Filament\Admin\Resources\StudentCardResource;
use App\Filament\Support\StudentCardActions;
use App\Models\StudentCard;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListStudentCards extends ListWithStatusTabs
{
    protected static string $resource = StudentCardResource::class;

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.academic'), StudentCardResource::getPluralModelLabel()];
    }

    public function getSubheading(): ?string
    {
        return __('admin.student_card.description');
    }

    protected function getHeaderActions(): array
    {
        return [
            StudentCardActions::issue(Action::make('issue'))
                ->visible(fn (): bool => Gate::allows('create', StudentCard::class)),
        ];
    }

    public function getTabs(): array
    {
        $counts = StudentCardResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => Tab::make(__('admin.student_card.tabs.all'))->badge($counts->sum()),
            ...collect(StudentCardStatus::cases())->mapWithKeys(fn (StudentCardStatus $status): array => [
                $status->value => Tab::make($status->getLabel())
                    ->badge((int) ($counts[$status->value] ?? 0))
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status)),
            ])->all(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\AgentResource\Pages;

use App\Enums\MouStatus;
use App\Filament\Admin\Resources\AgentResource;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Models\Agent;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tab mengikuti status MOU terbaru tiap agen; "Belum ada MOU" = agen yang baru mendaftar.
 */
class ListAgents extends ListWithStatusTabs
{
    protected static string $resource = AgentResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.agent.description');
    }

    public function getTabs(): array
    {
        $withStatus = fn (MouStatus $status): \Closure => fn (Builder $query): Builder => $query
            ->whereHas('latestMou', fn (Builder $q) => $q->where('status', $status));

        return [
            'all' => Tab::make(__('admin.applicant.tabs.all'))->badge(Agent::query()->count()),
            ...collect(MouStatus::cases())->mapWithKeys(fn (MouStatus $status): array => [
                $status->value => Tab::make($status->getLabel())
                    ->badge($withStatus($status)(Agent::query())->count())
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing($withStatus($status)),
            ])->all(),
            'none' => Tab::make(__('admin.agent.no_mou'))
                ->badge(Agent::query()->doesntHave('mous')->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->doesntHave('mous')),
        ];
    }
}

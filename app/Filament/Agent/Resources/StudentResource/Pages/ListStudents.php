<?php

namespace App\Filament\Agent\Resources\StudentResource\Pages;

use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Filament\Agent\Resources\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * UC-01 / UC-14: semua mahasiswa agen dengan tab status di dalam kartu tabel (pola admin).
 * Urutan tab: yang perlu tindakan agen lebih dulu (draf, perlu revisi).
 */
class ListStudents extends ListWithStatusTabs
{
    protected static string $resource = StudentResource::class;

    private const TAB_ORDER = [
        StudentStatus::Draft,
        StudentStatus::Revision,
        StudentStatus::Submitted,
        StudentStatus::InReview,
        StudentStatus::Approved,
        StudentStatus::LoaIssued,
    ];

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [StudentResource::getPluralModelLabel()];
    }

    public function getSubheading(): ?string
    {
        return __('agent.students.description');
    }

    public function getTabs(): array
    {
        $counts = Student::query()
            ->visibleTo(Filament::auth()->user())
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => Tab::make(__('admin.applicant.tabs.all'))->badge($counts->sum()),
            ...collect(self::TAB_ORDER)->mapWithKeys(fn (StudentStatus $status): array => [
                $status->value => Tab::make($status->getLabel())
                    ->badge((int) ($counts[$status->value] ?? 0))
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status)),
            ])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('agent.students.actions.create'))
                ->icon('lucide-plus')
                ->visible(fn (): bool => StudentResource::canCreate())
                ->url(fn (): string => StudentResource::getUrl('create')),
        ];
    }
}

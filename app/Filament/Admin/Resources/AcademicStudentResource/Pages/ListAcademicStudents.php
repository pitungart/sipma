<?php

namespace App\Filament\Admin\Resources\AcademicStudentResource\Pages;

use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tab mengikuti tahap pasca-LOA: Semua · Siap diaktifkan (LOA terbit) · Aktif · Selesai.
 * ?activeTab= dipakai kartu "LOA terbit" di dashboard.
 */
class ListAcademicStudents extends ListWithStatusTabs
{
    protected static string $resource = AcademicStudentResource::class;

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.academic'), AcademicStudentResource::getPluralModelLabel()];
    }

    public function getSubheading(): ?string
    {
        return __('admin.academic_student.description');
    }

    public function getTabs(): array
    {
        $counts = AcademicStudentResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => Tab::make(__('admin.academic_student.tabs.all'))->badge($counts->sum()),
            ...collect(StudentStatus::acceptedStatuses())->mapWithKeys(fn (StudentStatus $status): array => [
                $status->value => Tab::make($status === StudentStatus::LoaIssued ? __('admin.academic_student.tabs.ready') : $status->getLabel())
                    ->badge((int) ($counts[$status->value] ?? 0))
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status)),
            ])->all(),
        ];
    }
}

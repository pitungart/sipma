<?php

namespace App\Filament\Admin\Resources\AcademicStudentResource\Pages;

use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Models\StudentCard;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Tab mengikuti tahap pasca-LOA: Semua · Siap diaktifkan (LOA terbit) · Aktif · Selesai.
 * ?activeTab= dipakai kartu "LOA terbit" di dashboard.
 */
class ListAcademicStudents extends ListWithStatusTabs
{
    protected static string $resource = AcademicStudentResource::class;

    protected static string $view = 'filament.admin.list-with-guide';

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

    public function actionGuideIntro(): string
    {
        return __('admin.academic_student.guide.intro');
    }

    /**
     * Fungsi tombol massal yang tersedia di tab ini; Selesai tidak punya aksi massal.
     *
     * @return list<array{label: string, text: string}>
     */
    public function actionGuide(): array
    {
        $tab = $this->activeTab ?? 'all';
        $guide = [];

        if (in_array($tab, AcademicStudentResource::ENROLL_TABS, true)) {
            $guide[] = ['label' => __('admin.academic_student.actions.enroll_bulk'), 'text' => __('admin.academic_student.guide.enroll')];
        }

        if (in_array($tab, AcademicStudentResource::ENROLLED_TABS, true)) {
            if (Gate::allows('create', StudentCard::class)) {
                $guide[] = ['label' => __('admin.student_card.actions.issue'), 'text' => __('admin.academic_student.guide.issue')];
            }

            $guide[] = ['label' => __('admin.academic_student.actions.complete'), 'text' => __('admin.academic_student.guide.complete')];
        }

        return $guide;
    }

    public function getTabs(): array
    {
        $counts = AcademicStudentResource::getEloquentQuery()
            ->toBase()
            // select() membuang withCount dari resource; MySQL menolak kolom itu bersama GROUP BY
            ->select('status')
            ->selectRaw('COUNT(*) as total')
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

<?php

namespace App\Filament\Admin\Resources\StudentCardResource\Pages;

use App\Enums\StudentCardStatus;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Filament\Admin\Resources\StudentCardResource;
use App\Filament\Support\StudentCardActions;
use App\Filament\Support\StudentCardBatch;
use App\Models\StudentCard;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListStudentCards extends ListWithStatusTabs
{
    protected static string $resource = StudentCardResource::class;

    protected static string $view = 'filament.admin.list-with-guide';

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
            StudentCardBatch::downloadByPeriod(Action::make('downloadByPeriod'))
                ->tooltip(__('admin.student_card.guide.download_zip_tip')),

            StudentCardActions::issue(Action::make('issue'))
                ->color('gray')
                ->tooltip(__('admin.student_card.guide.issue_tip'))
                ->visible(fn (): bool => Gate::allows('create', StudentCard::class)),

            StudentCardBatch::issueByPeriod(Action::make('issueByPeriod'))
                ->tooltip(__('admin.student_card.guide.issue_period_tip')),
        ];
    }

    /** Judul alert sudah menjelaskan isinya, jadi tidak perlu kalimat pembuka. */
    public function actionGuideIntro(): string
    {
        return '';
    }

    /**
     * Fungsi tombol di halaman ini. Admin Fakultas hanya bisa mengunduh, jadi hanya itu yang dijelaskan.
     *
     * @return list<array{label: string, text: string}>
     */
    public function actionGuide(): array
    {
        $manage = Gate::allows('create', StudentCard::class);
        $item = fn (string $action, string $guide): array => [
            'label' => __("admin.student_card.actions.{$action}"),
            'text' => __("admin.student_card.guide.{$guide}"),
        ];

        return array_values(array_filter([
            $manage ? $item('issue_period', 'issue_period') : null,
            $manage ? $item('issue', 'issue') : null,
            $item('download_zip', 'download_zip'),
            $item('download', 'download'),
            $manage ? $item('reissue', 'reissue') : null,
            $manage ? $item('revoke', 'revoke') : null,
        ]));
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

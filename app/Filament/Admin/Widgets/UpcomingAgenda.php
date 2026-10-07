<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\AcademicPeriodResource;
use App\Models\AcademicPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Agenda mendatang dari periode akademik: pendaftaran dibuka/ditutup, program mulai/selesai.
 * Setiap jenis tanggal punya warna sendiri seperti kategori kalender di template.
 */
class UpcomingAgenda extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.upcoming-agenda';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    /**
     * kolom tanggal => [kunci label, nada warna]
     */
    private const EVENTS = [
        'registration_opens_at' => ['agenda_opens', 'info'],
        'registration_closes_at' => ['agenda_closes', 'danger'],
        'starts_on' => ['agenda_starts', 'primary'],
        'ends_on' => ['agenda_ends', 'success'],
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'events' => $this->events(),
            'link' => AcademicPeriodResource::canViewAny() ? AcademicPeriodResource::getUrl() : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function events(): Collection
    {
        $today = now()->startOfDay();

        $periods = AcademicPeriod::query()
            ->visibleTo($this->user())
            ->where('is_active', true)
            ->where(fn ($q) => collect(array_keys(self::EVENTS))
                ->each(fn (string $column) => $q->orWhereDate($column, '>=', $today)))
            ->get();

        return $periods
            ->flatMap(fn (AcademicPeriod $period): array => collect(self::EVENTS)
                ->filter(fn ($_, string $column): bool => $period->{$column} !== null
                    && $period->{$column}->greaterThanOrEqualTo($today))
                ->map(fn (array $meta, string $column): array => $this->event($period, $period->{$column}, ...$meta))
                ->values()
                ->all())
            ->sortBy('timestamp')
            ->take(4)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function event(AcademicPeriod $period, CarbonInterface $date, string $label, string $tone): array
    {
        return [
            'timestamp' => $date->timestamp,
            'day' => $date->format('j'),
            'month' => $date->translatedFormat('M'),
            'title' => $period->name,
            'meta' => $date->translatedFormat('l').' · '.__('admin.dashboard.'.$label),
            'tone' => $tone,
        ];
    }
}

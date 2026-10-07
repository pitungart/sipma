<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Program;

/**
 * Pendaftar per program tahun berjalan, sebagai bar horizontal (program terbanyak di atas).
 */
class ProgramBars extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.program-bars';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $programs = Program::query()
            ->visibleTo($this->user())
            ->withCount(['students' => fn ($q) => $q->whereYear('created_at', now()->year)])
            ->orderByDesc('students_count')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);

        $max = max(1, (int) $programs->max('students_count'));

        return [
            'year' => now()->year,
            'bars' => $programs->map(fn (Program $program): array => [
                'name' => $program->name,
                'value' => $program->students_count,
                'width' => round($program->students_count / $max * 100, 1),
            ]),
        ];
    }
}

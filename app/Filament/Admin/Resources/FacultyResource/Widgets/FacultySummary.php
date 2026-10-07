<?php

namespace App\Filament\Admin\Resources\FacultyResource\Widgets;

use App\Filament\Widgets\SummaryLine;
use App\Models\Faculty;

class FacultySummary extends SummaryLine
{
    public function summaryItems(): array
    {
        $total = Faculty::query()->count();
        $withPrograms = Faculty::query()->has('programs')->count();

        return [
            ['value' => $total, 'label' => __('admin.faculty.summary.total')],
            ['value' => $withPrograms, 'label' => __('admin.faculty.summary.with_programs')],
            ['value' => $total - $withPrograms, 'label' => __('admin.faculty.summary.empty')],
        ];
    }
}

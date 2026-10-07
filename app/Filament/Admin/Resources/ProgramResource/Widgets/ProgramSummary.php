<?php

namespace App\Filament\Admin\Resources\ProgramResource\Widgets;

use App\Filament\Widgets\SummaryLine;
use App\Models\Program;
use Filament\Facades\Filament;

class ProgramSummary extends SummaryLine
{
    public function summaryItems(): array
    {
        // Mengikuti pembatasan role yang sama dengan tabelnya (Admin Fakultas: fakultasnya saja)
        $programs = Program::query()->visibleTo(Filament::auth()->user());

        $total = (clone $programs)->count();
        $active = (clone $programs)->where('is_active', true)->count();

        return [
            ['value' => $total, 'label' => __('admin.program.summary.total')],
            ['value' => $active, 'label' => __('admin.program.summary.active')],
            ['value' => $total - $active, 'label' => __('admin.program.summary.inactive')],
        ];
    }
}

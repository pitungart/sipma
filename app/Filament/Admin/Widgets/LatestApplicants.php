<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\Student;

/**
 * Lima pendaftar terakhir: avatar inisial, nama + negara, program, status, tanggal.
 */
class LatestApplicants extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.latest-applicants';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'rows' => $this->students()
                ->with(['program:id,name', 'nationality'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Student $student): array => [
                    'name' => $student->full_name,
                    'url' => StudentResource::getUrl('view', ['record' => $student]),
                    'initials' => InitialsAvatarProvider::initialsOf($student->full_name),
                    'tone' => InitialsAvatarProvider::toneOf($student->full_name),
                    'country' => $student->nationality?->name ?? $student->email,
                    'program' => $student->program?->name,
                    'status' => $student->status,
                    'date' => $student->created_at?->translatedFormat('j M Y'),
                ]),
        ];
    }
}

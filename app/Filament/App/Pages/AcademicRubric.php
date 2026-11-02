<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Concerns\RequiresAcademicAccess;
use App\Models\StudentCourse;
use App\Support\AcademicSummary;
use Filament\Pages\Page;

/**
 * Rubrik akademik mahasiswa di portal: mata kuliah yang ditempuh, nilai, dan IP.
 */
class AcademicRubric extends Page
{
    use RequiresAcademicAccess;

    protected static ?string $navigationIcon = 'lucide-book-open';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'academic-rubric';

    protected static string $view = 'filament.app.academic-rubric';

    public static function getNavigationLabel(): string
    {
        return __('workflow.rubric.title');
    }

    public function getTitle(): string
    {
        return __('workflow.rubric.title');
    }

    public function getSubheading(): ?string
    {
        return __('student.rubric.description');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $student = static::currentApplication();
        $rows = StudentCourse::inPackageOrder($student->studentCourses()->with('course')->get());

        return [
            'student' => $student->loadMissing('program.faculty'),
            'rows' => $rows,
            'summary' => AcademicSummary::from($rows),
        ];
    }
}

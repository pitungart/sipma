<?php

namespace App\Support;

use App\Models\StudentCourse;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Ringkasan rubrik satu mahasiswa untuk admin dan portal: total SKS, MK selesai, dan IP.
 * MK yang di-drop tidak dihitung. IP = rata-rata bobot × SKS, hanya dari MK yang punya bobot dan SKS.
 */
final class AcademicSummary
{
    public function __construct(
        public readonly int $credits,
        public readonly int $completed,
        public readonly int $total,
        public readonly ?float $gpa,
    ) {}

    /**
     * @param  Collection<int, StudentCourse>  $rows  dengan relasi course
     */
    public static function from(Collection $rows): self
    {
        $taken = $rows->reject(fn (StudentCourse $row): bool => $row->isDropped());

        $weighted = $taken->filter(fn (StudentCourse $row): bool => $row->grade_point !== null && $row->graded_credits > 0);
        $credits = $weighted->sum('graded_credits');

        return new self(
            credits: (int) $taken->sum(fn (StudentCourse $row): int => (int) $row->effectiveCredits()),
            completed: $taken->filter(fn (StudentCourse $row): bool => $row->grade !== null)->count(),
            total: $taken->count(),
            gpa: $credits > 0
                ? round($weighted->sum(fn (StudentCourse $row): float => (float) $row->grade_point * $row->graded_credits) / $credits, 2)
                : null,
        );
    }

    public function gpaLabel(): string
    {
        return $this->gpa === null ? '—' : (string) Number::format($this->gpa, precision: 2, locale: app()->getLocale());
    }
}

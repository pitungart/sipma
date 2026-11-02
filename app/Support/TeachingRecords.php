<?php

namespace App\Support;

use App\Enums\StudentCourseStatus;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * Menghubungkan SK Mengajar dengan mahasiswa: SK yang memuat MK mahasiswa (belum di-drop) dan periodenya
 * beririsan dengan masa aktifnya. Hanya informasi; tidak menentukan siapa yang mengisi nilai (K3).
 */
final class TeachingRecords
{
    /**
     * @return Collection<int, TeachingAssignment>
     */
    public static function forStudent(Student $student): Collection
    {
        if ($student->enrolled_at === null) {
            return collect();
        }

        return TeachingAssignment::query()
            ->whereIn('course_id', $student->studentCourses()->where('status', '!=', StudentCourseStatus::Dropped)->select('course_id'))
            ->whereHas('decree', fn ($decree) => $decree->overlapping($student->enrolled_at, $student->completed_at))
            ->with(['decree', 'lecturer', 'course'])
            ->get()
            ->sortByDesc(fn (TeachingAssignment $row) => $row->decree->decree_date)
            ->values();
    }

    /**
     * Teks pengajar per MK, mis. "Dr. Ayu (Koordinator), Budi, M.T. (Asisten)". Dosen yang tercantum di
     * beberapa SK cukup disebut sekali.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @return Collection<string, string> course_id => teks
     */
    public static function teachersByCourse(Collection $assignments): Collection
    {
        return $assignments
            ->groupBy('course_id')
            ->map(fn (Collection $rows): string => self::teachers($rows));
    }

    /**
     * @param  Collection<int, TeachingAssignment>  $rows  satu MK
     */
    public static function teachers(Collection $rows): string
    {
        return $rows
            ->unique('lecturer_id')
            ->map(fn (TeachingAssignment $row): string => "{$row->lecturer?->name} ({$row->role->getLabel()})")
            ->implode(', ');
    }
}

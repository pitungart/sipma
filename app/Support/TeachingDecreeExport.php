<?php

namespace App\Support;

use App\Enums\StudentCourseStatus;
use App\Models\StudentCourse;
use App\Models\TeachingAssignment;
use App\Models\TeachingDecree;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap pengajar mahasiswa asing (CSV), satu baris per penugasan. Query dari pemanggil sudah dibatasi
 * fakultas dan saringan yang aktif. Format laporan USDI belum diterima, jadi kolomnya masih umum.
 */
final class TeachingDecreeExport
{
    /**
     * @param  Builder<TeachingDecree>  $query
     */
    public static function download(Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                __('admin.teaching_decree.fields.number'),
                __('admin.teaching_decree.fields.date'),
                __('admin.teaching_decree.fields.faculty'),
                __('admin.teaching_decree.fields.period_start'),
                __('admin.teaching_decree.fields.period_end'),
                __('admin.lecturer.label'),
                __('admin.lecturer.fields.nip'),
                __('admin.course.fields.code'),
                __('admin.course.fields.name'),
                __('admin.program.label'),
                __('admin.teaching_decree.fields.role'),
                __('admin.teaching_decree.fields.students'),
            ]);

            (clone $query)
                ->with(['faculty', 'assignments.lecturer', 'assignments.course.program'])
                ->reorder()
                ->latest('decree_date')
                ->lazy()
                ->each(function (TeachingDecree $decree) use ($out): void {
                    $decree->assignments->each(fn (TeachingAssignment $row) => fputcsv($out, [
                        $decree->decree_number,
                        $decree->decree_date?->toDateString(),
                        $decree->faculty?->name,
                        $decree->period_start?->toDateString(),
                        $decree->period_end?->toDateString(),
                        $row->lecturer?->name,
                        $row->lecturer?->nip,
                        $row->course?->code,
                        $row->course?->localizedName(),
                        $row->course?->program?->name,
                        $row->role->getLabel(),
                        self::students($decree, $row->course_id),
                    ]));
                });

            fclose($out);
        }, 'sipma-sk-mengajar-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Mahasiswa yang menempuh MK itu dan masa aktifnya beririsan dengan periode SK.
     */
    public static function students(TeachingDecree $decree, string $courseId): int
    {
        return StudentCourse::query()
            ->where('course_id', $courseId)
            ->where('status', '!=', StudentCourseStatus::Dropped)
            ->whereHas('student', fn (Builder $student) => $student
                ->whereDate('enrolled_at', '<=', $decree->period_end)
                ->where(fn (Builder $active) => $active
                    ->whereNull('completed_at')
                    ->orWhereDate('completed_at', '>=', $decree->period_start)))
            ->count();
    }
}

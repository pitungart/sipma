<?php

namespace App\Workflow;

use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang mengubah daftar mata kuliah mahasiswa (TP-3). Hanya untuk mahasiswa
 * Aktif, jadi data mahasiswa Selesai terkunci sampai status Aktif dibuka kembali. Siapa yang boleh
 * memanggilnya diperiksa di layar (StudentPolicy::manageCourses).
 */
final class AcademicRecordWorkflow
{
    /**
     * Tambahkan semua MK aktif program yang belum dimiliki mahasiswa; aman dijalankan berulang.
     * MK yang sudah di-drop tidak kembali. Riwayat mencatat satu baris, bukan satu per MK.
     */
    public function applyPackage(Student $student): int
    {
        return DB::transaction(function () use ($student): int {
            $student = $this->lockedEnrolled($student);

            $courses = Course::query()
                ->where('program_id', $student->program_id)
                ->active()
                ->whereNotIn('id', $student->studentCourses()->select('course_id'))
                ->get();

            if ($courses->isEmpty()) {
                return 0;
            }

            activity()->withoutLogs(fn () => $courses->each(
                fn (Course $course) => $student->studentCourses()->create(['course_id' => $course->getKey()]),
            ));

            activity()
                ->performedOn($student)
                ->event('updated')
                ->withProperties(['courses_applied' => $courses->count()])
                ->log('courses_applied');

            return $courses->count();
        });
    }

    /**
     * Tambah satu MK, boleh dari program lain (mahasiswa pertukaran).
     */
    public function addCourse(Student $student, Course $course): StudentCourse
    {
        return DB::transaction(function () use ($student, $course): StudentCourse {
            $student = $this->lockedEnrolled($student);

            if (! $course->is_active || $course->trashed()) {
                throw WorkflowException::because('course_unavailable', ['course' => $course->code]);
            }

            if ($student->studentCourses()->where('course_id', $course->getKey())->exists()) {
                throw WorkflowException::because('course_already_added', ['course' => $course->code]);
            }

            return $student->studentCourses()->create(['course_id' => $course->getKey()]);
        });
    }

    /**
     * Keluarkan MK dari rencana studi. Barisnya tetap ada, termasuk nilainya bila sudah terisi.
     */
    public function drop(StudentCourse $row, ?string $note = null): StudentCourse
    {
        return DB::transaction(function () use ($row, $note): StudentCourse {
            $this->lockedEnrolled($row->student);

            if ($row->refresh()->isDropped()) {
                throw WorkflowException::because('course_already_dropped');
            }

            $row->update(['status' => StudentCourseStatus::Dropped, 'notes' => filled($note) ? $note : null]);

            return $row;
        });
    }

    /**
     * Kembalikan MK yang di-drop: Selesai bila sudah ada nilai, selain itu Aktif.
     */
    public function restore(StudentCourse $row): StudentCourse
    {
        return DB::transaction(function () use ($row): StudentCourse {
            $this->lockedEnrolled($row->student);

            if (! $row->refresh()->isDropped()) {
                throw WorkflowException::because('course_not_dropped');
            }

            $row->update([
                'status' => $row->grade !== null ? StudentCourseStatus::Completed : StudentCourseStatus::Active,
                'notes' => null,
            ]);

            return $row;
        });
    }

    /**
     * Baris terbaru mahasiswa, terkunci sampai transaksi selesai.
     */
    private function lockedEnrolled(Student $student): Student
    {
        $student = Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

        if ($student->status !== StudentStatus::Enrolled) {
            throw WorkflowException::transition($student->status, 'manage_courses');
        }

        return $student;
    }
}

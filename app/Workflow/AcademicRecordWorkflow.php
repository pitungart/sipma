<?php

namespace App\Workflow;

use App\Enums\Grade;
use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Notifications\GradesPublished;
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
     * Simpan nilai sekaligus untuk satu mahasiswa; satu notifikasi per simpan. MK yang di-drop atau
     * bukan milik mahasiswa ini dilewati, nilai yang tidak berubah tidak disentuh. Mengembalikan
     * jumlah nilai yang berubah. Siapa yang boleh dan kapan diperiksa StudentPolicy::gradeCourses.
     *
     * @param  array<string, Grade|null>  $grades  ID baris => nilai; null mengosongkan nilai
     */
    public function saveGrades(Student $student, array $grades): int
    {
        return DB::transaction(function () use ($student, $grades): int {
            $student = Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();

            if (! $student->status->hasAcademicAccess()) {
                throw WorkflowException::transition($student->status, 'grade');
            }

            $changed = 0;

            $rows = $student->studentCourses()
                ->whereKey(array_keys($grades))
                ->where('status', '!=', StudentCourseStatus::Dropped)
                ->with('course')
                ->get();

            foreach ($rows as $row) {
                $grade = $grades[$row->getKey()] ?? null;

                if ($row->grade === $grade) {
                    continue;
                }

                $row->update($grade === null ? [
                    'status' => StudentCourseStatus::Active,
                    'grade' => null,
                    'grade_point' => null,
                    'graded_credits' => null,
                    'graded_at' => null,
                    'graded_by' => null,
                ] : [
                    'status' => StudentCourseStatus::Completed,
                    'grade' => $grade,
                    'grade_point' => $grade->point(),
                    'graded_credits' => $row->course?->credits,
                    'graded_at' => now(),
                    'graded_by' => auth()->id(),
                ]);

                $changed++;
            }

            if ($changed > 0) {
                DB::afterCommit(fn () => $student->user?->notify(new GradesPublished($changed)));
            }

            return $changed;
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

<?php

namespace Tests\Feature\Workflow;

use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Support\ActivityDescriber;
use App\Workflow\AcademicRecordWorkflow;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/**
 * TP-3 F2: aturan daftar mata kuliah mahasiswa di AcademicRecordWorkflow.
 */
class AcademicRecordWorkflowTest extends WorkflowTestCase
{
    private function workflow(): AcademicRecordWorkflow
    {
        return app(AcademicRecordWorkflow::class);
    }

    private function packaged(int $active = 2): Program
    {
        $program = $this->program();

        foreach (range(1, $active) as $i) {
            $program->courses()->create(['code' => "MK{$i}", 'name' => "Mata kuliah {$i}", 'credits' => 3]);
        }

        $program->courses()->create(['code' => 'OFF', 'name' => 'Nonaktif', 'is_active' => false]);

        return $program;
    }

    private function enrolled(?Program $program = null): Student
    {
        return $this->student($program ?? $this->packaged(), StudentStatus::Enrolled);
    }

    public function test_apply_package_adds_active_courses_only_and_can_run_again(): void
    {
        $student = $this->enrolled();

        $this->assertSame(2, $this->workflow()->applyPackage($student));
        $this->assertEqualsCanonicalizing(['MK1', 'MK2'], $student->studentCourses()->with('course')->get()->pluck('course.code')->all());
        $this->assertSame(0, $this->workflow()->applyPackage($student));
        $this->assertSame(2, $student->studentCourses()->count());
    }

    public function test_apply_package_picks_up_new_courses_but_not_dropped_ones(): void
    {
        $student = $this->enrolled($program = $this->packaged());
        $this->workflow()->applyPackage($student);

        $this->workflow()->drop($student->studentCourses()->first());
        $program->courses()->create(['code' => 'MK9', 'name' => 'Baru']);

        $this->assertSame(1, $this->workflow()->applyPackage($student));
        $this->assertSame(3, $student->studentCourses()->count());
        $this->assertSame(1, $student->studentCourses()->where('status', StudentCourseStatus::Dropped)->count());
    }

    public function test_enrolling_installs_the_package_and_records_one_history_line(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $student = $this->student($this->packaged(), StudentStatus::LoaIssued);

        app(StudentWorkflow::class)->enroll($student);

        $this->assertSame(2, $student->studentCourses()->count());
        $this->assertSame(0, Activity::query()->where('subject_type', (new StudentCourse)->getMorphClass())->count());

        $texts = ActivityDescriber::forStudent($student)->pluck('text');
        $this->assertContains(__('admin.dashboard.activity_courses_applied', ['causer' => $admin->name, 'name' => $student->full_name, 'count' => 2]), $texts);

        $this->assertContains(__('profile.activity.courses_applied', ['name' => $student->full_name, 'count' => 2]), ActivityDescriber::forCauser($admin)->pluck('text'));
    }

    public function test_enrolling_without_a_package_still_works(): void
    {
        $student = $this->student($this->program(), StudentStatus::LoaIssued);

        app(StudentWorkflow::class)->enroll($student);

        $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
        $this->assertSame(0, $student->studentCourses()->count());
    }

    public function test_add_course_accepts_other_programs_and_rejects_duplicates_and_inactive(): void
    {
        $student = $this->enrolled();
        $other = $this->packaged();
        $course = $other->courses()->where('code', 'MK1')->first();

        $row = $this->workflow()->addCourse($student, $course);
        $this->assertSame(StudentCourseStatus::Active, $row->status);

        $this->assertWorkflowFails('workflow.errors.course_already_added', fn () => $this->workflow()->addCourse($student, $course), ['course' => 'MK1']);

        $off = $other->courses()->where('code', 'OFF')->first();
        $this->assertWorkflowFails('workflow.errors.course_unavailable', fn () => $this->workflow()->addCourse($student, $off), ['course' => 'OFF']);

        $deleted = $other->courses()->where('code', 'MK2')->first();
        $deleted->delete();
        $this->assertWorkflowFails('workflow.errors.course_unavailable', fn () => $this->workflow()->addCourse($student, $deleted), ['course' => 'MK2']);
    }

    public function test_drop_keeps_the_grade_and_restore_returns_the_right_status(): void
    {
        $student = $this->enrolled();
        $this->workflow()->applyPackage($student);
        [$graded, $ungraded] = $student->studentCourses()->get()->all();

        $graded->update(['status' => StudentCourseStatus::Completed, 'grade' => 'A', 'grade_point' => 4]);

        $this->workflow()->drop($graded, 'Bentrok jadwal');
        $graded->refresh();
        $this->assertSame(StudentCourseStatus::Dropped, $graded->status);
        $this->assertSame('A', $graded->grade);
        $this->assertSame('Bentrok jadwal', $graded->notes);

        $this->workflow()->drop($ungraded);
        $this->assertNull($ungraded->fresh()->notes);

        $this->assertWorkflowFails('workflow.errors.course_already_dropped', fn () => $this->workflow()->drop($graded));

        $this->workflow()->restore($graded);
        $this->assertSame(StudentCourseStatus::Completed, $graded->fresh()->status);
        $this->assertNull($graded->fresh()->notes);

        $this->workflow()->restore($ungraded);
        $this->assertSame(StudentCourseStatus::Active, $ungraded->fresh()->status);

        $this->assertWorkflowFails('workflow.errors.course_not_dropped', fn () => $this->workflow()->restore($graded));
    }

    public function test_only_enrolled_students_can_change_and_completed_is_locked(): void
    {
        $student = $this->enrolled();
        $this->workflow()->applyPackage($student);
        $row = $student->studentCourses()->first();
        $course = $this->packaged()->courses()->first();

        $student->update(['status' => StudentStatus::Completed]);

        foreach ([
            fn () => $this->workflow()->applyPackage($student),
            fn () => $this->workflow()->addCourse($student, $course),
            fn () => $this->workflow()->drop($row),
            fn () => $this->workflow()->restore($row),
        ] as $step) {
            try {
                $step();
                $this->fail('Completed student should be locked');
            } catch (WorkflowException) {
                $this->assertSame(2, $student->studentCourses()->count());
            }
        }

        // Dibuka kembali → bisa dikoreksi
        app(StudentWorkflow::class)->reopen($student->fresh());
        $this->assertSame(StudentCourseStatus::Dropped, $this->workflow()->drop($row)->status);
    }

    public function test_deleted_course_stays_on_the_student_and_cannot_be_force_deleted(): void
    {
        $student = $this->enrolled();
        $this->workflow()->applyPackage($student);
        $row = $student->studentCourses()->first();
        $course = Course::find($row->course_id);

        $course->delete();

        $this->assertSame($course->code, $row->fresh()->course->code);

        $this->expectException(QueryException::class);
        $course->forceDelete();
    }

    public function test_history_sentences_for_manual_changes(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $student = $this->enrolled();
        $course = $this->packaged()->courses()->where('code', 'MK1')->first();

        $row = $this->workflow()->addCourse($student, $course);
        $this->workflow()->drop($row);
        $this->workflow()->restore($row);

        $texts = ActivityDescriber::forStudent($student)->pluck('text');
        $replace = ['causer' => $admin->name, 'name' => $student->full_name, 'course' => 'MK1'];

        $this->assertContains(__('admin.dashboard.activity_course_added', $replace), $texts);
        $this->assertContains(__('admin.dashboard.activity_course_dropped', $replace), $texts);
        $this->assertContains(__('admin.dashboard.activity_course_restored', $replace), $texts);
    }

    private function assertWorkflowFails(string $key, callable $step, array $replace = []): void
    {
        try {
            $step();
            $this->fail("Expected {$key}");
        } catch (WorkflowException $e) {
            $this->assertSame(__($key, $replace), $e->getMessage());
        }
    }
}

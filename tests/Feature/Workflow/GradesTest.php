<?php

namespace Tests\Feature\Workflow;

use App\Enums\Grade;
use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\User;
use App\Notifications\GradesPublished;
use App\Support\AcademicSummary;
use App\Support\ActivityDescriber;
use App\Support\NotificationPreferences;
use App\Workflow\AcademicRecordWorkflow;
use App\Workflow\WorkflowException;
use Illuminate\Support\Facades\Notification;

/**
 * TP-3 F3: penyimpanan nilai, salinan SKS, IP, notifikasi, dan riwayat.
 */
class GradesTest extends WorkflowTestCase
{
    private function workflow(): AcademicRecordWorkflow
    {
        return app(AcademicRecordWorkflow::class);
    }

    private function packaged(): Program
    {
        $program = $this->program();
        $program->courses()->create(['code' => 'MK1', 'name' => 'Satu', 'credits' => 3]);
        $program->courses()->create(['code' => 'MK2', 'name' => 'Dua', 'credits' => 2]);
        $program->courses()->create(['code' => 'MK3', 'name' => 'Non SKS', 'credits' => null]);

        return $program;
    }

    private function enrolled(StudentStatus $status = StudentStatus::Enrolled): Student
    {
        $student = $this->student($this->packaged(), StudentStatus::Enrolled);
        $this->workflow()->applyPackage($student);
        $student->update(['status' => $status]);

        return $student->fresh();
    }

    private function row(Student $student, string $code): StudentCourse
    {
        return $student->studentCourses()->with('course')->whereHas('course', fn ($q) => $q->where('code', $code))->first();
    }

    public function test_scale_points_follow_the_provisional_table(): void
    {
        $this->assertSame(
            ['A' => 4.0, 'B+' => 3.5, 'B' => 3.0, 'C+' => 2.5, 'C' => 2.0, 'D' => 1.0, 'E' => 0.0, 'pass' => null, 'fail' => null],
            collect(Grade::cases())->mapWithKeys(fn (Grade $grade): array => [$grade->value => $grade->point()])->all(),
        );
    }

    public function test_saving_a_grade_fills_everything_and_completes_the_course(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $student = $this->enrolled();
        $row = $this->row($student, 'MK1');

        $this->assertSame(1, $this->workflow()->saveGrades($student, [$row->getKey() => Grade::BPlus]));

        $row->refresh();
        $this->assertSame(Grade::BPlus, $row->grade);
        $this->assertEquals(3.5, (float) $row->grade_point);
        $this->assertSame(3, $row->graded_credits);
        $this->assertSame(StudentCourseStatus::Completed, $row->status);
        $this->assertNotNull($row->graded_at);
        $this->assertSame($admin->getKey(), $row->graded_by);
    }

    public function test_clearing_a_grade_returns_the_course_to_in_progress(): void
    {
        $student = $this->enrolled();
        $row = $this->row($student, 'MK1');

        $this->workflow()->saveGrades($student, [$row->getKey() => Grade::A]);
        $this->workflow()->saveGrades($student, [$row->getKey() => null]);

        $row->refresh();
        $this->assertNull($row->grade);
        $this->assertNull($row->grade_point);
        $this->assertNull($row->graded_credits);
        $this->assertNull($row->graded_at);
        $this->assertSame(StudentCourseStatus::Active, $row->status);
    }

    public function test_one_notification_per_save_with_the_number_of_changes(): void
    {
        $student = $this->enrolled();

        $this->workflow()->saveGrades($student, [
            $this->row($student, 'MK1')->getKey() => Grade::A,
            $this->row($student, 'MK2')->getKey() => Grade::B,
        ]);

        Notification::assertSentToTimes($student->user, GradesPublished::class, 1);
        Notification::assertSentTo($student->user, GradesPublished::class, fn (GradesPublished $n): bool => $n->count === 2);
    }

    public function test_unchanged_grades_touch_nothing_and_send_nothing(): void
    {
        $student = $this->enrolled();
        $row = $this->row($student, 'MK1');
        $this->workflow()->saveGrades($student, [$row->getKey() => Grade::A]);
        $gradedAt = $row->fresh()->graded_at;

        Notification::fake();
        $this->travel(1)->days();

        $this->assertSame(0, $this->workflow()->saveGrades($student, [$row->getKey() => Grade::A, $this->row($student, 'MK2')->getKey() => null]));

        Notification::assertNothingSent();
        $this->assertTrue($gradedAt->equalTo($row->fresh()->graded_at));
    }

    public function test_dropped_and_foreign_rows_are_skipped(): void
    {
        $student = $this->enrolled();
        $other = $this->enrolled();
        $dropped = $this->row($student, 'MK1');
        $this->workflow()->drop($dropped);
        $foreign = $this->row($other, 'MK1');

        $this->assertSame(0, $this->workflow()->saveGrades($student, [$dropped->getKey() => Grade::A, $foreign->getKey() => Grade::A]));

        $this->assertNull($dropped->fresh()->grade);
        $this->assertNull($foreign->fresh()->grade);
    }

    public function test_grades_need_academic_access(): void
    {
        $student = $this->enrolled(StudentStatus::LoaIssued);

        $this->expectException(WorkflowException::class);

        $this->workflow()->saveGrades($student, [$this->row($student, 'MK1')->getKey() => Grade::A]);
    }

    public function test_completed_student_can_be_corrected_at_the_workflow_level(): void
    {
        $student = $this->enrolled(StudentStatus::Completed);
        $row = $this->row($student, 'MK1');

        $this->assertSame(1, $this->workflow()->saveGrades($student, [$row->getKey() => Grade::C]));
    }

    public function test_gpa_is_weighted_and_skips_what_it_should(): void
    {
        $student = $this->enrolled();
        $this->workflow()->saveGrades($student, [
            $this->row($student, 'MK1')->getKey() => Grade::A,  // 4,00 × 3
            $this->row($student, 'MK2')->getKey() => Grade::C,  // 2,00 × 2
            $this->row($student, 'MK3')->getKey() => Grade::Pass, // tanpa bobot & SKS
        ]);

        $summary = AcademicSummary::from($student->studentCourses()->with('course')->get());

        $this->assertSame(3.2, $summary->gpa);
        $this->assertSame(5, $summary->credits);
        $this->assertSame(3, $summary->completed);
        $this->assertSame(3, $summary->total);
    }

    public function test_gpa_ignores_dropped_courses_and_is_empty_without_weighted_grades(): void
    {
        $student = $this->enrolled();
        $summary = fn (): AcademicSummary => AcademicSummary::from($student->studentCourses()->with('course')->get());

        $this->assertNull($summary()->gpa);
        $this->assertSame('—', $summary()->gpaLabel());

        $this->workflow()->saveGrades($student, [$this->row($student, 'MK3')->getKey() => Grade::Pass]);
        $this->assertNull($summary()->gpa);

        $this->workflow()->saveGrades($student, [
            $this->row($student, 'MK1')->getKey() => Grade::A,
            $this->row($student, 'MK2')->getKey() => Grade::E,
        ]);
        $this->assertSame(2.4, $summary()->gpa);

        $this->workflow()->drop($this->row($student, 'MK2'));
        $this->assertSame(4.0, $summary()->gpa);
        $this->assertSame(3, $summary()->credits);
        $this->assertSame(2, $summary()->total);
    }

    public function test_changing_credits_in_the_package_leaves_graded_courses_alone(): void
    {
        $student = $this->enrolled();
        $graded = $this->row($student, 'MK1');
        $ungraded = $this->row($student, 'MK2');
        $this->workflow()->saveGrades($student, [$graded->getKey() => Grade::A]);

        $graded->course->update(['credits' => 6]);
        $ungraded->course->update(['credits' => 4]);

        $this->assertSame(3, $graded->fresh()->effectiveCredits());
        $this->assertSame(4, $ungraded->fresh()->effectiveCredits());
        $this->assertSame(4.0, AcademicSummary::from($student->studentCourses()->with('course')->get())->gpa);
        $this->assertSame(7, AcademicSummary::from($student->studentCourses()->with('course')->get())->credits);
    }

    public function test_history_says_from_what_to_what(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $student = $this->enrolled();
        $row = $this->row($student, 'MK1');

        $this->workflow()->saveGrades($student, [$row->getKey() => Grade::B]);
        $this->workflow()->saveGrades($student, [$row->getKey() => Grade::A]);

        $texts = ActivityDescriber::forStudent($student)->pluck('text');
        $replace = ['causer' => $admin->name, 'name' => $student->full_name, 'course' => 'MK1'];

        $this->assertContains(__('admin.dashboard.activity_course_graded', [...$replace, 'old' => '—', 'new' => 'B']), $texts);
        $this->assertContains(__('admin.dashboard.activity_course_graded', [...$replace, 'old' => 'B', 'new' => 'A']), $texts);
    }

    public function test_grade_policy_by_role_and_status(): void
    {
        $student = $this->enrolled();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]);

        $this->assertTrue($admin->can('gradeCourses', $student));
        $this->assertFalse($student->user->can('gradeCourses', $student));
        $this->assertFalse($this->agent()->user->can('gradeCourses', $student));

        $student->update(['status' => StudentStatus::Completed]);

        $this->assertFalse($admin->can('gradeCourses', $student->fresh()));
        $this->assertTrue($this->superAdmin()->can('gradeCourses', $student->fresh()));
    }

    public function test_grade_preference_belongs_to_students(): void
    {
        $this->assertContains('grades', NotificationPreferences::keysFor($this->studentUser()));
        $this->assertNotContains('grades', NotificationPreferences::keysFor($this->agent()->user));
    }
}

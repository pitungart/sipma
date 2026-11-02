<?php

namespace Tests\Feature\Admin;

use App\Enums\Grade;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ViewAcademicStudent;
use App\Filament\Admin\Resources\CourseResource;
use App\Filament\Admin\Resources\CourseResource\Pages\GradeSheet;
use App\Filament\Admin\Resources\CourseResource\Pages\ManageCourses;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\User;
use App\Notifications\GradesPublished;
use App\Workflow\AcademicRecordWorkflow;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-3 F3: isi nilai dari tab Rubrik dan lembar nilai per MK.
 */
class GradeEntryTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function packaged(): Program
    {
        $program = $this->program();
        $program->courses()->create(['code' => 'MK1', 'name' => 'Budaya Bali', 'credits' => 3]);
        $program->courses()->create(['code' => 'MK2', 'name' => 'Tari Bali', 'credits' => 2]);

        return $program;
    }

    private function enrolled(?Program $program = null, StudentStatus $status = StudentStatus::Enrolled): Student
    {
        $student = $this->student($program ?? $this->packaged(), StudentStatus::Enrolled);
        app(AcademicRecordWorkflow::class)->applyPackage($student);
        $student->update(['status' => $status]);

        return $student->fresh();
    }

    private function row(Student $student, string $code): StudentCourse
    {
        return $student->studentCourses()->whereHas('course', fn ($q) => $q->where('code', $code))->first();
    }

    private function facultyAdmin(Program $program): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $program->faculty_id]);
    }

    private function otherFacultyProgram(): Program
    {
        $program = $this->packaged();
        $program->update(['faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);

        return $program;
    }

    public function test_grades_are_saved_at_once_from_the_rubric_tab(): void
    {
        $student = $this->enrolled();
        $one = $this->row($student, 'MK1');
        $two = $this->row($student, 'MK2');

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertActionVisible('gradeCourses')
            ->callAction('gradeCourses', ['grades' => [$one->getKey() => 'A', $two->getKey() => 'B+']])
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.student_course.success.graded', ['count' => 2]))
            ->assertSee(__('workflow.rubric.gpa'));

        $this->assertSame(Grade::A, $one->fresh()->grade);
        $this->assertSame(Grade::BPlus, $two->fresh()->grade);

        Notification::assertSentToTimes($student->user, GradesPublished::class, 1);
    }

    public function test_tab_form_rejects_values_outside_the_scale_and_keeps_existing_grades(): void
    {
        $student = $this->enrolled();
        $row = $this->row($student, 'MK1');
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$row->getKey() => Grade::A]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('gradeCourses', ['grades' => [$row->getKey() => 'Z']])
            ->assertHasActionErrors();

        $this->assertSame(Grade::A, $row->fresh()->grade);
    }

    public function test_tab_form_can_clear_a_grade(): void
    {
        $student = $this->enrolled();
        $row = $this->row($student, 'MK1');
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$row->getKey() => Grade::A]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('gradeCourses', ['grades' => [$row->getKey() => null]])
            ->assertHasNoActionErrors();

        $this->assertNull($row->fresh()->grade);
    }

    public function test_completed_student_is_corrected_by_super_admin_only(): void
    {
        $program = $this->packaged();
        $student = $this->enrolled($program, StudentStatus::Completed);

        $this->actingAs($this->facultyAdmin($program));
        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertActionHidden('gradeCourses')
            ->assertDontSeeText(__('admin.student_course.actions.grade'));

        $this->actingAs($this->superAdmin());
        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertActionVisible('gradeCourses')
            ->assertSeeText(__('admin.student_course.actions.grade'))
            ->assertDontSeeText(__('admin.student_course.actions.apply'))
            ->assertSee(__('workflow.rubric.not_graded'));
    }

    public function test_grade_action_is_hidden_without_courses_or_before_active(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->student($this->program(), StudentStatus::Enrolled)->getKey()])
            ->assertActionHidden('gradeCourses');

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->student($this->packaged(), StudentStatus::LoaIssued)->getKey()])
            ->assertActionHidden('gradeCourses');
    }

    public function test_marking_completed_warns_about_ungraded_courses(): void
    {
        $student = $this->enrolled();
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$this->row($student, 'MK1')->getKey() => Grade::A]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->mountAction('complete')
            ->assertSee(__('admin.academic_student.confirm.complete_ungraded', ['count' => 1]));

        // Peringatan saja, tidak memblokir
        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('complete');

        $this->assertSame(StudentStatus::Completed, $student->fresh()->status);
    }

    public function test_no_warning_when_everything_is_graded(): void
    {
        $student = $this->enrolled();
        app(AcademicRecordWorkflow::class)->saveGrades($student, [
            $this->row($student, 'MK1')->getKey() => Grade::A,
            $this->row($student, 'MK2')->getKey() => Grade::A,
        ]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->mountAction('complete')
            ->assertDontSee(trans('admin.academic_student.confirm.complete_ungraded', ['count' => 0]));
    }

    public function test_sheet_lists_active_students_and_saves_one_notification_each(): void
    {
        $program = $this->packaged();
        $course = $program->courses()->where('code', 'MK1')->first();
        $first = $this->enrolled($program);
        $second = $this->enrolled($program);
        $first->update(['full_name' => 'Ayu Pertama']);
        $second->update(['full_name' => 'Budi Kedua']);

        $dropped = $this->enrolled($program);
        $dropped->update(['full_name' => 'Citra Keluar']);
        app(AcademicRecordWorkflow::class)->drop($this->row($dropped, 'MK1'));

        $done = $this->enrolled($program, StudentStatus::Completed);
        $done->update(['full_name' => 'Dewi Selesai']);

        $this->actingAs($this->superAdmin());

        $sheet = Livewire::test(GradeSheet::class, ['record' => $course->getKey()])
            ->assertSee('Ayu Pertama')
            ->assertSee('Budi Kedua')
            ->assertDontSee('Citra Keluar')
            ->assertDontSee('Dewi Selesai');

        $sheet->set('data.grades.'.$this->row($first, 'MK1')->getKey(), 'A')
            ->set('data.grades.'.$this->row($second, 'MK1')->getKey(), 'C')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.student_course.success.graded', ['count' => 2]));

        $this->assertSame(Grade::A, $this->row($first, 'MK1')->grade);
        $this->assertSame(Grade::C, $this->row($second, 'MK1')->grade);
        Notification::assertSentToTimes($first->user, GradesPublished::class, 1);
        Notification::assertSentToTimes($second->user, GradesPublished::class, 1);
    }

    public function test_sheet_loads_existing_grades_and_rejects_bad_values(): void
    {
        $program = $this->packaged();
        $course = $program->courses()->where('code', 'MK1')->first();
        $student = $this->enrolled($program);
        $row = $this->row($student, 'MK1');
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$row->getKey() => Grade::B]);

        $this->actingAs($this->superAdmin());

        Livewire::test(GradeSheet::class, ['record' => $course->getKey()])
            ->assertSet('data.grades.'.$row->getKey(), 'B')
            ->set('data.grades.'.$row->getKey(), 'Z')
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(Grade::B, $row->fresh()->grade);
    }

    public function test_sheet_saves_nothing_when_nothing_changed(): void
    {
        $program = $this->packaged();
        $course = $program->courses()->where('code', 'MK1')->first();
        $this->enrolled($program);

        $this->actingAs($this->superAdmin());

        Livewire::test(GradeSheet::class, ['record' => $course->getKey()])
            ->call('save')
            ->assertNotified(__('admin.student_course.success.no_grade_change'));

        Notification::assertNothingSent();
    }

    public function test_sheet_shows_only_students_of_the_admins_faculty(): void
    {
        $program = $this->packaged();
        $course = $program->courses()->where('code', 'MK1')->first();
        $own = $this->enrolled($program);
        $own->update(['full_name' => 'Mahasiswa Sendiri']);

        $foreign = $this->student($this->otherFacultyProgram(), StudentStatus::Enrolled);
        $foreign->update(['full_name' => 'Mahasiswa Lain']);
        app(AcademicRecordWorkflow::class)->addCourse($foreign, $course);

        $this->actingAs($this->facultyAdmin($program));

        Livewire::test(GradeSheet::class, ['record' => $course->getKey()])
            ->assertSee('Mahasiswa Sendiri')
            ->assertDontSee('Mahasiswa Lain')
            ->call('save');

        $this->assertNull($foreign->studentCourses()->first()->grade);

        $this->actingAs($this->superAdmin());

        Livewire::test(GradeSheet::class, ['record' => $course->getKey()])
            ->assertSee('Mahasiswa Lain');
    }

    public function test_sheet_is_closed_to_other_faculties_and_roles(): void
    {
        $course = $this->otherFacultyProgram()->courses()->first();

        $this->actingAs($this->facultyAdmin($this->packaged()));
        $this->get(CourseResource::getUrl('grades', ['record' => $course]))->assertNotFound();

        $this->actingAs($this->studentUser());
        $this->get(CourseResource::getUrl('grades', ['record' => $course]))->assertForbidden();

        $this->actingAs($this->agent()->user);
        $this->get(CourseResource::getUrl('grades', ['record' => $course]))->assertForbidden();
    }

    public function test_sheet_is_reachable_from_the_course_list_and_handles_empty(): void
    {
        $course = $this->packaged()->courses()->first();

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->assertTableActionVisible('grades', $course)
            ->assertTableActionHasUrl('grades', CourseResource::getUrl('grades', ['record' => $course]), $course);

        $this->get(CourseResource::getUrl('grades', ['record' => $course]))
            ->assertOk()
            ->assertSee(__('admin.student_course.sheet.empty'));

        $course->delete();
        $this->get(CourseResource::getUrl('grades', ['record' => Course::withTrashed()->find($course->getKey())]))->assertForbidden();
    }
}

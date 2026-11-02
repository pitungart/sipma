<?php

namespace Tests\Feature\Admin;

use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ListAcademicStudents;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ViewAcademicStudent;
use App\Filament\Admin\Resources\CourseResource\Pages\ManageCourses;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Workflow\AcademicRecordWorkflow;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-3 F2: tab Rubrik di detail Mahasiswa Asing dan kolom jumlah MK.
 */
class StudentRubricTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function packaged(): Program
    {
        $program = $this->program();
        $program->courses()->create(['code' => 'MK1', 'name' => 'Budaya Bali', 'credits' => 3, 'semester' => 1]);
        $program->courses()->create(['code' => 'MK2', 'name' => 'Tari Bali', 'credits' => 2, 'semester' => 2]);

        return $program;
    }

    private function enrolled(?Program $program = null, StudentStatus $status = StudentStatus::Enrolled): Student
    {
        return $this->student($program ?? $this->packaged(), $status);
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

    public function test_apply_add_drop_and_restore_from_the_tab(): void
    {
        $student = $this->enrolled();
        $extra = $this->packaged()->courses()->first();

        $this->actingAs($this->superAdmin());

        $page = Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertSee(__('admin.student_course.empty'))
            ->callAction('applyPackage')
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.student_course.success.applied', ['count' => 2]))
            ->assertSee('Budaya Bali')
            ->callAction('addCourse', ['course_id' => $extra->getKey()])
            ->assertHasNoActionErrors();

        $this->assertSame(3, $student->studentCourses()->count());

        $row = $student->studentCourses()->where('course_id', $student->program->courses()->where('code', 'MK1')->value('id'))->first();

        $page->callAction('dropCourse', ['note' => 'Bentrok jadwal'], ['row' => $row->getKey()])
            ->assertHasNoActionErrors()
            ->assertSee('Bentrok jadwal');

        $this->assertSame(StudentCourseStatus::Dropped, $row->fresh()->status);

        $page->callAction('restoreCourse', [], ['row' => $row->getKey()])->assertHasNoActionErrors();

        $this->assertSame(StudentCourseStatus::Active, $row->fresh()->status);
    }

    public function test_total_credits_leave_out_dropped_courses(): void
    {
        $student = $this->enrolled();
        app(AcademicRecordWorkflow::class)->applyPackage($student);
        $row = $student->studentCourses()->whereHas('course', fn ($q) => $q->where('code', 'MK1'))->first();

        $this->actingAs($this->superAdmin());

        $total = fn (): string => Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])->html();

        $this->assertMatchesRegularExpression('/'.preg_quote(__('workflow.rubric.total'), '/').'.*?<dd>\s*5\s*</s', $total());

        app(AcademicRecordWorkflow::class)->drop($row);

        $this->assertMatchesRegularExpression('/'.preg_quote(__('workflow.rubric.total'), '/').'.*?<dd>\s*2\s*</s', $total());
    }

    public function test_row_argument_cannot_reach_another_students_course(): void
    {
        $student = $this->enrolled();
        $other = $this->enrolled();
        app(AcademicRecordWorkflow::class)->applyPackage($other);
        $foreign = $other->studentCourses()->first();

        $this->actingAs($this->superAdmin());

        try {
            Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
                ->call('mountAction', 'dropCourse', ['row' => $foreign->getKey()]);
            $this->fail('Another student\'s course should not be found');
        } catch (ModelNotFoundException) {
            $this->assertSame(StudentCourseStatus::Active, $foreign->fresh()->status);
        }
    }

    public function test_course_list_explains_itself_outside_the_active_stage(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->enrolled(status: StudentStatus::LoaIssued)->getKey()])
            ->assertSee(__('admin.student_course.empty_not_active'))
            ->assertActionHidden('applyPackage')
            ->assertActionHidden('addCourse');

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->enrolled($this->program())->getKey()])
            ->assertSee(__('admin.student_course.empty_active'));

        $completed = $this->enrolled();
        app(AcademicRecordWorkflow::class)->applyPackage($completed);
        $completed->update(['status' => StudentStatus::Completed]);

        Livewire::test(ViewAcademicStudent::class, ['record' => $completed->getKey()])
            ->assertSee('Budaya Bali')
            ->assertSee(__('admin.student_course.locked'))
            ->assertDontSeeText(__('admin.student_course.actions.apply'))
            ->assertDontSeeText(__('admin.student_course.actions.add'))
            ->assertActionHidden('dropCourse')
            ->assertActionHidden('restoreCourse');
    }

    public function test_faculty_admin_manages_own_faculty_and_picks_only_its_courses(): void
    {
        $student = $this->enrolled($program = $this->packaged());
        $sameFaculty = $this->packaged()->courses()->first();
        $foreignCourse = $this->otherFacultyProgram()->courses()->first();

        $this->actingAs($this->facultyAdmin($program));

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('addCourse', ['course_id' => $foreignCourse->getKey()])
            ->assertHasActionErrors(['course_id']);

        $this->assertSame(0, $student->studentCourses()->count());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('addCourse', ['course_id' => $sameFaculty->getKey()])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $student->studentCourses()->count());
    }

    public function test_faculty_admin_cannot_open_or_change_another_faculty(): void
    {
        $program = $this->packaged();
        $foreign = $this->enrolled($this->otherFacultyProgram());
        $admin = $this->facultyAdmin($program);

        $this->assertFalse($admin->can('manageCourses', $foreign));

        $this->actingAs($admin);
        $this->get(route('filament.admin.resources.international-students.view', ['record' => $foreign]))->assertNotFound();
    }

    public function test_agents_and_students_cannot_manage_courses(): void
    {
        $student = $this->enrolled();

        $this->assertFalse($student->user->can('manageCourses', $student));

        $agent = $this->agent();
        $student->update(['agent_id' => $agent->id]);
        $this->assertFalse($agent->user->can('manageCourses', $student->fresh()));
    }

    public function test_list_counts_courses_without_dropped_ones(): void
    {
        $student = $this->enrolled();
        app(AcademicRecordWorkflow::class)->applyPackage($student);
        app(AcademicRecordWorkflow::class)->drop($student->studentCourses()->first());
        $empty = $this->enrolled($this->program());

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertTableColumnStateSet('courses_taken', 1, $student->getKey())
            ->assertTableColumnStateSet('courses_taken', 0, $empty->getKey());
    }

    public function test_course_list_counts_students_without_dropped_ones(): void
    {
        $program = $this->packaged();
        $course = $program->courses()->first();

        foreach ([$this->enrolled($program), $this->enrolled($program)] as $student) {
            app(AcademicRecordWorkflow::class)->addCourse($student, $course);
        }

        app(AcademicRecordWorkflow::class)->drop($course->studentCourses()->first());

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->assertTableColumnStateSet('students_taking', 1, $course->getKey());
    }

    public function test_deleted_course_still_shows_on_the_tab(): void
    {
        $student = $this->enrolled();
        app(AcademicRecordWorkflow::class)->applyPackage($student);
        $student->program->courses()->where('code', 'MK1')->first()->delete();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])->assertSee('Budaya Bali');
    }
}

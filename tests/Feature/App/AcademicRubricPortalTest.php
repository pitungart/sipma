<?php

namespace Tests\Feature\App;

use App\Enums\Grade;
use App\Enums\StudentStatus;
use App\Filament\App\Pages\AcademicRubric;
use App\Filament\App\Pages\Dashboard;
use App\Models\Student;
use App\Notifications\GradesPublished;
use App\Workflow\AcademicRecordWorkflow;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-3 F3: halaman Rubrik akademik di portal mahasiswa.
 */
class AcademicRubricPortalTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    private function enrolled(StudentStatus $status = StudentStatus::Enrolled, bool $package = true): Student
    {
        $program = $this->program();

        if ($package) {
            $program->courses()->create(['code' => 'MK1', 'name' => 'Budaya Bali', 'name_en' => 'Balinese Culture', 'credits' => 3]);
            $program->courses()->create(['code' => 'MK2', 'name' => 'Tari Bali', 'credits' => 2]);
        }

        $student = $this->student($program, StudentStatus::Enrolled);
        app(AcademicRecordWorkflow::class)->applyPackage($student);
        $student->update(['status' => $status]);

        return $student->fresh();
    }

    public function test_page_is_only_for_active_or_completed_students(): void
    {
        $student = $this->enrolled(StudentStatus::LoaIssued);

        $this->actingAs($student->user);
        $this->get(AcademicRubric::getUrl(panel: 'app'))->assertForbidden();
        $this->assertFalse(AcademicRubric::shouldRegisterNavigation());

        $student->update(['status' => StudentStatus::Enrolled]);
        $this->get(AcademicRubric::getUrl(panel: 'app'))->assertOk();

        $student->update(['status' => StudentStatus::Completed]);
        $this->get(AcademicRubric::getUrl(panel: 'app'))->assertOk();
        $this->assertTrue(AcademicRubric::shouldRegisterNavigation());
    }

    public function test_page_shows_grades_summary_and_follows_the_language(): void
    {
        $student = $this->enrolled();
        $rows = $student->studentCourses()->with('course')->get()->keyBy('course.code');
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$rows['MK1']->getKey() => Grade::A]);

        $this->actingAs($student->user);

        app()->setLocale('en');

        Livewire::test(AcademicRubric::class)
            ->assertSee('Balinese Culture')
            ->assertSee('Tari Bali')
            ->assertSee(__('workflow.rubric.gpa'))
            ->assertSee('4.00')
            ->assertSee('1/2');
    }

    public function test_student_sees_only_their_own_courses(): void
    {
        $mine = $this->enrolled();
        $other = $this->student($this->program(), StudentStatus::Enrolled);
        $other->program->courses()->create(['code' => 'XX9', 'name' => 'Rahasia Orang Lain']);
        app(AcademicRecordWorkflow::class)->applyPackage($other);

        $this->actingAs($mine->user);

        Livewire::test(AcademicRubric::class)->assertSee('MK1')->assertDontSee('XX9');
    }

    public function test_dropped_grade_is_hidden_and_not_graded_label_appears_after_completion(): void
    {
        $student = $this->enrolled();
        $rows = $student->studentCourses()->with('course')->get()->keyBy('course.code');
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$rows['MK1']->getKey() => Grade::BPlus]);
        app(AcademicRecordWorkflow::class)->drop($rows['MK1']->fresh());

        $this->actingAs($student->user);

        Livewire::test(AcademicRubric::class)->assertDontSee('B+')->assertDontSee(__('workflow.rubric.not_graded'));

        $student->update(['status' => StudentStatus::Completed]);

        Livewire::test(AcademicRubric::class)->assertSee(__('workflow.rubric.not_graded'));
    }

    public function test_empty_state_and_dashboard_summary(): void
    {
        $student = $this->enrolled(package: false);

        $this->actingAs($student->user);

        Livewire::test(AcademicRubric::class)->assertSee(__('student.rubric.empty_title'));
        Livewire::test(Dashboard::class)->assertSee(__('student.rubric.empty_title'));
    }

    public function test_dashboard_shows_progress_and_gpa(): void
    {
        $student = $this->enrolled();
        $row = $student->studentCourses()->first();
        app(AcademicRecordWorkflow::class)->saveGrades($student, [$row->getKey() => Grade::A]);

        $this->actingAs($student->user);

        Livewire::test(Dashboard::class)
            ->assertSee(__('student.rubric.progress', ['completed' => 1, 'total' => 2]))
            ->assertSee(__('workflow.rubric.gpa'));
    }

    public function test_notification_opens_the_rubric_page(): void
    {
        $student = $this->enrolled();

        $this->assertStringContainsString(
            AcademicRubric::getUrl(panel: 'app'),
            json_encode((new GradesPublished(1))->toDatabase($student->user), JSON_UNESCAPED_SLASHES),
        );
    }
}

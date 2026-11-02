<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\CourseResource;
use App\Filament\Admin\Resources\CourseResource\Pages\ManageCourses;
use App\Filament\Admin\Resources\FacultyResource;
use App\Filament\Admin\Resources\ProgramResource\Pages\ManagePrograms;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-3 F1: paket mata kuliah per program.
 */
class CoursesTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function course(Program $program, string $code = 'MK101', array $attributes = []): Course
    {
        return $program->courses()->create([...['code' => $code, 'name' => "Mata kuliah {$code}", 'credits' => 3], ...$attributes]);
    }

    private function otherFacultyProgram(): Program
    {
        $program = $this->program();
        $program->update(['faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);

        return $program;
    }

    private function facultyAdmin(Program $program): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $program->faculty_id]);
    }

    public function test_kui_creates_a_course_with_a_normalised_code(): void
    {
        $program = $this->program();

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->callAction('create', [
                'program_id' => $program->getKey(),
                'code' => ' bipa101 ',
                'name' => 'Bahasa Indonesia',
                'name_en' => 'Indonesian',
                'credits' => 3,
                'semester' => 1,
            ])
            ->assertHasNoActionErrors();

        $course = $program->courses()->sole();
        $this->assertSame('BIPA101', $course->code);
        $this->assertSame(3, $course->credits);
        $this->assertTrue($course->is_active);
    }

    public function test_credits_and_semester_are_optional_for_non_credit_programs(): void
    {
        $program = $this->program();

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $program->getKey(), 'code' => 'BIPA', 'name' => 'BIPA'])
            ->assertHasNoActionErrors();

        $this->assertNull($program->courses()->sole()->credits);
    }

    public function test_credits_and_semester_are_range_checked(): void
    {
        $program = $this->program();

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $program->getKey(), 'code' => 'A', 'name' => 'A', 'credits' => 11, 'semester' => 15])
            ->assertHasActionErrors(['credits', 'semester']);

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $program->getKey(), 'code' => 'A', 'name' => 'A', 'credits' => 0])
            ->assertHasActionErrors(['credits']);
    }

    public function test_code_is_unique_per_program_only(): void
    {
        $program = $this->program();
        $this->course($program, 'MK101');
        $other = $this->program();

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $program->getKey(), 'code' => 'MK101', 'name' => 'Duplikat'])
            ->assertHasActionErrors(['code' => 'unique']);

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $other->getKey(), 'code' => 'MK101', 'name' => 'Program lain'])
            ->assertHasNoActionErrors();

        $this->expectException(QueryException::class);
        $this->course($program, 'MK101');
    }

    public function test_deleted_course_keeps_its_code_until_restored(): void
    {
        $program = $this->program();
        $course = $this->course($program);

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->callTableAction(DeleteAction::class, $course);

        $this->assertSoftDeleted($course);

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $program->getKey(), 'code' => 'MK101', 'name' => 'Sama'])
            ->assertHasActionErrors(['code' => 'unique']);

        Livewire::test(ManageCourses::class)
            ->filterTable(TrashedFilter::class, true)
            ->assertCanSeeTableRecords([$course])
            ->callTableAction(RestoreAction::class, $course->fresh());

        $this->assertNotSoftDeleted($course);
    }

    public function test_faculty_admin_works_only_inside_their_faculty(): void
    {
        $own = $this->program();
        $ownCourse = $this->course($own);
        $other = $this->otherFacultyProgram();
        $otherCourse = $this->course($other);

        $this->actingAs($admin = $this->facultyAdmin($own));

        Livewire::test(ManageCourses::class)
            ->assertCanSeeTableRecords([$ownCourse])
            ->assertCanNotSeeTableRecords([$otherCourse])
            ->assertTableFilterHidden('faculty')
            ->assertTableActionVisible(EditAction::class, $ownCourse);

        // Program fakultas lain ditolak walau ID-nya dikirim paksa
        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $other->getKey(), 'code' => 'X1', 'name' => 'Selundupan'])
            ->assertHasActionErrors(['program_id']);

        $this->assertSame(1, $other->courses()->count());

        Livewire::test(ManageCourses::class)
            ->callAction('create', ['program_id' => $own->getKey(), 'code' => 'X1', 'name' => 'Boleh'])
            ->assertHasNoActionErrors();

        // Tidak bisa memindahkan MK ke fakultas lain
        Livewire::test(ManageCourses::class)
            ->callTableAction(EditAction::class, $ownCourse, ['program_id' => $other->getKey()])
            ->assertHasTableActionErrors(['program_id']);

        $this->assertTrue($admin->can('update', $ownCourse));
        $this->assertFalse($admin->can('update', $otherCourse));
        $this->assertFalse($admin->can('delete', $otherCourse));
        $this->assertFalse($admin->can('forceDelete', $ownCourse));
    }

    public function test_agents_and_students_cannot_manage_courses(): void
    {
        $course = $this->course($this->program());

        foreach ([$this->agent()->user, $this->studentUser()] as $user) {
            foreach (['viewAny', 'create'] as $ability) {
                $this->assertFalse($user->can($ability, Course::class));
            }
            foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
                $this->assertFalse($user->can($ability, $course));
            }

            $this->actingAs($user)->get(CourseResource::getUrl())->assertForbidden();
        }
    }

    public function test_package_order_is_semester_then_code_with_empty_semester_last(): void
    {
        $program = $this->program();
        $this->course($program, 'C', ['semester' => null]);
        $this->course($program, 'B', ['semester' => 2]);
        $this->course($program, 'Z', ['semester' => 1]);
        $this->course($program, 'A', ['semester' => 1]);

        $this->assertSame(['A', 'Z', 'B', 'C'], $program->courses()->packageOrder()->pluck('code')->all());
    }

    public function test_program_filter_shows_summary_of_active_courses(): void
    {
        $program = $this->program();
        $this->course($program, 'A', ['credits' => 3]);
        $this->course($program, 'B', ['credits' => null]);
        $inactive = $this->course($program, 'C', ['credits' => 4, 'is_active' => false]);
        $other = $this->course($this->program(), 'D');

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->assertDontSee(trans_choice('admin.course.summary_courses', 2, ['count' => 2]))
            ->filterTable('program_id', $program->getKey())
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$other])
            ->assertSee(trans_choice('admin.course.summary_courses', 2, ['count' => 2]))
            ->assertSee(trans_choice('admin.course.summary_credits', 3, ['count' => 3]));
    }

    public function test_program_list_links_to_its_package_and_prefills_the_form(): void
    {
        $program = $this->program();
        $this->course($program, 'A');
        $this->course($program, 'B', ['is_active' => false]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ManagePrograms::class)
            ->assertTableColumnStateSet('courses_count', 1, $program->getKey());

        $this->get(CourseResource::urlForProgram($program))->assertOk();

        Livewire::withQueryParams(['tableFilters' => ['program_id' => ['value' => $program->getKey()]]])
            ->test(ManageCourses::class)
            ->mountAction('create')
            ->assertSet('mountedActionsData.0.program_id', $program->getKey());
    }

    public function test_course_package_stays_out_of_master_data_tabs(): void
    {
        $this->actingAs($this->superAdmin());

        $labels = FacultyResource::masterTabs()->pluck('label');

        $this->assertNotContains(CourseResource::getPluralModelLabel(), $labels);
        $this->assertContains(FacultyResource::getPluralModelLabel(), $labels);
        $this->assertSame(__('admin.groups.academic'), CourseResource::getNavigationGroup());
    }

    public function test_localized_name_falls_back_to_the_official_name(): void
    {
        $withEnglish = $this->course($this->program(), 'A', ['name' => 'Budaya', 'name_en' => 'Culture']);
        $official = $this->course($this->program(), 'B', ['name' => 'Tari', 'name_en' => null]);

        app()->setLocale('en');
        $this->assertSame('Culture', $withEnglish->localizedName());
        $this->assertSame('Tari', $official->localizedName());

        app()->setLocale('id');
        $this->assertSame('Budaya', $withEnglish->localizedName());
    }
}

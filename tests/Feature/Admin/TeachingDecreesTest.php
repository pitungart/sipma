<?php

namespace Tests\Feature\Admin;

use App\Enums\DecreePeriod;
use App\Enums\TeachingRole;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\TeachingDecreeResource;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages\CreateTeachingDecree;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages\EditTeachingDecree;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages\ListTeachingDecrees;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\TeachingDecree;
use App\Models\User;
use App\Support\ActivityDescriber;
use App\Support\UploadLimits;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-4 F2: SK Mengajar, penugasannya, dan berkas PDF.
 */
class TeachingDecreesTest extends WorkflowTestCase
{
    private Faculty $fib;

    private Faculty $fk;

    private Program $fibProgram;

    private Course $fibCourse;

    private Course $fkCourse;

    private Lecturer $fibLecturer;

    private Lecturer $fkLecturer;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Tanpa UUID acak, baris repeater Filament bisa diuji dengan kunci yang pasti
        Repeater::fake();

        $this->fibProgram = $this->program();
        $this->fib = $this->fibProgram->faculty;
        $this->fk = Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran']);

        $fkProgram = $this->program();
        $fkProgram->update(['faculty_id' => $this->fk->id]);

        $this->fibCourse = $this->fibProgram->courses()->create(['code' => 'BUD1', 'name' => 'Budaya Bali']);
        $this->fkCourse = $fkProgram->courses()->create(['code' => 'MED1', 'name' => 'Klinik']);

        $this->fibLecturer = Lecturer::create(['faculty_id' => $this->fib->id, 'name' => 'Dosen FIB']);
        $this->fkLecturer = Lecturer::create(['faculty_id' => $this->fk->id, 'name' => 'Dosen FK']);
    }

    private function facultyAdmin(Faculty $faculty): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return [
            'decree_number' => 'SK/001/2026',
            'decree_date' => '2026-10-01',
            'faculty_id' => $this->fib->id,
            'period_start' => '2026-11-01',
            'period_end' => '2027-04-30',
            'assignments' => [[
                'lecturer_id' => $this->fibLecturer->id,
                'course_id' => $this->fibCourse->id,
                'role' => TeachingRole::Coordinator->value,
            ]],
            ...$overrides,
        ];
    }

    private function decree(?Faculty $faculty = null, array $attributes = []): TeachingDecree
    {
        $decree = TeachingDecree::create([
            'faculty_id' => ($faculty ?? $this->fib)->id,
            'decree_number' => 'SK/'.uniqid(),
            'decree_date' => '2026-10-01',
            'period_start' => '2026-01-01',
            'period_end' => '2030-12-31',
            ...$attributes,
        ]);

        if (($faculty ?? $this->fib)->is($this->fib)) {
            $decree->assignments()->create(['lecturer_id' => $this->fibLecturer->id, 'course_id' => $this->fibCourse->id, 'role' => TeachingRole::Lecturer]);
        }

        return $decree;
    }

    public function test_kui_creates_a_decree_with_assignments_and_a_private_pdf(): void
    {
        $this->actingAs($admin = $this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm([...$this->formData(), 'document_file' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf')])
            ->call('create')
            ->assertHasNoFormErrors();

        $decree = TeachingDecree::query()->sole();
        $this->assertSame('SK/001/2026', $decree->decree_number);
        $this->assertSame($admin->getKey(), $decree->created_by);
        $this->assertSame(TeachingRole::Coordinator, $decree->assignments()->sole()->role);
        $this->assertStringStartsWith("teaching-decrees/{$decree->getKey()}/", $decree->document);
        Storage::disk('local')->assertExists($decree->document);
    }

    public function test_pdf_is_optional_and_list_marks_the_missing_file(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasNoFormErrors();

        $decree = TeachingDecree::query()->sole();
        $this->assertNull($decree->document);

        Livewire::test(ListTeachingDecrees::class)
            ->assertTableColumnStateSet('document', __('admin.teaching_decree.document_state.no'), $decree->getKey())
            ->assertTableActionHidden('download', $decree);
    }

    public function test_required_fields_and_period_order(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['decree_number' => '', 'decree_date' => null, 'period_start' => null, 'period_end' => null]))
            ->call('create')
            ->assertHasFormErrors(['decree_number' => 'required', 'decree_date' => 'required', 'period_start' => 'required', 'period_end' => 'required']);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['period_start' => '2027-01-01', 'period_end' => '2026-01-01']))
            ->call('create')
            ->assertHasFormErrors(['period_end']);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['assignments' => []]))
            ->call('create')
            ->assertHasFormErrors(['assignments']);

        $this->assertSame(0, TeachingDecree::query()->count());
    }

    public function test_only_pdf_within_the_limit_is_accepted(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm([...$this->formData(), 'document_file' => UploadedFile::fake()->image('sk.png')])
            ->call('create')
            ->assertHasFormErrors(['document_file']);

        UploadLimits::save([UploadLimits::TEACHING_DECREE => 200]);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm([...$this->formData(), 'document_file' => UploadedFile::fake()->create('sk.pdf', 300, 'application/pdf')])
            ->call('create')
            ->assertHasFormErrors(['document_file']);

        $this->assertSame(0, TeachingDecree::query()->count());
    }

    public function test_number_is_unique_per_faculty_and_stays_taken_while_deleted(): void
    {
        $deleted = $this->decree(attributes: ['decree_number' => 'SK/SAMA']);

        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['decree_number' => 'SK/SAMA']))
            ->call('create')
            ->assertHasFormErrors(['decree_number' => 'unique']);

        $deleted->delete();

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['decree_number' => 'SK/SAMA']))
            ->call('create')
            ->assertHasFormErrors(['decree_number' => 'unique']);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData([
                'decree_number' => 'SK/SAMA',
                'faculty_id' => $this->fk->id,
                'assignments' => [['lecturer_id' => $this->fkLecturer->id, 'course_id' => $this->fkCourse->id, 'role' => 'lecturer']],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_course_must_belong_to_the_decree_faculty(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['assignments' => [['lecturer_id' => $this->fibLecturer->id, 'course_id' => $this->fkCourse->id, 'role' => 'lecturer']]]))
            ->call('create')
            ->assertHasFormErrors(['assignments.0.course_id']);

        $this->assertSame(0, TeachingDecree::query()->count());
    }

    public function test_super_admin_may_assign_a_lecturer_from_another_faculty(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['assignments' => [['lecturer_id' => $this->fkLecturer->id, 'course_id' => $this->fibCourse->id, 'role' => 'lecturer']]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(TeachingDecree::query()->sole()->assignments()->sole()->lecturer->is($this->fkLecturer));
    }

    public function test_faculty_admin_picks_only_their_own_lecturers_and_faculty(): void
    {
        $this->actingAs($this->facultyAdmin($this->fib));

        Livewire::test(CreateTeachingDecree::class)
            ->assertFormSet(['faculty_id' => $this->fib->id])
            ->fillForm($this->formData(['assignments' => [['lecturer_id' => $this->fkLecturer->id, 'course_id' => $this->fibCourse->id, 'role' => 'lecturer']]]))
            ->call('create')
            ->assertHasFormErrors(['assignments.0.lecturer_id']);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['faculty_id' => $this->fk->id, 'assignments' => [['lecturer_id' => $this->fibLecturer->id, 'course_id' => $this->fkCourse->id, 'role' => 'lecturer']]]))
            ->call('create')
            ->assertHasFormErrors(['faculty_id']);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, TeachingDecree::query()->count());
    }

    public function test_duplicate_assignment_is_rejected_but_two_lecturers_on_one_course_are_fine(): void
    {
        $this->actingAs($this->superAdmin());

        $row = ['lecturer_id' => $this->fibLecturer->id, 'course_id' => $this->fibCourse->id, 'role' => 'lecturer'];

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['assignments' => [$row, [...$row, 'role' => 'assistant']]]))
            ->call('create')
            ->assertHasFormErrors(['assignments']);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData(['assignments' => [$row, [...$row, 'lecturer_id' => $this->fkLecturer->id, 'role' => 'assistant']]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, TeachingDecree::query()->sole()->assignments()->count());
    }

    public function test_inactive_lecturers_and_courses_are_not_offered_for_new_rows(): void
    {
        $this->fibLecturer->update(['is_active' => false]);
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasFormErrors(['assignments.0.lecturer_id']);

        $this->fibLecturer->update(['is_active' => true]);
        $this->fibCourse->update(['is_active' => false]);

        Livewire::test(CreateTeachingDecree::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasFormErrors(['assignments.0.course_id']);
    }

    public function test_old_decree_keeps_inactive_and_deleted_lecturer_and_course_when_saved(): void
    {
        $decree = $this->decree();
        $this->fibLecturer->update(['is_active' => false]);
        $this->fibCourse->delete();

        $this->actingAs($this->superAdmin());

        Livewire::test(EditTeachingDecree::class, ['record' => $decree->getKey()])
            ->fillForm(['notes' => 'Diperbarui'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Diperbarui', $decree->fresh()->notes);
        $row = $decree->assignments()->sole();
        $this->assertSame('Dosen FIB', $row->lecturer->name);
        $this->assertSame('BUD1', $row->course->code);
    }

    public function test_changing_the_faculty_does_not_keep_courses_of_the_old_faculty(): void
    {
        $decree = $this->decree();
        $row = $decree->assignments()->sole();

        $this->actingAs($this->superAdmin());

        // Baris lama dikirim ulang dengan MK fakultas sebelumnya
        Livewire::test(EditTeachingDecree::class, ['record' => $decree->getKey()])
            ->set('data.faculty_id', $this->fk->id)
            ->set('data.assignments', ['record-'.$row->getKey() => [
                'lecturer_id' => $row->lecturer_id,
                'course_id' => $this->fibCourse->id,
                'role' => 'lecturer',
            ]])
            ->call('save')
            ->assertHasFormErrors(['assignments.record-'.$row->getKey().'.course_id']);

        $this->assertSame($this->fib->id, $decree->fresh()->faculty_id);
    }

    public function test_summary_counts_distinct_lecturers_and_courses(): void
    {
        $decree = $this->decree();
        $decree->assignments()->create(['lecturer_id' => $this->fkLecturer->id, 'course_id' => $this->fibCourse->id, 'role' => TeachingRole::Assistant]);

        app()->setLocale('en');

        $this->assertSame('2 lecturers · 1 course', TeachingDecreeResource::assignmentSummary($decree->fresh()));
    }

    public function test_replacing_the_pdf_removes_the_old_file(): void
    {
        $this->actingAs($this->superAdmin());

        $old = 'teaching-decrees/lama/old.pdf';
        Storage::disk('local')->put($old, '%PDF');
        $decree = $this->decree(attributes: ['document' => $old]);

        Livewire::test(EditTeachingDecree::class, ['record' => $decree->getKey()])
            ->assertSee(__('admin.teaching_decree.open_document'))
            ->fillForm(['document_file' => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame($old, $decree->fresh()->document);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($decree->fresh()->document);
    }

    public function test_saving_without_a_new_file_keeps_the_existing_one(): void
    {
        $this->actingAs($this->superAdmin());

        $path = 'teaching-decrees/tetap/sk.pdf';
        Storage::disk('local')->put($path, '%PDF');
        $decree = $this->decree(attributes: ['document' => $path]);

        Livewire::test(EditTeachingDecree::class, ['record' => $decree->getKey()])
            ->fillForm(['notes' => 'Catatan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($path, $decree->fresh()->document);
        Storage::disk('local')->assertExists($path);
    }

    public function test_file_access_follows_faculty_and_role(): void
    {
        $path = 'teaching-decrees/akses/sk.pdf';
        Storage::disk('local')->put($path, '%PDF');
        $decree = $this->decree(attributes: ['document' => $path]);
        $url = route('files.teaching-decree', ['decree' => $decree, 'download' => 1]);

        $this->actingAs($this->superAdmin())->get($url)->assertOk();
        $this->actingAs($this->facultyAdmin($this->fib))->get($url)->assertOk();
        $this->actingAs($this->facultyAdmin($this->fk))->get($url)->assertForbidden();
        $this->actingAs($this->agent()->user)->get($url)->assertForbidden();
        $this->actingAs($this->studentUser())->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect();
    }

    public function test_deleting_keeps_rows_and_file_and_can_be_restored(): void
    {
        $path = 'teaching-decrees/hapus/sk.pdf';
        Storage::disk('local')->put($path, '%PDF');
        $decree = $this->decree(attributes: ['document' => $path]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListTeachingDecrees::class)->callTableAction(DeleteAction::class, $decree);

        $this->assertSoftDeleted($decree);
        $this->assertSame(1, $decree->assignments()->count());
        Storage::disk('local')->assertExists($path);
        $this->get(route('files.teaching-decree', ['decree' => $decree, 'download' => 1]))->assertOk();
        $this->get(TeachingDecreeResource::getUrl('edit', ['record' => $decree]))->assertForbidden();

        Livewire::test(ListTeachingDecrees::class)
            ->filterTable(TrashedFilter::class, true)
            ->assertCanSeeTableRecords([$decree])
            ->callTableAction(RestoreAction::class, $decree->fresh());

        $this->assertNotSoftDeleted($decree);
    }

    public function test_lecturer_and_course_on_a_decree_cannot_be_force_deleted(): void
    {
        $this->decree();

        try {
            $this->fibLecturer->forceDelete();
            $this->fail('Lecturer on a decree should be protected');
        } catch (QueryException) {
            $this->assertNotNull($this->fibLecturer->fresh());
        }

        $this->expectException(QueryException::class);
        $this->fibCourse->forceDelete();
    }

    public function test_list_scope_filters_and_summary(): void
    {
        $own = $this->decree(attributes: ['decree_number' => 'SK/SENDIRI']);
        $other = $this->decree($this->fk, ['decree_number' => 'SK/LAIN']);

        $this->actingAs($this->facultyAdmin($this->fib));

        Livewire::test(ListTeachingDecrees::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableFilterHidden('faculty_id')
            ->assertTableColumnStateSet('assignment_summary', TeachingDecreeResource::assignmentSummary($own), $own->getKey());

        $this->get(TeachingDecreeResource::getUrl('edit', ['record' => $other]))->assertNotFound();

        $this->actingAs($this->superAdmin());

        Livewire::test(ListTeachingDecrees::class)
            ->assertCanSeeTableRecords([$own, $other])
            ->filterTable('course', $this->fibCourse->id)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ListTeachingDecrees::class)
            ->filterTable('lecturer', $this->fkLecturer->id)
            ->assertCanNotSeeTableRecords([$own, $other]);
    }

    public function test_period_status_and_filter_follow_today(): void
    {
        $current = $this->decree(attributes: ['period_start' => today()->subDay(), 'period_end' => today()]);
        $upcoming = $this->decree(attributes: ['period_start' => today()->addDay(), 'period_end' => today()->addMonth()]);
        $ended = $this->decree(attributes: ['period_start' => today()->subMonth(), 'period_end' => today()->subDay()]);

        $this->assertSame(DecreePeriod::Current, $current->period());
        $this->assertSame(DecreePeriod::Upcoming, $upcoming->period());
        $this->assertSame(DecreePeriod::Ended, $ended->period());

        $this->actingAs($this->superAdmin());

        Livewire::test(ListTeachingDecrees::class)
            ->filterTable('period', DecreePeriod::Ended->value)
            ->assertCanSeeTableRecords([$ended])
            ->assertCanNotSeeTableRecords([$current, $upcoming]);
    }

    public function test_policy_refuses_other_roles_and_force_delete(): void
    {
        $decree = $this->decree();
        $fibAdmin = $this->facultyAdmin($this->fib);
        $fkAdmin = $this->facultyAdmin($this->fk);

        $this->assertTrue($fibAdmin->can('update', $decree));
        $this->assertFalse($fibAdmin->can('forceDelete', $decree));

        foreach (['view', 'update', 'delete', 'restore'] as $ability) {
            $this->assertFalse($fkAdmin->can($ability, $decree));
        }

        foreach ([$this->agent()->user, $this->studentUser()] as $user) {
            $this->assertFalse($user->can('viewAny', TeachingDecree::class));
            $this->assertFalse($user->can('create', TeachingDecree::class));
            foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
                $this->assertFalse($user->can($ability, $decree));
            }
            $this->actingAs($user)->get(TeachingDecreeResource::getUrl())->assertForbidden();
        }
    }

    public function test_each_save_writes_one_activity_line_even_after_deletion(): void
    {
        $this->actingAs($admin = $this->superAdmin());

        Livewire::test(CreateTeachingDecree::class)->fillForm($this->formData())->call('create')->assertHasNoFormErrors();
        $decree = TeachingDecree::query()->sole();

        Livewire::test(EditTeachingDecree::class, ['record' => $decree->getKey()])->fillForm(['notes' => 'x'])->call('save');
        Livewire::test(ListTeachingDecrees::class)->callTableAction(DeleteAction::class, $decree);

        $texts = ActivityDescriber::forCauser($admin)->pluck('text');

        $this->assertCount(3, $texts);
        $this->assertContains(__('profile.activity.decree_created', ['number' => 'SK/001/2026']), $texts);
        $this->assertContains(__('profile.activity.decree_updated', ['number' => 'SK/001/2026']), $texts);
        $this->assertContains(__('profile.activity.decree_deleted', ['number' => 'SK/001/2026']), $texts);
    }

    public function test_upload_setting_has_its_own_limit(): void
    {
        $this->assertSame(5120, UploadLimits::teachingDecree());

        UploadLimits::save([UploadLimits::TEACHING_DECREE => 2048]);

        $this->assertSame(2048, UploadLimits::teachingDecree());
        $this->assertSame(5120, UploadLimits::mou());
    }
}

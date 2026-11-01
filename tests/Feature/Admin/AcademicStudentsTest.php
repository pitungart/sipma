<?php

namespace Tests\Feature\Admin;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ListAcademicStudents;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Admin\Resources\StudentResource\Pages\ListApplicants;
use App\Filament\Admin\Widgets\StatCards;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-1 F1: daftar Mahasiswa Asing (LOA terbit · Aktif · Selesai), terpisah dari Pendaftar.
 */
class AcademicStudentsTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, Student>
     */
    private function oneOfEach(): array
    {
        $program = $this->program();

        return collect(StudentStatus::cases())
            ->mapWithKeys(fn (StudentStatus $status): array => [$status->value => $this->student($program, $status)])
            ->all();
    }

    public function test_list_holds_accepted_students_with_post_loa_tabs(): void
    {
        $students = $this->oneOfEach();
        $accepted = collect(StudentStatus::acceptedStatuses())->map(fn (StudentStatus $s): Student => $students[$s->value]);
        $admissions = collect($students)->reject(fn (Student $s): bool => $s->status->isAccepted());

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertCanSeeTableRecords($accepted)
            ->assertCanNotSeeTableRecords($admissions)
            ->set('activeTab', StudentStatus::LoaIssued->value)
            ->assertCanSeeTableRecords([$students['loa_issued']])
            ->assertCanNotSeeTableRecords([$students['enrolled'], $students['completed']])
            ->set('activeTab', StudentStatus::Enrolled->value)
            ->assertCanSeeTableRecords([$students['enrolled']])
            ->assertCanNotSeeTableRecords([$students['loa_issued'], $students['completed']]);

        $this->get(AcademicStudentResource::getUrl())
            ->assertOk()
            ->assertSee(__('admin.groups.academic'))
            ->assertSee(__('admin.academic_student.tabs.ready'))
            ->assertSee(StudentStatus::Enrolled->getLabel())
            ->assertSee(StudentStatus::Completed->getLabel());

        $this->assertSame('1', AcademicStudentResource::getNavigationBadge(), 'one student waits to be activated');
    }

    public function test_applicants_list_stops_at_loa_issued(): void
    {
        $students = $this->oneOfEach();
        $this->actingAs($this->superAdmin());

        Livewire::test(ListApplicants::class)
            ->assertCanSeeTableRecords([$students['loa_issued'], $students['draft']])
            ->assertCanNotSeeTableRecords([$students['enrolled'], $students['completed']])
            ->assertSee(__('admin.applicant.tabs.all'));

        $tabs = Livewire::test(ListApplicants::class)->instance()->getTabs();
        $this->assertSame(count(StudentStatus::cases()) - 2, $tabs['all']->getBadge());

        // Detail pendaftarannya tetap bisa dibuka
        $this->get(StudentResource::getUrl('view', ['record' => $students['enrolled']]))->assertOk();
    }

    public function test_faculty_admin_sees_only_own_faculty_and_no_faculty_filter(): void
    {
        $own = $this->student($this->program(), StudentStatus::Enrolled);
        $otherFaculty = Faculty::create(['code' => 'FT', 'name' => 'Fakultas Teknik']);
        $other = $this->student($this->program(), StudentStatus::Enrolled);
        $other->program->update(['faculty_id' => $otherFaculty->id]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $own->program->faculty_id]);
        $this->actingAs($admin);

        $this->assertTrue(AcademicStudentResource::canViewAny());
        Livewire::test(ListAcademicStudents::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableFilterHidden('faculty')
            ->assertTableFilterVisible('program_id');

        $this->actingAs($this->superAdmin());
        Livewire::test(ListAcademicStudents::class)
            ->assertTableFilterVisible('faculty')
            ->filterTable('faculty', $otherFaculty->id)
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$own]);
    }

    public function test_portal_account_column_shows_who_cannot_sign_in_yet(): void
    {
        $withAccount = $this->student(status: StudentStatus::Enrolled);
        $pending = $this->student(status: StudentStatus::LoaIssued);
        $pending->update(['user_id' => null]);
        $failed = $this->student(status: StudentStatus::Enrolled);
        $failed->update(['user_id' => null]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertTableColumnStateSet('account', __('admin.academic_student.account.yes'), $withAccount)
            ->assertTableColumnStateSet('account', __('admin.academic_student.account.no'), $pending)
            ->assertTableColumnStateSet('account', __('admin.academic_student.account.no'), $failed);
    }

    public function test_loa_card_counts_every_accepted_student_and_opens_this_list(): void
    {
        $this->oneOfEach();
        $this->actingAs($this->superAdmin());

        Livewire::test(StatCards::class)
            ->assertViewHas('cards', fn (array $cards): bool => collect($cards)->contains(fn (array $card): bool => $card['label'] === __('admin.dashboard.loa_issued')
                && $card['value'] === 3
                && $card['url'] === AcademicStudentResource::getUrl('index')));
    }

    public function test_other_roles_stay_out(): void
    {
        $this->student(status: StudentStatus::Enrolled);

        $this->actingAs($this->agent()->user)->get(AcademicStudentResource::getUrl())->assertForbidden();
        $this->actingAs($this->studentUser())->get(AcademicStudentResource::getUrl())->assertForbidden();
    }
}

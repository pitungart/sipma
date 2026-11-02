<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\NumberType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\NumberingSettings;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ListAcademicStudents;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ViewAcademicStudent;
use App\Filament\Admin\Resources\ProgramResource\Pages\ManagePrograms;
use App\Filament\Admin\Resources\StudentCardResource;
use App\Filament\Admin\Resources\StudentCardResource\Pages\ListStudentCards;
use App\Models\Faculty;
use App\Models\NumberFormat;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Support\ActivityDescriber;
use App\Support\NotificationPreferences;
use App\Workflow\StudentCardWorkflow;
use Database\Seeders\NumberFormatSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-2 F1: menu KTM digital, tab KTM di detail Mahasiswa Asing, dan hak aksesnya.
 */
class StudentCardsTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function enrolled(?Program $program = null, StudentStatus $status = StudentStatus::Enrolled): Student
    {
        $student = $this->student($program, $status);

        $student->documents()->create([
            'type' => DocumentType::Photo,
            'file_path' => 'photo.png',
            'original_name' => 'photo.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
            'status' => DocumentStatus::Approved,
        ]);

        return $student;
    }

    private function issued(?Student $student = null): StudentCard
    {
        return app(StudentCardWorkflow::class)->issue($student ?? $this->enrolled(), now()->addYear());
    }

    private function facultyAdmin(Faculty $faculty): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }

    public function test_kui_issues_a_card_from_the_student_detail(): void
    {
        $student = $this->enrolled();
        $date = now()->addYear()->toDateString();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertSee(__('admin.student_card.detail.none'))
            ->assertSeeText(__('admin.student_card.actions.issue'))
            ->assertDontSeeText(__('admin.student_card.actions.reissue'))
            ->assertDontSeeText(__('admin.student_card.actions.revoke'))
            ->assertActionVisible('issueCard')
            ->assertActionHidden('reissueCard')
            ->assertActionHidden('revokeCard')
            ->callAction('issueCard', ['valid_until' => $date])
            ->assertHasNoActionErrors()
            ->assertNotified()
            ->assertActionHidden('issueCard')
            ->assertActionVisible('reissueCard')
            ->assertActionVisible('revokeCard')
            ->assertDontSeeText(__('admin.student_card.actions.issue'))
            ->assertSeeText(__('admin.student_card.actions.reissue'))
            ->assertSeeText(__('admin.student_card.actions.revoke'))
            ->assertSee($student->activeCard()->first()->card_number);

        $this->assertSame($date, $student->activeCard()->first()->valid_until->toDateString());
    }

    public function test_validity_field_is_filled_from_the_program_duration(): void
    {
        $program = $this->program();
        $program->update(['duration_months' => 6]);
        $student = $this->enrolled($program);
        $student->update(['enrollment_month' => 11, 'enrollment_year' => now()->year + 1]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->mountAction('issueCard')
            ->assertSet('mountedActionsData.0.valid_until', fn (string $date): bool => str_starts_with($date, (now()->year + 2).'-04-30'));
    }

    public function test_picking_a_student_fills_the_validity_from_their_program(): void
    {
        $program = $this->program();
        $program->update(['duration_months' => 6]);
        $student = $this->enrolled($program);
        $student->update(['enrollment_month' => 11, 'enrollment_year' => now()->year + 1]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListStudentCards::class)
            ->mountAction('issue')
            ->assertSee(__('admin.student_card.hints.valid_pick'))
            ->setActionData(['student_id' => $student->getKey()])
            ->assertSet('mountedActionsData.0.valid_until', fn (string $date): bool => str_starts_with($date, (now()->year + 2).'-04-30'));
    }

    public function test_validity_and_duplicate_numbers_are_checked_in_the_form(): void
    {
        $taken = $this->issued();
        $student = $this->enrolled();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('issueCard', ['valid_until' => null])
            ->assertHasActionErrors(['valid_until' => 'required'])
            ->callAction('issueCard', ['valid_until' => today()->toDateString()])
            ->assertHasActionErrors(['valid_until'])
            ->callAction('issueCard', ['valid_until' => now()->addYear()->toDateString(), 'card_number' => $taken->card_number])
            ->assertHasActionErrors(['card_number' => 'unique']);

        $this->assertSame(0, $student->studentCards()->count());
    }

    public function test_reissue_and_revoke_from_the_detail_keep_history(): void
    {
        $card = $this->issued();
        $student = $card->student;

        $this->actingAs($this->superAdmin());

        $page = Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('reissueCard', ['reason' => 'Kartu hilang', 'valid_until' => now()->addYears(2)->toDateString()])
            ->assertHasNoActionErrors();

        $new = $student->activeCard()->first();
        $this->assertTrue($new->replaces->is($card));

        $page->assertSee(__('admin.student_card.detail.history'))
            ->assertSee($card->card_number)
            ->assertSee('Kartu hilang')
            ->assertSee($new->card_number)
            ->callAction('revokeCard', ['reason' => 'Data keliru'])
            ->assertHasNoActionErrors()
            ->assertSee(__('admin.student_card.detail.none'))
            ->assertSee('Data keliru');

        $this->assertNull($student->activeCard()->first());
        $this->assertSame(StudentCardStatus::Revoked, $new->fresh()->status);
    }

    public function test_reason_is_required_to_reissue_or_revoke(): void
    {
        $card = $this->issued();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $card->student_id])
            ->callAction('revokeCard', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        Livewire::test(ViewAcademicStudent::class, ['record' => $card->student_id])
            ->callAction('reissueCard', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertTrue($card->fresh()->isActive());
    }

    public function test_card_tab_explains_when_a_card_cannot_be_issued(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->enrolled(status: StudentStatus::LoaIssued)->getKey()])
            ->assertActionHidden('issueCard')
            ->assertSee(__('admin.student_card.detail.not_ready'));

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->enrolled(status: StudentStatus::Completed)->getKey()])
            ->assertActionHidden('issueCard')
            ->assertSee(__('admin.student_card.detail.none_completed'));
    }

    public function test_completed_student_card_can_be_revoked_but_not_reissued(): void
    {
        $card = $this->issued();
        $card->student->update(['status' => StudentStatus::Completed]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $card->student_id])
            ->assertActionHidden('reissueCard')
            ->assertActionVisible('revokeCard');
    }

    public function test_warning_appears_when_the_student_has_no_portal_account(): void
    {
        $student = $this->enrolled();
        $student->update(['user_id' => null, 'agent_id' => $this->agent()->id]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->mountAction('issueCard')
            ->assertSee(__('admin.student_card.confirm.no_account'));

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->callAction('issueCard', ['valid_until' => now()->addYear()->toDateString()])
            ->assertHasNoActionErrors()
            ->assertNotified(
                Notification::make()
                    ->warning()
                    ->title(__('admin.student_card.success.issued', ['number' => 'KTMA-'.now()->year.'-0001']))
                    ->body(__('admin.student_card.success.no_account')),
            );

        $this->assertNotNull($student->activeCard()->first());
    }

    public function test_card_menu_lists_cards_with_status_tabs_and_issues_for_a_picked_student(): void
    {
        $active = $this->issued();
        $revoked = $this->issued();
        app(StudentCardWorkflow::class)->revoke($revoked, 'Keliru');
        $waiting = $this->enrolled();
        $waiting->update(['full_name' => 'Waiting Student']);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListStudentCards::class)
            ->assertCanSeeTableRecords([$active, $revoked])
            ->set('activeTab', StudentCardStatus::Active->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$revoked])
            ->searchTable($active->card_number)
            ->assertCanSeeTableRecords([$active]);

        Livewire::test(ListStudentCards::class)
            ->mountAction('issue')
            ->setActionData(['student_id' => $waiting->getKey(), 'valid_until' => now()->addYear()->toDateString()])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertNotNull($waiting->activeCard()->first());

        Livewire::test(ListStudentCards::class)
            ->callTableAction('revoke', $active, ['reason' => 'Dicabut dari daftar'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('reissue', $waiting->activeCard()->first(), ['reason' => 'Rusak', 'valid_until' => now()->addYear()->toDateString()])
            ->assertHasNoTableActionErrors();

        $this->assertSame(StudentCardStatus::Revoked, $active->fresh()->status);
        $this->assertSame(2, $waiting->studentCards()->count());
    }

    public function test_student_list_shows_who_has_an_active_card(): void
    {
        $withCard = $this->issued()->student;
        $waiting = $this->enrolled();

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertTableColumnStateSet('card', __('admin.student_card.yes'), $withCard)
            ->assertTableColumnStateSet('card', __('admin.student_card.no'), $waiting);
    }

    public function test_faculty_admin_reads_only_their_own_faculty(): void
    {
        $ownCard = $this->issued();
        $faculty = $ownCard->student->program->faculty;

        $otherFaculty = Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran']);
        $otherProgram = $this->program();
        $otherProgram->update(['faculty_id' => $otherFaculty->id]);
        $otherCard = $this->issued($this->enrolled($otherProgram));

        $this->actingAs($admin = $this->facultyAdmin($faculty));

        Livewire::test(ListStudentCards::class)
            ->assertCanSeeTableRecords([$ownCard])
            ->assertCanNotSeeTableRecords([$otherCard])
            ->assertActionHidden('issue')
            ->assertTableActionVisible('download', $ownCard)
            ->assertTableActionHidden('reissue', $ownCard)
            ->assertTableActionHidden('revoke', $ownCard);

        Livewire::test(ViewAcademicStudent::class, ['record' => $ownCard->student_id])
            ->assertActionHidden('issueCard')
            ->assertActionHidden('reissueCard')
            ->assertActionHidden('revokeCard')
            ->assertDontSeeText(__('admin.student_card.actions.reissue'))
            ->assertDontSeeText(__('admin.student_card.actions.revoke'))
            ->assertSee(__('admin.student_card.detail.readonly'));

        $this->assertTrue($admin->can('view', $ownCard));
        $this->assertFalse($admin->can('view', $otherCard));
        $this->assertFalse($admin->can('create', StudentCard::class));
        $this->assertFalse($admin->can('reissue', $ownCard));
        $this->assertFalse($admin->can('revoke', $ownCard));
        $this->assertFalse($admin->can('update', $ownCard));
        $this->assertFalse($admin->can('delete', $ownCard));
        $this->assertFalse($admin->can('forceDelete', $ownCard));
    }

    public function test_other_roles_stay_out_and_student_only_sees_their_own_after_active(): void
    {
        $card = $this->issued();
        $owner = $card->student->user;

        $this->assertTrue($owner->can('view', $card));
        $this->assertFalse($owner->can('create', StudentCard::class));
        $this->assertFalse($owner->can('revoke', $card));
        $this->assertFalse($this->studentUser()->can('view', $card));

        $agent = $this->agent();
        $card->student->update(['agent_id' => $agent->id]);
        $this->assertFalse($agent->user->can('view', $card->fresh()));
        $this->assertFalse($agent->user->can('viewAny', StudentCard::class));

        $card->student->update(['status' => StudentStatus::LoaIssued]);
        $this->assertFalse($owner->can('view', $card->fresh()));

        $this->actingAs($this->studentUser());
        $this->get(StudentCardResource::getUrl())->assertForbidden();
    }

    public function test_number_type_is_seeded_and_appears_in_the_numbering_settings(): void
    {
        $this->seed(NumberFormatSeeder::class);

        $format = NumberFormat::query()->where('type', NumberType::StudentCard)->first();

        $this->assertNotNull($format);

        $this->actingAs($this->superAdmin());

        Livewire::test(NumberingSettings::class)->assertSee(__('numbering.types.student_card.label'));
    }

    public function test_program_form_saves_the_duration(): void
    {
        $program = $this->program();

        $this->actingAs($this->superAdmin());

        Livewire::test(ManagePrograms::class)
            ->callTableAction('edit', $program, ['duration_months' => 6])
            ->assertHasNoTableActionErrors();

        $this->assertSame(6, $program->fresh()->duration_months);

        Livewire::test(ManagePrograms::class)
            ->callTableAction('edit', $program, ['duration_months' => 0])
            ->assertHasTableActionErrors(['duration_months']);
    }

    public function test_history_and_preferences_know_about_cards(): void
    {
        $this->actingAs($admin = $this->superAdmin());

        $card = $this->issued();
        app(StudentCardWorkflow::class)->revoke($card, 'Keliru');

        $name = $card->student->full_name;
        $texts = ActivityDescriber::forStudent($card->student)->pluck('text');

        $this->assertContains(__('admin.dashboard.activity_card_issued', ['causer' => $admin->name, 'name' => $name]), $texts);
        $this->assertContains(__('admin.dashboard.activity_card_revoked', ['causer' => $admin->name, 'name' => $name]), $texts);

        $own = ActivityDescriber::forCauser($admin)->pluck('text');
        $this->assertContains(__('profile.activity.card_issued', ['name' => $name]), $own);

        $this->assertContains('student_card', NotificationPreferences::keysFor($card->student->user));
        $this->assertNotContains('student_card', NotificationPreferences::keysFor($this->agent()->user));
    }

    public function test_row_opens_the_card_tab_of_the_student(): void
    {
        $card = $this->issued();

        $this->actingAs($this->superAdmin());

        $url = AcademicStudentResource::getUrl('view', ['record' => $card->student_id, 'tab' => 'card']);

        $this->get($url)->assertOk();
    }
}

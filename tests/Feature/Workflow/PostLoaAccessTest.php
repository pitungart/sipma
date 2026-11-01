<?php

namespace Tests\Feature\Workflow;

use App\Enums\AccountProvision;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\App\Concerns\RequiresAcademicAccess;
use App\Models\Agent;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAccountInvitation;
use App\Notifications\StudentEnrolled;
use App\Support\StudentAccounts;
use App\Workflow\StudentWorkflow;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * TP-0 F2: grup menu Akademik, hak menandai Aktif/Selesai, gerbang halaman akademik portal,
 * dan akun portal + undangan untuk mahasiswa yang didaftarkan agen.
 */
class PostLoaAccessTest extends WorkflowTestCase
{
    private function viaAgent(Agent $agent, StudentStatus $status = StudentStatus::LoaIssued, ?string $email = 'lena.k@example.test'): Student
    {
        $student = $this->student(status: $status);
        $student->user()->first()->delete();
        $student->update(['user_id' => null, 'agent_id' => $agent->id, 'email' => $email]);

        return $student->fresh();
    }

    public function test_academic_group_sits_between_admissions_and_master_data(): void
    {
        $labels = collect(Filament::getPanel('admin')->getNavigationGroups())
            ->map(fn (NavigationGroup $group): string => $group->getLabel())
            ->values()->all();

        $position = array_search(__('admin.groups.academic'), $labels, true);
        $this->assertNotFalse($position);
        $this->assertSame(__('admin.groups.admissions'), $labels[$position - 1]);
        $this->assertSame(__('admin.groups.master_data'), $labels[$position + 1]);
    }

    public function test_only_kui_and_the_faculty_admin_mark_students_active(): void
    {
        $student = $this->student(status: StudentStatus::LoaIssued);
        $own = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]);
        $other = User::factory()->create([
            'role' => UserRole::Admin,
            'faculty_id' => Faculty::create(['code' => 'FT', 'name' => 'Fakultas Teknik'])->id,
        ]);

        $this->assertTrue($this->superAdmin()->can('enroll', $student));
        $this->assertTrue($this->superAdmin()->can('reopen', $student));

        $this->assertTrue($own->can('enroll', $student));
        $this->assertTrue($own->can('complete', $student));
        $this->assertFalse($own->can('reopen', $student), 'reopening is for KUI only');

        $this->assertFalse($other->can('enroll', $student));
        $this->assertFalse($student->user->can('enroll', $student));
        $this->assertFalse($this->agent()->user->can('enroll', $student));
    }

    public function test_academic_pages_open_only_after_the_student_is_active(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $page = new class extends Page
        {
            use RequiresAcademicAccess;
        };

        $owner = $this->studentUser();
        $student = $this->student(status: StudentStatus::LoaIssued, owner: $owner);
        $this->actingAs($owner);

        $this->assertFalse($page::canAccess());
        $this->assertFalse($page::shouldRegisterNavigation());

        foreach ([StudentStatus::Enrolled, StudentStatus::Completed] as $status) {
            $student->update(['status' => $status]);
            $this->assertTrue($page::canAccess(), $status->value);
        }

        $this->actingAs($this->studentUser()); // belum punya pendaftaran
        $this->assertFalse($page::canAccess());
    }

    public function test_agent_registered_student_gets_a_portal_account_and_invitation(): void
    {
        $agent = $this->agent();
        $student = $this->viaAgent($agent, email: 'Lena.K@Example.test ');
        $this->actingAs($this->superAdmin());

        $this->assertSame(AccountProvision::Created, app(StudentWorkflow::class)->enroll($student));

        $user = $student->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('lena.k@example.test', $user->email);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($student->fresh()->isOwnedBy($agent->user), 'the agent keeps the data');
        $this->assertSame(StudentStatus::Enrolled, $user->student->status);

        Notification::assertSentTo($user, StudentAccountInvitation::class, fn ($n, array $channels): bool => $channels === ['mail']);
        Notification::assertNotSentTo($user, StudentEnrolled::class);
        Notification::assertSentTo($agent->user, StudentEnrolled::class);
    }

    public function test_invitation_link_opens_the_reset_page_once_and_expires(): void
    {
        $student = $this->viaAgent($this->agent());
        $this->actingAs($this->superAdmin());
        app(StudentWorkflow::class)->enroll($student);
        auth()->logout();

        $user = $student->fresh()->user;
        $url = StudentAccounts::invitationUrl($user);

        $target = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/app/password-reset/reset', $target);
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertTrue(Password::broker()->tokenExists($user, $query['token']));

        // Tanda tangan diubah → minta tautan baru
        $this->get($url.'x')->assertRedirect(Filament::getPanel('app')->getRequestPasswordResetUrl());

        // Kedaluwarsa → minta tautan baru
        $this->travel(config('sipma.invitation_days') + 1)->days();
        $this->get($url)->assertRedirect(Filament::getPanel('app')->getRequestPasswordResetUrl());
        $this->travelBack();

        // Password sudah dibuat → undangan mati
        $user->update(['password' => 'a-new-password']);
        $this->get($url)->assertRedirect(route('login'));

        // Hanya untuk akun mahasiswa
        $this->get(StudentAccounts::invitationUrl($this->superAdmin()))->assertNotFound();
    }

    public function test_activation_goes_ahead_when_no_account_can_be_prepared(): void
    {
        $agent = $this->agent();
        $this->actingAs($this->superAdmin());
        $users = User::query()->count();

        // Agen memakai email sendiri untuk mahasiswanya
        $taken = $this->viaAgent($agent, email: $agent->user->email);
        $this->assertSame(AccountProvision::EmailTaken, app(StudentWorkflow::class)->enroll($taken));

        $missing = $this->viaAgent($agent, email: ' ');
        $this->assertSame(AccountProvision::MissingEmail, app(StudentWorkflow::class)->enroll($missing));

        $this->assertSame($users, User::query()->count());
        foreach ([$taken, $missing] as $student) {
            $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
            $this->assertNull($student->fresh()->user_id);
        }
        Notification::assertSentToTimes($agent->user, StudentEnrolled::class, 2);
        Notification::assertNotSentTo($agent->user, StudentAccountInvitation::class);
    }

    public function test_existing_student_accounts_are_reused(): void
    {
        $this->actingAs($this->superAdmin());

        // Akun mahasiswa tanpa pendaftaran dengan email yang sama → dihubungkan
        $account = User::factory()->create(['role' => UserRole::Student, 'email' => 'lena.k@example.test']);
        $linked = $this->viaAgent($this->agent());
        $this->assertSame(AccountProvision::Linked, app(StudentWorkflow::class)->enroll($linked));
        $this->assertTrue($linked->fresh()->user->is($account));
        Notification::assertSentTo($account, StudentEnrolled::class);

        // Email itu kini sudah punya pendaftaran → mahasiswa lain tidak ikut dihubungkan
        $second = $this->viaAgent($this->agent());
        $this->assertSame(AccountProvision::EmailTaken, app(StudentWorkflow::class)->enroll($second));

        // Pendaftar mandiri sudah punya akun
        $self = $this->student(status: StudentStatus::LoaIssued);
        $this->assertSame(AccountProvision::Existing, app(StudentWorkflow::class)->enroll($self));
        Notification::assertSentTo($self->user, StudentEnrolled::class);
    }
}

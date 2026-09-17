<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_shows_both_role_options(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Who are you signing up as?')
            ->assertSee('Student')
            ->assertSee('Partner agency');
    }

    public function test_role_can_be_preselected_from_query_string(): void
    {
        Livewire::withQueryParams(['role' => 'agent'])
            ->test(Register::class)
            ->assertSet('form.role', 'agent')
            ->assertSee('About your agency');
    }

    public function test_student_can_register_and_receives_verification_email(): void
    {
        Notification::fake();

        $this->fillForm(role: 'student', email: 'Jane.Doe@Example.com')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(url('/app'));

        $user = User::where('email', 'jane.doe@example.com')->firstOrFail();

        $this->assertSame(UserRole::Student, $user->role);
        $this->assertNull($user->agent);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification): bool => str_contains($notification->url, '/app/email-verification/verify'),
        );
    }

    public function test_agent_registration_creates_agent_profile(): void
    {
        Notification::fake();

        $this->fillForm(role: 'agent', email: 'contact@edu-agency.test')
            ->set('form.agency_name', 'Edu Agency')
            ->set('form.country', 'Australia')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(url('/agent'));

        $user = User::where('email', 'contact@edu-agency.test')->firstOrFail();

        $this->assertSame(UserRole::Agent, $user->role);
        $this->assertSame('Edu Agency', $user->agent->company_name);
        $this->assertSame('Australia', $user->agent->country);
        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification): bool => str_contains($notification->url, '/agent/email-verification/verify'),
        );
    }

    public function test_agency_fields_are_required_only_for_agents(): void
    {
        $this->fillForm(role: 'agent')
            ->call('register')
            ->assertHasErrors(['form.agency_name' => 'required', 'form.country' => 'required']);

        $this->fillForm(role: 'student')
            ->call('register')
            ->assertHasNoErrors(['form.agency_name', 'form.country']);
    }

    public function test_admin_roles_cannot_be_self_registered(): void
    {
        foreach ([UserRole::Admin, UserRole::SuperAdmin] as $role) {
            $this->fillForm(role: $role->value)
                ->call('register')
                ->assertHasErrors(['form.role']);
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_role_and_consent_are_required(): void
    {
        $this->fillForm(role: '')
            ->set('form.consent', false)
            ->call('register')
            ->assertHasErrors(['form.role' => 'required', 'form.consent' => 'accepted']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->fillForm()
            ->set('form.password_confirmation', 'something-else')
            ->call('register')
            ->assertHasErrors(['form.password_confirmation']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->fillForm()
            ->call('register')
            ->assertHasErrors(['form.email' => 'unique']);
    }

    public function test_error_summary_appears_only_after_failed_submit(): void
    {
        Livewire::test(Register::class)
            ->set('form.email', 'not-an-email')
            ->assertHasErrors(['form.email'])
            ->assertDontSee('There is a problem with your sign-up')
            ->call('register')
            ->assertSee('There is a problem with your sign-up')
            ->assertDispatched('form-invalid');
    }

    public function test_registration_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->fillForm(role: '')->call('register');
        }

        $this->fillForm()
            ->call('register')
            ->assertHasErrors(['form.email'])
            ->assertSee('Too many sign-up attempts');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_panel_signup_links_redirect_to_portal_register(): void
    {
        $this->get('/agent/register')->assertRedirect(route('register', ['role' => 'agent']));
        $this->get('/app/register')->assertRedirect(route('register', ['role' => 'student']));
    }

    public function test_unverified_user_must_verify_email_before_using_panel(): void
    {
        $user = User::factory()->unverified()->create(['role' => UserRole::Student]);

        $this->actingAs($user)
            ->get(url('/app'))
            ->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());
    }

    public function test_logged_in_user_visiting_register_is_sent_to_their_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Agent]);

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect(url('/agent'));
    }

    private function fillForm(string $role = 'student', string $email = 'jane@example.com'): Testable
    {
        return Livewire::test(Register::class)
            ->set('form.role', $role)
            ->set('form.name', 'Jane Doe')
            ->set('form.email', $email)
            ->set('form.password', 'secret-password-123')
            ->set('form.password_confirmation', 'secret-password-123')
            ->set('form.consent', true);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password-123';

    public function test_login_page_renders_with_password_toggle(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Log in to SIPMA')
            ->assertSee('Show password');
    }

    /**
     * @return array<string, array{UserRole, string}>
     */
    public static function roles(): array
    {
        return [
            'super admin' => [UserRole::SuperAdmin, '/admin'],
            'faculty admin' => [UserRole::Admin, '/admin'],
            'agent' => [UserRole::Agent, '/agent'],
            'student' => [UserRole::Student, '/app'],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_is_sent_to_its_own_panel(UserRole $role, string $path): void
    {
        $user = $this->makeUser(['role' => $role]);

        $this->attempt($user->email, self::PASSWORD)
            ->assertHasNoErrors()
            ->assertRedirect(url($path));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_shows_the_same_message_as_unknown_email(): void
    {
        $user = $this->makeUser();

        $this->attempt($user->email, 'wrong-password')
            ->assertHasErrors(['form.email'])
            ->assertSee('The email address or password is incorrect.');

        $this->attempt('nobody@example.com', self::PASSWORD)
            ->assertHasErrors(['form.email'])
            ->assertSee('The email address or password is incorrect.');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->makeUser(['is_active' => false]);

        $this->attempt($user->email, self::PASSWORD)
            ->assertHasErrors(['form.email'])
            ->assertSee('This account has been deactivated');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->makeUser();

        foreach (range(1, 5) as $attempt) {
            $this->attempt($user->email, 'wrong-password');
        }

        $this->attempt($user->email, self::PASSWORD)
            ->assertHasErrors(['form.email'])
            ->assertSee('Too many login attempts');

        $this->assertGuest();
    }

    public function test_intended_page_inside_own_panel_is_respected(): void
    {
        $user = $this->makeUser(['role' => UserRole::Agent]);
        session()->put('url.intended', url('/agent/students'));

        $this->attempt($user->email, self::PASSWORD)->assertRedirect(url('/agent/students'));
    }

    public function test_intended_page_of_another_panel_is_ignored(): void
    {
        $user = $this->makeUser(['role' => UserRole::Agent]);
        session()->put('url.intended', url('/admin/users'));

        $this->attempt($user->email, self::PASSWORD)->assertRedirect(url('/agent'));
    }

    public function test_every_panel_login_url_redirects_to_unified_login(): void
    {
        foreach (['/admin/login', '/agent/login', '/app/login'] as $panelLogin) {
            $this->get($panelLogin)->assertRedirect(route('login'));
        }
    }

    public function test_logged_in_user_visiting_login_is_sent_to_their_panel(): void
    {
        $user = $this->makeUser(['role' => UserRole::Student]);

        $this->actingAs($user)->get('/login')->assertRedirect(url('/app'));
    }

    public function test_register_page_links_to_unified_login(): void
    {
        $this->get('/register')
            ->assertSee(route('login'), false)
            ->assertSee('Show password');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password' => self::PASSWORD], $attributes));
    }

    private function attempt(string $email, string $password): Testable
    {
        return Livewire::test(Login::class)
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }
}

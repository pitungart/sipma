<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Auth\Register;
use App\Models\User;
use Filament\Notifications\Auth\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_is_used_when_browser_sends_no_preference(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Create your SIPMA account');
    }

    public function test_browser_language_is_used_when_no_choice_was_made(): void
    {
        $this->withHeader('Accept-Language', 'id-ID,id;q=0.9,en;q=0.8')
            ->get('/register')
            ->assertSee('<html lang="id">', false)
            ->assertSee('Buat akun SIPMA');
    }

    public function test_unsupported_browser_language_falls_back_to_english(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get('/register')
            ->assertSee('Create your SIPMA account');
    }

    public function test_switching_language_stores_choice_and_returns_to_previous_page(): void
    {
        $this->withHeader('Referer', url('/register'))
            ->get('/locale/id')
            ->assertRedirect(url('/register'))
            ->assertSessionHas('locale', 'id')
            ->assertCookie('locale', 'id');
    }

    public function test_switching_language_without_previous_page_goes_to_register(): void
    {
        $this->get('/locale/id')
            ->assertRedirect(route('register'))
            ->assertCookie('locale', 'id');
    }

    public function test_saved_choice_overrides_browser_language(): void
    {
        $this->withCookie('locale', 'id')
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/register')
            ->assertSee('Buat akun SIPMA');

        $this->withSession(['locale' => 'en'])
            ->withHeader('Accept-Language', 'id-ID')
            ->get('/register')
            ->assertSee('Create your SIPMA account');
    }

    public function test_unsupported_locale_is_not_found(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }

    public function test_switching_language_never_redirects_to_another_host(): void
    {
        $this->withHeader('Referer', 'https://evil.test/phishing')
            ->get('/locale/en')
            ->assertRedirect(route('register'));
    }

    public function test_validation_messages_follow_active_language(): void
    {
        app()->setLocale('id');

        Livewire::test(Register::class)
            ->set('form.email', 'bukan-email')
            ->set('form.password', 'pendek')
            ->call('register')
            ->assertSee('Masukkan alamat email yang valid')
            ->assertSee('Kata sandi minimal 8 karakter.')
            ->assertSee('Ada yang perlu diperbaiki pada pendaftaran Anda');
    }

    public function test_agent_and_student_panels_follow_chosen_language(): void
    {
        // Login terpadu ikut bahasa pilihan (dicek sebelum ada user yang login, karena user login dialihkan ke panel)
        $this->withCookie('locale', 'id')->get('/login')->assertSee('Masuk ke SIPMA');

        // Login panel dialihkan ke /login terpadu, jadi uji halaman panel yang tetap dirender Filament:
        // prompt verifikasi email untuk user yang belum terverifikasi.
        $pairs = [[UserRole::Agent, 'agent'], [UserRole::Student, 'app']];

        foreach ($pairs as [$role, $panelId]) {
            $user = User::factory()->unverified()->create(['role' => $role]);

            $this->actingAs($user)
                ->withCookie('locale', 'id')
                ->get(\Filament\Facades\Filament::getPanel($panelId)->getEmailVerificationPromptUrl())
                ->assertOk()
                ->assertSee('lang="id"', false);
        }
    }

    public function test_verification_email_is_sent_in_active_language(): void
    {
        Notification::fake();
        app()->setLocale('id');

        $user = User::factory()->unverified()->create(['role' => UserRole::Student]);
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification): bool => $notification->locale === 'id',
        );
    }
}

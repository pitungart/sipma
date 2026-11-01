<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_shows_sipma_404_with_login_button_for_guests(): void
    {
        $this->get('/tidak-ada')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('/tidak-ada')
            ->assertSee(route('login'), false)
            ->assertDontSee('Go to dashboard');
    }

    public function test_404_follows_chosen_language(): void
    {
        $this->withHeader('Accept-Language', 'id-ID,id;q=0.9')
            ->get('/tidak-ada')
            ->assertNotFound()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Halaman tidak ditemukan');
    }

    public function test_signed_in_user_gets_a_button_to_their_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/tidak-ada')
            ->assertNotFound()
            ->assertSee('Go to dashboard')
            ->assertSee($admin->panelUrl(), false);
    }

    public function test_back_button_only_appears_with_a_previous_page_on_this_site(): void
    {
        $this->get('/tidak-ada')->assertDontSee('Go back');

        $this->from(url('/login'))->get('/tidak-ada')->assertSee('Go back');

        $this->from('https://example.com/elsewhere')->get('/tidak-ada')->assertDontSee('Go back');
    }
}

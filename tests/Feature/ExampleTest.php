<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman "/" belum punya landing page: tamu ke login, pengguna yang masuk ke panelnya.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_signed_in_user_is_sent_to_their_panel(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);

        $this->actingAs($agent)->get('/')->assertRedirect(url('/agent'));
    }
}

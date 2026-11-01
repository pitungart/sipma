<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\Profile;
use App\Models\Faculty;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\MouSubmitted;
use App\Support\NotificationPreferences;
use App\Support\UserSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function superAdmin(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'password' => Hash::make('rahasia-lama'), ...$attributes]);
    }

    public function test_profile_page_shows_identity_access_and_tabs(): void
    {
        $admin = $this->superAdmin(['name' => 'Ni Made Ayu']);

        $this->actingAs($admin)
            ->get(Profile::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Ni Made Ayu')
            ->assertSee(__('portal.brand.office_name'))
            ->assertSee(__('profile.card.access'))
            ->assertSee(__('profile.tabs.security'))
            ->assertSee(__('profile.tabs.activity'));
    }

    public function test_faculty_admin_sees_faculty_as_unit_and_no_email_types(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);

        $this->actingAs($admin)
            ->get(Profile::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Fakultas Teknik')
            ->assertSee(__('profile.preferences.none'));
    }

    public function test_name_can_be_changed_without_password(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)
            ->fillForm(['name' => 'Nama Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nama Baru', $admin->fresh()->name);
    }

    public function test_changing_email_requires_current_password(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)
            ->fillForm(['email' => 'baru@unud.ac.id'])
            ->call('save')
            ->assertHasFormErrors(['current_password' => 'required']);

        Livewire::actingAs($admin)->test(Profile::class)
            ->fillForm(['email' => 'baru@unud.ac.id', 'current_password' => 'salah'])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        Livewire::actingAs($admin)->test(Profile::class)
            ->fillForm(['email' => 'baru@unud.ac.id', 'current_password' => 'rahasia-lama'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('baru@unud.ac.id', $admin->fresh()->email);
    }

    public function test_password_change_checks_current_password_and_signs_out_other_devices(): void
    {
        $admin = $this->superAdmin();
        DB::table('sessions')->insert(['id' => 'perangkat-lain', 'user_id' => $admin->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Firefox/120', 'payload' => '', 'last_activity' => now()->timestamp]);

        Livewire::actingAs($admin)->test(Profile::class)
            ->fillForm(['current_password' => 'salah', 'password' => 'RahasiaBaru1!', 'password_confirmation' => 'RahasiaBaru1!'], 'passwordForm')
            ->call('updatePassword')
            ->assertHasFormErrors(['current_password'], 'passwordForm');

        Livewire::actingAs($admin)->test(Profile::class)
            ->fillForm(['current_password' => 'rahasia-lama', 'password' => 'RahasiaBaru1!', 'password_confirmation' => 'RahasiaBaru1!'], 'passwordForm')
            ->call('updatePassword')
            ->assertHasNoFormErrors(formName: 'passwordForm');

        $this->assertTrue(Hash::check('RahasiaBaru1!', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'perangkat-lain']);
    }

    public function test_another_session_can_be_revoked_and_rotates_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->superAdmin();
        $admin->forceFill(['remember_token' => 'token-lama'])->save();
        DB::table('sessions')->insert(['id' => 'iphone', 'user_id' => $admin->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile Safari/604.1', 'payload' => '', 'last_activity' => now()->timestamp]);

        Livewire::actingAs($admin)->test(Profile::class)
            ->assertSee('Safari · iPhone')
            ->call('revokeSession', 'iphone');

        $this->assertDatabaseMissing('sessions', ['id' => 'iphone']);
        $this->assertNotSame('token-lama', $admin->fresh()->remember_token);
    }

    public function test_sessions_of_other_users_cannot_be_revoked(): void
    {
        $admin = $this->superAdmin();
        $other = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'milik-orang', 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => '', 'payload' => '', 'last_activity' => now()->timestamp]);

        Livewire::actingAs($admin)->test(Profile::class)->call('revokeSession', 'milik-orang');

        $this->assertDatabaseHas('sessions', ['id' => 'milik-orang']);
    }

    public function test_user_agent_is_described_without_external_services(): void
    {
        $this->assertSame(
            ['browser' => 'Chrome', 'platform' => 'Windows', 'device' => 'desktop'],
            UserSessions::describeAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'),
        );
        $this->assertSame(
            ['browser' => 'Edge', 'platform' => 'macOS', 'device' => 'desktop'],
            UserSessions::describeAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0'),
        );
        $this->assertSame('mobile', UserSessions::describeAgent('Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile Safari/537.36')['device']);
    }

    public function test_photo_upload_replaces_old_photo_and_can_be_removed(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('a.jpg'));
        $first = $admin->fresh()->avatar_path;
        Storage::disk('local')->assertExists($first);

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('b.png'));
        $second = $admin->fresh()->avatar_path;
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($admin)->get($admin->fresh()->avatarUrl())->assertOk();

        Livewire::actingAs($admin)->test(Profile::class)->call('removePhoto');
        $this->assertNull($admin->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_new_photo_opens_the_position_editor_and_focus_can_be_saved(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin(['avatar_focus' => ['x' => 0.1, 'y' => 0.1, 'zoom' => 2, 'ratio' => 1]]);

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('pas-foto.jpg', 300, 400))
            ->assertDispatched('open-modal', id: 'avatar-editor');

        // Foto baru mulai dari tengah lagi
        $this->assertNull($admin->fresh()->avatar_focus);

        Livewire::actingAs($admin->fresh())->test(Profile::class)
            ->call('saveAvatarFocus', 0.5, 0.3, 1.5, 0.75)
            ->assertRedirect(Profile::getUrl(panel: 'admin'));

        $this->assertEquals(['x' => 0.5, 'y' => 0.3, 'zoom' => 1.5, 'ratio' => 0.75], $admin->fresh()->avatar_focus);
    }

    public function test_focus_values_are_clamped_and_need_a_photo(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)
            ->call('saveAvatarFocus', 0.5, 0.5, 1, 1)
            ->assertStatus(422);

        $admin->forceFill(['avatar_path' => 'users/x/avatar/a.jpg'])->save();

        Livewire::actingAs($admin)->test(Profile::class)->call('saveAvatarFocus', 7, -2, 9, 1);

        $this->assertEquals(['x' => 1, 'y' => 0, 'zoom' => 3, 'ratio' => 1], $admin->fresh()->avatar_focus);
    }

    public function test_photo_must_be_an_image_within_limit(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('photo', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
            ->assertHasErrors('photo');

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('photo', UploadedFile::fake()->image('besar.jpg')->size(Profile::PHOTO_MAX_KB + 1))
            ->assertHasErrors('photo');

        $this->assertNull($admin->fresh()->avatar_path);
    }

    public function test_avatar_file_is_private(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Agent, 'avatar_path' => UploadedFile::fake()->image('x.jpg')->store('users/x/avatar', 'local')]);
        $student = User::factory()->create(['role' => UserRole::Student]);

        $this->get($owner->avatarUrl())->assertRedirect();
        $this->actingAs($student)->get($owner->avatarUrl())->assertForbidden();
        $this->actingAs($owner)->get($owner->avatarUrl())->assertOk();
        $this->actingAs($this->superAdmin())->get($owner->avatarUrl())->assertOk();
    }

    public function test_email_preferences_are_disabled_while_system_mail_is_off(): void
    {
        config(['sipma.mail_notifications' => false]);
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)
            ->assertSee(__('profile.preferences.mail_disabled'))
            ->call('toggleNotification', 'mou_submitted')
            ->assertForbidden();

        $this->assertNull($admin->fresh()->notification_preferences);
    }

    public function test_email_preferences_filter_mail_when_enabled(): void
    {
        config(['sipma.mail_notifications' => true]);
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Profile::class)->call('toggleNotification', 'mou_submitted');

        $admin->refresh();
        $this->assertFalse(NotificationPreferences::for($admin)['mou_submitted']);
        $this->assertFalse(NotificationPreferences::wantsMail($admin, MouSubmitted::class));
        $this->assertTrue(NotificationPreferences::wantsMail($admin, ApplicationSubmitted::class));
    }

    public function test_unknown_preference_key_is_rejected(): void
    {
        config(['sipma.mail_notifications' => true]);

        Livewire::actingAs($this->superAdmin())->test(Profile::class)
            ->call('toggleNotification', 'loa_issued')
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\SentReminder;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Notifications\StudentCardExpiring;
use App\Notifications\StudentCardsExpiring;
use App\Support\ActivityDescriber;
use App\Support\NotificationPreferences;
use App\Workflow\StudentCardWorkflow;
use App\Workflow\WorkflowException;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

/**
 * TP-2 F3: KTM otomatis kedaluwarsa (sipma:expire-cards) dan pengingat sebelum habis (sipma:reminders).
 */
class StudentCardExpiryTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(8, 0));
    }

    private function enrolled(StudentStatus $status = StudentStatus::Enrolled): Student
    {
        $student = $this->student(status: $status);

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

    /**
     * KTM yang berlaku $validDays lagi dari hari ini, terbit $issuedDaysAgo hari lalu.
     */
    private function card(int $validDays, int $issuedDaysAgo = 100, ?Student $student = null): StudentCard
    {
        $card = app(StudentCardWorkflow::class)->issue($student ?? $this->enrolled(), now()->addYear());
        $card->update(['valid_until' => today()->addDays($validDays), 'issued_at' => today()->subDays($issuedDaysAgo)]);

        Notification::fake();

        return $card->fresh();
    }

    public function test_cards_expire_only_after_their_last_valid_day(): void
    {
        $lastDay = $this->card(0);
        $yesterday = $this->card(-1);
        $future = $this->card(10);

        $this->artisan('sipma:expire-cards')->expectsOutputToContain('Kedaluwarsa: 1 KTM')->assertSuccessful();

        $this->assertSame(StudentCardStatus::Active, $lastDay->fresh()->status);
        $this->assertSame(StudentCardStatus::Expired, $yesterday->fresh()->status);
        $this->assertSame(StudentCardStatus::Active, $future->fresh()->status);
    }

    public function test_revoked_cards_are_left_alone_and_running_again_is_safe(): void
    {
        $revoked = $this->card(-5);
        app(StudentCardWorkflow::class)->revoke($revoked, 'Keliru');
        $expired = $this->card(-1);

        $this->artisan('sipma:expire-cards')->assertSuccessful();
        $this->artisan('sipma:expire-cards')->expectsOutputToContain('Kedaluwarsa: 0 KTM')->assertSuccessful();

        $this->assertSame(StudentCardStatus::Revoked, $revoked->fresh()->status);
        $this->assertSame(StudentCardStatus::Expired, $expired->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $card = $this->card(-1);

        $this->artisan('sipma:expire-cards --dry-run')->expectsOutputToContain('[dry-run] Akan kedaluwarsa: 1 KTM')->assertSuccessful();

        $this->assertSame(StudentCardStatus::Active, $card->fresh()->status);
    }

    public function test_expiry_ignores_the_reminder_switch(): void
    {
        config(['sipma.reminders.enabled' => false]);
        $card = $this->card(-1);

        $this->artisan('sipma:expire-cards')->assertSuccessful();

        $this->assertSame(StudentCardStatus::Expired, $card->fresh()->status);
    }

    public function test_workflow_skips_a_card_that_was_replaced_in_the_meantime(): void
    {
        $card = $this->card(-1);
        $stale = StudentCard::find($card->getKey());

        $card->update(['status' => StudentCardStatus::Revoked]);

        $this->assertFalse(app(StudentCardWorkflow::class)->expire($stale));
        $this->assertSame(StudentCardStatus::Revoked, $card->fresh()->status);
    }

    public function test_expired_card_leaves_room_for_a_new_card_and_cannot_be_reissued(): void
    {
        $card = $this->card(-1);
        $this->artisan('sipma:expire-cards')->assertSuccessful();

        try {
            app(StudentCardWorkflow::class)->reissue($card->fresh(), 'Perpanjang');
            $this->fail('Expired card should not be reissued');
        } catch (WorkflowException) {
            // Perpanjangan setelah kedaluwarsa memakai Terbitkan KTM
        }

        $new = app(StudentCardWorkflow::class)->issue($card->student, now()->addYear());

        $this->assertTrue($new->isActive());
        $this->assertTrue($card->student->currentCard()->is($new));
    }

    public function test_history_names_the_system_expiry(): void
    {
        $card = $this->card(-1);
        $this->artisan('sipma:expire-cards')->assertSuccessful();

        $this->assertContains(
            __('admin.dashboard.activity_card_expired', ['name' => $card->student->full_name]),
            ActivityDescriber::forStudent($card->student)->pluck('text'),
        );
    }

    public function test_commands_are_scheduled_separately(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn (Event $event): string => (string) $event->command);

        $this->assertTrue($events->contains(fn (string $command): bool => str_contains($command, 'sipma:expire-cards')));
        $this->assertTrue($events->contains(fn (string $command): bool => str_contains($command, 'sipma:reminders')));
    }

    public function test_active_student_and_kui_are_reminded_once(): void
    {
        $admin = $this->superAdmin();
        $card = $this->card(30);
        $later = $this->card(31); // belum masuk jendela

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertSentTo($card->student->user, StudentCardExpiring::class, fn (StudentCardExpiring $n): bool => $n->card->is($card));
        Notification::assertSentTo($admin, StudentCardsExpiring::class, fn (StudentCardsExpiring $n): bool => $n->cards->count() === 1 && $n->cards->first()->is($card));
        Notification::assertSentTimes(StudentCardExpiring::class, 1);

        // Dijalankan ulang di hari yang sama: tidak ada yang terkirim lagi
        Notification::fake();
        $this->artisan('sipma:reminders')->assertSuccessful();
        Notification::assertNothingSent();

        // Lima hari kemudian hanya KTM yang baru masuk jendela
        $this->travel(5)->days();
        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertNotSentTo($card->student->user, StudentCardExpiring::class);
        Notification::assertSentTo($later->student->user, StudentCardExpiring::class);
        Notification::assertSentTo($admin, StudentCardsExpiring::class, fn (StudentCardsExpiring $n): bool => $n->cards->count() === 1 && $n->cards->first()->is($later));
    }

    public function test_short_lived_cards_are_not_reminded_right_after_issuing(): void
    {
        $this->superAdmin();
        $this->card(30, issuedDaysAgo: 0);
        $this->card(20, issuedDaysAgo: 5);

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_only_active_students_with_active_cards_count(): void
    {
        $this->superAdmin();

        $completed = $this->card(10);
        $completed->student->update(['status' => StudentStatus::Completed]);

        $revoked = $this->card(10);
        app(StudentCardWorkflow::class)->revoke($revoked, 'Keliru');
        Notification::fake();

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_student_without_account_still_appears_for_kui(): void
    {
        $admin = $this->superAdmin();
        $card = $this->card(10);
        $card->student->update(['user_id' => null, 'agent_id' => $this->agent()->id]);

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertNotSentTo($card->student->agent->user, StudentCardExpiring::class);
        Notification::assertSentTo($admin, StudentCardsExpiring::class);
    }

    public function test_a_new_kui_admin_receives_their_own_summary(): void
    {
        $first = $this->superAdmin();
        $this->card(10);

        $this->artisan('sipma:reminders')->assertSuccessful();
        Notification::assertSentTo($first, StudentCardsExpiring::class);

        Notification::fake();
        $second = $this->superAdmin();

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertSentTo($second, StudentCardsExpiring::class);
        Notification::assertNotSentTo($first, StudentCardsExpiring::class);
    }

    public function test_inactive_admins_and_faculty_admins_are_skipped(): void
    {
        $inactive = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => false]);
        $facultyAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $this->card(10);

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertNotSentTo($inactive, StudentCardsExpiring::class);
        Notification::assertNotSentTo($facultyAdmin, StudentCardsExpiring::class);
    }

    public function test_dry_run_and_switch_send_nothing(): void
    {
        $admin = $this->superAdmin();
        $this->card(10);

        $this->artisan('sipma:reminders --dry-run')->expectsOutputToContain('[dry-run]')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertSame(0, SentReminder::query()->count());

        config(['sipma.reminders.enabled' => false]);
        $this->artisan('sipma:reminders')->assertSuccessful();
        Notification::assertNotSentTo($admin, StudentCardsExpiring::class);
    }

    public function test_reminder_window_follows_the_setting(): void
    {
        config(['sipma.reminders.card_expiry_before' => 7]);
        $this->superAdmin();
        $near = $this->card(7);
        $far = $this->card(8);

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertSentTo($near->student->user, StudentCardExpiring::class);
        Notification::assertNotSentTo($far->student->user, StudentCardExpiring::class);
    }

    public function test_preferences_and_notification_links(): void
    {
        $admin = $this->superAdmin();
        $card = $this->card(10);
        $this->assertContains('student_cards_expiring', NotificationPreferences::keysFor($admin));

        $admin->forceFill(['notification_preferences' => ['student_cards_expiring' => false]])->save();
        $this->assertFalse(NotificationPreferences::wantsMail($admin, StudentCardsExpiring::class));

        $card->student->user->forceFill(['notification_preferences' => ['student_card' => false]])->save();
        $this->assertFalse(NotificationPreferences::wantsMail($card->student->user->fresh(), StudentCardExpiring::class));

        $this->assertStringContainsString('student-card', json_encode((new StudentCardExpiring($card))->toDatabase($card->student->user), JSON_UNESCAPED_SLASHES));
        $this->assertStringContainsString('student-cards', json_encode((new StudentCardsExpiring(collect([$card])))->toDatabase($admin), JSON_UNESCAPED_SLASHES));
    }
}

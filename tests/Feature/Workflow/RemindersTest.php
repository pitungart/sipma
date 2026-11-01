<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Models\Program;
use App\Models\SentReminder;
use App\Models\Student;
use App\Notifications\LoaIssued;
use App\Notifications\PaymentReminder;
use App\Notifications\SubmissionReminder;
use App\Workflow\StudentWorkflow;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * §5 Sistem otomatis: pengingat terjadwal (sipma:reminders), causer activity log, email antrean.
 */
class RemindersTest extends WorkflowTestCase
{
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(8, 0));
        $this->program = $this->program(admissionFee: 500_000);
    }

    private function agentStudent($agent, StudentStatus $status = StudentStatus::Draft): Student
    {
        $student = $this->student($this->program, $status);
        $student->update(['user_id' => null, 'agent_id' => $agent->id]);

        return $student->fresh();
    }

    public function test_master_switch_turns_reminders_off(): void
    {
        config(['sipma.reminders.enabled' => false]);
        $this->student($this->program);

        $this->artisan('sipma:reminders')->expectsOutputToContain('SIPMA_REMINDERS=false')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_idle_unsubmitted_applications_are_reminded_once_per_idle_stretch(): void
    {
        $self = $this->student($this->program);
        $agent = $this->agent();
        $this->agentStudent($agent);
        $this->agentStudent($agent, StudentStatus::Revision);
        $this->agentStudent($agent, StudentStatus::Submitted); // sudah diajukan: tidak diingatkan

        // Belum 3 hari tidak diubah
        $this->travel(2)->days();
        $this->artisan('sipma:reminders')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(1)->days();
        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertSentTo($self->user, SubmissionReminder::class, fn (SubmissionReminder $n): bool => $n->days === 3 && $n->students->count() === 1
            && $n->toDatabase($self->user)['title'] === trans_choice('workflow.notifications.submission_reminder.title', 1));
        Notification::assertSentTo($agent->user, SubmissionReminder::class, fn (SubmissionReminder $n): bool => $n->students->count() === 2);
        Notification::assertSentTimes(SubmissionReminder::class, 2);

        // Dijalankan ulang hari yang sama, atau hari berikutnya tanpa perubahan: tidak ganda
        $this->artisan('sipma:reminders');
        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(SubmissionReminder::class, 2);

        // Diubah lalu diam lagi 3 hari → diingatkan lagi, hanya pendaftaran itu
        $self->fresh()->update(['phone_number' => '+61 400 111 222']);
        $this->travel(2)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(SubmissionReminder::class, 2);

        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(SubmissionReminder::class, 3);
        Notification::assertSentToTimes($self->user, SubmissionReminder::class, 2);
    }

    public function test_uploading_documents_counts_as_activity(): void
    {
        $student = $this->student($this->program);

        $this->travel(2)->days();
        app(StudentWorkflow::class)->uploadDocument($student, DocumentType::Passport, $this->file(DocumentType::Passport));

        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertNothingSent();

        $this->travel(2)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTo($student->user, SubmissionReminder::class);
    }

    public function test_idle_days_follow_the_configuration(): void
    {
        config(['sipma.reminders.submission_after' => 5]);
        $student = $this->student($this->program);

        $this->travel(3)->days();
        $this->artisan('sipma:reminders');
        Notification::assertNothingSent();

        $this->travel(2)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTo($student->user, SubmissionReminder::class, fn (SubmissionReminder $n): bool => $n->days === 5
            && str_contains($n->toDatabase($student->user)['body'], '5'));
    }

    public function test_unpaid_fees_are_reminded_every_day_until_proof_is_uploaded(): void
    {
        $student = $this->student($this->program, StudentStatus::Approved);

        // Baru disetujui hari ini: belum diingatkan (notifikasi "disetujui" sudah cukup)
        $this->artisan('sipma:reminders');
        Notification::assertNotSentTo($student->user, PaymentReminder::class);

        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTo($student->user, PaymentReminder::class, fn (PaymentReminder $n): bool => $n->feeTypes === [PaymentType::AdmissionFee->value, PaymentType::TuitionFee->value]
            && str_contains($n->toDatabase($student->user)['body'], PaymentType::AdmissionFee->getLabel()));

        $this->artisan('sipma:reminders'); // hari yang sama
        Notification::assertSentTimes(PaymentReminder::class, 1);

        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(PaymentReminder::class, 2);

        // Semua bukti diunggah → berhenti; bukti ditolak → diingatkan lagi
        $payment = app(StudentWorkflow::class)->submitPayment($student, PaymentType::AdmissionFee, $this->file());
        app(StudentWorkflow::class)->submitPayment($student, PaymentType::TuitionFee, $this->file());
        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(PaymentReminder::class, 2);

        $this->actingAs($this->superAdmin());
        app(StudentWorkflow::class)->rejectPayment($payment, 'Blurry');
        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(PaymentReminder::class, 3);
        $this->assertSame(PaymentStatus::Rejected, $payment->fresh()->status);
    }

    public function test_dry_run_sends_and_records_nothing(): void
    {
        $this->student($this->program);
        $this->travel(3)->days();

        $this->artisan('sipma:reminders --dry-run')->expectsOutputToContain('[dry-run]')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, SentReminder::query()->count());
    }

    public function test_activity_log_records_who_changed_the_status(): void
    {
        $admin = $this->superAdmin();
        $student = $this->student($this->program);
        $this->uploadAll($student);
        app(StudentWorkflow::class)->submit($student);

        $this->actingAs($admin);
        app(StudentWorkflow::class)->startReview($student->fresh());

        $activity = Activity::query()->where('subject_id', $student->id)->latest('id')->first();
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(StudentStatus::InReview->value, $activity->properties['attributes']['status']);
    }

    public function test_mail_can_go_through_the_queue_while_the_bell_stays_immediate(): void
    {
        $notification = new LoaIssued($this->student($this->program));

        $this->assertSame(['database' => 'sync', 'mail' => 'sync'], $notification->viaConnections());

        config(['sipma.queue_mail' => true, 'queue.default' => 'database']);
        $this->assertSame(['database' => 'sync', 'mail' => 'database'], $notification->viaConnections());
    }

    private function uploadAll(Student $student): void
    {
        foreach (DocumentType::required() as $type) {
            app(StudentWorkflow::class)->uploadDocument($student, $type, $this->file($type));
        }
    }
}

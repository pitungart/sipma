<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Models\AcademicPeriod;
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

    private AcademicPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(8, 0));
        $this->program = $this->program(admissionFee: 500_000);
        $this->period = AcademicPeriod::create([
            'program_id' => $this->program->id,
            'name' => 'Batch Oktober',
            'code' => 'OKT-26',
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ]);
    }

    private function agentStudent($agent, StudentStatus $status = StudentStatus::Draft): Student
    {
        $student = $this->student($this->program, $status);
        $student->update(['user_id' => null, 'agent_id' => $agent->id, 'academic_period_id' => $this->period->id]);

        return $student->fresh();
    }

    public function test_master_switch_turns_reminders_off(): void
    {
        config(['sipma.reminders.enabled' => false]);
        $this->student($this->program);

        $this->artisan('sipma:reminders')->expectsOutputToContain('SIPMA_REMINDERS=false')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_unsubmitted_applications_are_reminded_before_the_period_closes(): void
    {
        $self = $this->student($this->program);
        $self->update(['academic_period_id' => $this->period->id]);
        $agent = $this->agent();
        $this->agentStudent($agent);
        $this->agentStudent($agent, StudentStatus::Revision);
        $this->agentStudent($agent, StudentStatus::Submitted); // sudah diajukan: tidak diingatkan

        $this->artisan('sipma:reminders')->assertSuccessful();

        Notification::assertSentTo($self->user, SubmissionReminder::class, fn (SubmissionReminder $n): bool => $n->days === 7 && $n->students->count() === 1);
        Notification::assertSentTo($agent->user, SubmissionReminder::class, fn (SubmissionReminder $n): bool => $n->students->count() === 2);
        Notification::assertSentTimes(SubmissionReminder::class, 2);

        // Dijalankan ulang hari yang sama: tidak ganda
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(SubmissionReminder::class, 2);

        // H-3 tidak termasuk SIPMA_REMINDER_DAYS (7,1); H-1 diingatkan lagi
        $this->travel(4)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(SubmissionReminder::class, 2);

        $this->travel(2)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTo($self->user, SubmissionReminder::class, fn (SubmissionReminder $n): bool => $n->days === 1
            && $n->toDatabase($self->user)['title'] === trans_choice('workflow.notifications.submission_reminder.title', 1, ['period' => 'Batch Oktober', 'days' => 1]));
        Notification::assertSentTimes(SubmissionReminder::class, 4);
    }

    public function test_unpaid_fees_are_reminded_every_day_until_proof_is_uploaded(): void
    {
        $student = $this->student($this->program, StudentStatus::Approved);

        // Baru disetujui hari ini: belum diingatkan (notifikasi "disetujui" sudah cukup)
        $this->artisan('sipma:reminders');
        Notification::assertNotSentTo($student->user, PaymentReminder::class);

        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTo($student->user, PaymentReminder::class, fn (PaymentReminder $n): bool => $n->feeTypes === [PaymentType::AdmissionFee->value]
            && str_contains($n->toDatabase($student->user)['body'], PaymentType::AdmissionFee->getLabel()));

        $this->artisan('sipma:reminders'); // hari yang sama
        Notification::assertSentTimes(PaymentReminder::class, 1);

        $this->travel(1)->days();
        $this->artisan('sipma:reminders');
        Notification::assertSentTimes(PaymentReminder::class, 2);

        // Bukti diunggah → berhenti; bukti ditolak → diingatkan lagi
        $payment = app(StudentWorkflow::class)->submitPayment($student, PaymentType::AdmissionFee, $this->file());
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
        $student = $this->student($this->program);
        $student->update(['academic_period_id' => $this->period->id]);

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

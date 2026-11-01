<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LoaStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Student;
use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\LoaIssued;
use App\Notifications\PaymentRejected;
use App\Notifications\PaymentSubmitted;
use App\Notifications\RevisionRequested;
use App\Support\UploadLimits;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentWorkflowTest extends WorkflowTestCase
{
    private function workflow(): StudentWorkflow
    {
        return app(StudentWorkflow::class);
    }

    private function uploadRequired(Student $student): void
    {
        foreach (DocumentType::required() as $type) {
            $this->workflow()->uploadDocument($student, $type, $this->file($type));
        }
    }

    private function approveAll(Student $student): void
    {
        foreach ($student->documents as $document) {
            $this->workflow()->reviewDocument($document, DocumentStatus::Approved);
        }
    }

    /**
     * Diajukan, sedang diperiksa, dan semua dokumen wajib disetujui: tinggal approve().
     */
    private function inReview(Program $program): Student
    {
        $student = $this->student($program);
        $this->uploadRequired($student);
        $this->workflow()->submit($student);
        $this->actingAs($this->superAdmin());
        $this->workflow()->startReview($student);
        $this->approveAll($student->fresh());

        return $student->fresh();
    }

    private function expectLoaRefused(Student $student, string $reason): void
    {
        try {
            $this->workflow()->issueLoa($student, $this->pdf());
            $this->fail("LOA should be refused: {$reason}.");
        } catch (WorkflowException) {
            $this->assertSame(StudentStatus::Approved, $student->fresh()->status);
        }
    }

    public function test_full_journey_from_draft_to_loa(): void
    {
        $admin = $this->superAdmin();
        $owner = $this->studentUser();
        $student = $this->student($this->program(admissionFee: 500000), owner: $owner);

        $this->uploadRequired($student);
        $this->workflow()->submit($student);

        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
        $this->assertNotNull($student->fresh()->submitted_at);
        Notification::assertSentTo($admin, ApplicationSubmitted::class);

        $this->actingAs($admin);
        $this->workflow()->startReview($student);
        $this->approveAll($student->fresh());
        $this->workflow()->approve($student->fresh());

        $this->assertSame(StudentStatus::Approved, $student->fresh()->status);
        Notification::assertSentTo($owner, ApplicationApproved::class);

        $payment = $this->workflow()->submitPayment($student->fresh(), PaymentType::AdmissionFee, $this->pdf());
        $this->assertSame('500000.00', $payment->amount);
        Notification::assertSentTo($admin, PaymentSubmitted::class);
        $tuition = $this->workflow()->submitPayment($student->fresh(), PaymentType::TuitionFee, $this->pdf());

        $this->workflow()->verifyPayment($payment);
        $this->expectLoaRefused($student->fresh(), 'tuition is not verified yet');
        $this->workflow()->verifyPayment($tuition);
        $loa = $this->workflow()->issueLoa($student->fresh(), $this->pdf(), 'LOA/001');

        $this->assertSame(StudentStatus::LoaIssued, $student->fresh()->status);
        $this->assertSame(LoaStatus::Uploaded, $loa->status);
        Storage::disk('local')->assertExists($loa->file_path);
        Notification::assertSentTo($owner, LoaIssued::class);
    }

    public function test_submit_lists_what_is_missing(): void
    {
        $student = $this->student();
        $student->update(['permanent_address' => '']); // string kosong = belum diisi

        try {
            $this->workflow()->submit($student);
            $this->fail('Submit should be refused.');
        } catch (WorkflowException $e) {
            $keys = $e->missing->pluck('key');
            $this->assertTrue($keys->contains('permanent_address'));
            $this->assertTrue($keys->contains(DocumentType::Passport->value), 'passport scan is required');
            $this->assertCount(1 + count(DocumentType::required()), $e->missing);
        }

        $this->assertSame(StudentStatus::Draft, $student->fresh()->status);
    }

    public function test_revision_loop_reopens_only_after_reupload(): void
    {
        $owner = $this->studentUser();
        $student = $this->student(owner: $owner);
        $this->uploadRequired($student);
        $this->workflow()->submit($student);

        $this->actingAs($this->superAdmin());
        $this->workflow()->startReview($student);
        $medical = $student->documents()->where('type', DocumentType::MedicalStatement)->first();
        $this->workflow()->reviewDocument($medical, DocumentStatus::Revision, 'Missing signature');
        $this->workflow()->requestRevision($student->fresh());

        Notification::assertSentTo($owner, RevisionRequested::class);
        $this->assertSame(StudentStatus::Revision, $student->fresh()->status);

        // Belum diunggah ulang → belum bisa diajukan lagi, dan hanya dokumen itu yang kurang
        try {
            $this->workflow()->submit($student->fresh());
            $this->fail('Resubmit should wait for the re-upload.');
        } catch (WorkflowException $e) {
            $this->assertSame([DocumentType::MedicalStatement->value], $e->missing->pluck('key')->all());
            // Ditandai "perlu diperbaiki" (berkas ada, status revisi), bukan "kosong"
            $this->assertSame(DocumentStatus::Revision, $e->missing->sole()['status']);
        }

        $old = $medical->file_path;
        $this->workflow()->uploadDocument($student->fresh(), DocumentType::MedicalStatement, $this->file());
        Storage::disk('local')->assertMissing($old);
        $this->assertSame(DocumentStatus::Pending, $medical->fresh()->status);

        $this->workflow()->submit($student->fresh());
        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
    }

    public function test_enrollment_month_and_year_follow_the_first_submission(): void
    {
        $student = $this->student();
        $this->uploadRequired($student);
        $this->assertNull($student->fresh()->enrollment_month, 'drafts have no enrollment period yet');

        $this->travelTo(now()->setDate(2026, 11, 3));
        $this->workflow()->submit($student);

        $this->assertSame([11, 2026], [$student->fresh()->enrollment_month, $student->fresh()->enrollment_year]);

        // Pengajuan ulang setelah revisi di bulan lain tidak mengubah bulan masuk
        $this->actingAs($this->superAdmin());
        $this->workflow()->startReview($student->fresh());
        $this->workflow()->requestRevision($student->fresh(), 'Please re-check the address');
        $this->travelTo(now()->setDate(2027, 1, 10));
        $this->workflow()->submit($student->fresh());

        $this->assertSame([11, 2026], [$student->fresh()->enrollment_month, $student->fresh()->enrollment_year]);
    }

    public function test_enrollment_period_set_by_admin_is_kept_on_submit(): void
    {
        $student = $this->student();
        $student->update(['enrollment_month' => 2, 'enrollment_year' => 2027]);
        $this->uploadRequired($student);

        $this->workflow()->submit($student->fresh());

        $this->assertSame([2, 2027], [$student->fresh()->enrollment_month, $student->fresh()->enrollment_year]);
    }

    public function test_applications_created_as_submitted_get_an_enrollment_period(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20));
        $student = $this->student(status: StudentStatus::Submitted);

        $this->assertSame([12, 2026], [$student->fresh()->enrollment_month, $student->fresh()->enrollment_year]);

        // Dibuat langsung berstatus lanjut (seeder / impor): mengikuti submitted_at, bukan hari ini
        $approved = $this->student(status: StudentStatus::Approved);
        $approved->update(['enrollment_month' => null, 'enrollment_year' => null]);
        $approved->update(['submitted_at' => '2026-03-15 09:00:00']);
        $this->assertSame([3, 2026], [$approved->fresh()->enrollment_month, $approved->fresh()->enrollment_year]);

        // Draf tidak pernah diisi
        $draft = $this->student();
        $draft->update(['submitted_at' => '2026-03-15 09:00:00']);
        $this->assertNull($draft->fresh()->enrollment_month);
    }

    public function test_illegal_transitions_are_refused(): void
    {
        $student = $this->student();

        $this->expectException(WorkflowException::class);
        $this->workflow()->approve($student);
    }

    public function test_approval_needs_every_required_document_approved(): void
    {
        $student = $this->student();
        $this->uploadRequired($student);
        $this->workflow()->submit($student);
        $this->actingAs($this->superAdmin());
        $this->workflow()->startReview($student);

        $this->expectException(WorkflowException::class);
        $this->workflow()->approve($student->fresh());
    }

    public function test_revision_and_rejection_need_a_reason(): void
    {
        $student = $this->student();
        $this->uploadRequired($student);
        $this->workflow()->submit($student);
        $this->actingAs($this->superAdmin());
        $this->workflow()->startReview($student);

        $this->expectException(WorkflowException::class);
        $this->workflow()->reviewDocument($student->documents()->first(), DocumentStatus::Revision);
    }

    public function test_upload_rejects_files_over_the_configured_limit_and_wrong_photo_type(): void
    {
        $student = $this->student();

        // Bawaan 1 MB (Sistem → Unggahan berkas)
        $this->workflow()->uploadDocument($student, DocumentType::Declaration, $this->pdf(1024));
        $this->assertRejected(fn () => $this->workflow()->uploadDocument($student, DocumentType::Declaration, $this->pdf(1025)));

        // Batas yang diatur Super Admin langsung berlaku, per jenis dokumen; bukti bayar punya batas sendiri
        UploadLimits::save([DocumentType::Declaration->value => 300]);
        $this->assertRejected(fn () => $this->workflow()->uploadDocument($student, DocumentType::Declaration, $this->pdf(301)));
        $this->workflow()->uploadDocument($student, DocumentType::MedicalStatement, $this->pdf(1024));
        $this->assertSame(2048, UploadLimits::paymentProof());
        $this->assertSame('300 KB', UploadLimits::label(UploadLimits::document(DocumentType::Declaration)));

        $this->assertRejected(fn () => $this->workflow()->uploadDocument($student, DocumentType::Photo, $this->pdf()));
    }

    private function assertRejected(callable $upload): void
    {
        try {
            $upload();
            $this->fail('Upload accepted.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_loa_waits_for_verified_payments_when_the_program_charges_fees(): void
    {
        $student = $this->student($this->program(admissionFee: 500000), StudentStatus::Approved);
        $this->actingAs($this->superAdmin());

        $this->assertSame([PaymentType::AdmissionFee, PaymentType::TuitionFee], $this->workflow()->outstandingPayments($student)->all());

        $this->expectException(WorkflowException::class);
        $this->workflow()->issueLoa($student, $this->pdf());
    }

    public function test_approve_needs_program_fees_above_zero(): void
    {
        $student = $this->inReview($this->program(tuitionFee: 0));

        try {
            $this->workflow()->approve($student);
            $this->fail('Approval should be refused while a fee is Rp 0.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString(PaymentType::TuitionFee->getLabel(), $e->getMessage());
        }

        $this->assertSame(StudentStatus::InReview, $student->fresh()->status);

        $student->program->update(['tuition_fee' => 1_000_000]);
        $this->workflow()->approve($student->fresh());
        $this->assertSame(StudentStatus::Approved, $student->fresh()->status);
    }

    public function test_approve_needs_a_va_account_for_every_fee(): void
    {
        PaymentAccount::query()->delete();
        PaymentAccount::create(['bank_name' => 'BPD Bali', 'account_name' => 'Unud', 'va_number' => '8808 1', 'fee_type' => PaymentType::AdmissionFee, 'is_active' => true]);
        PaymentAccount::create(['bank_name' => 'BPD Bali', 'account_name' => 'Unud', 'va_number' => '8808 2', 'fee_type' => PaymentType::TuitionFee, 'is_active' => false]);
        $student = $this->inReview($this->program());

        try {
            $this->workflow()->approve($student);
            $this->fail('Approval should be refused without an active VA for tuition.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString(PaymentType::TuitionFee->getLabel(), $e->getMessage());
            $this->assertStringNotContainsString(PaymentType::AdmissionFee->getLabel(), $e->getMessage());
        }

        PaymentAccount::query()->where('va_number', '8808 2')->update(['is_active' => true]);
        $this->workflow()->approve($student->fresh());
        $this->assertSame(StudentStatus::Approved, $student->fresh()->status);
    }

    public function test_loa_numbers_are_automatic_sequential_and_kept_on_reupload(): void
    {
        $this->actingAs($this->superAdmin());
        $year = now()->year;

        $first = $this->workflow()->issueLoa($this->paid($this->student(status: StudentStatus::Approved)), $this->pdf());
        $second = $this->workflow()->issueLoa($this->paid($this->student(status: StudentStatus::Approved)), $this->pdf());
        $manual = $this->workflow()->issueLoa($this->paid($this->student(status: StudentStatus::Approved)), $this->pdf(), 'KUI/123/2026');

        $this->assertSame("LOA/SIPMA/{$year}/0001", $first->loa_number);
        $this->assertSame("LOA/SIPMA/{$year}/0002", $second->loa_number);
        $this->assertSame('KUI/123/2026', $manual->loa_number);

        // Unggah ulang untuk pendaftar yang sama tetap memakai nomornya
        $student = $first->student;
        $student->update(['status' => StudentStatus::Approved]);
        $again = $this->workflow()->issueLoa($student->fresh(), $this->pdf());
        $this->assertSame("LOA/SIPMA/{$year}/0001", $again->loa_number);

        // Tahun baru mulai dari 0001 lagi
        $this->travelTo(now()->addYear()->startOfYear());
        $next = $this->workflow()->issueLoa($this->paid($this->student(status: StudentStatus::Approved)), $this->pdf());
        $this->assertSame('LOA/SIPMA/'.($year + 1).'/0001', $next->loa_number);
    }

    public function test_payment_only_after_approval_and_rejection_notifies_owner(): void
    {
        $owner = $this->studentUser();
        $draft = $this->student($this->program(admissionFee: 100000), owner: $owner);

        try {
            $this->workflow()->submitPayment($draft, PaymentType::AdmissionFee, $this->pdf());
            $this->fail('Payment accepted before approval.');
        } catch (WorkflowException) {
        }

        $draft->update(['status' => StudentStatus::Approved]);
        $payment = $this->workflow()->submitPayment($draft->fresh(), PaymentType::AdmissionFee, $this->pdf());

        $this->actingAs($this->superAdmin());
        $this->workflow()->rejectPayment($payment, 'Amount does not match');

        $this->assertSame(PaymentStatus::Rejected, $payment->fresh()->status);
        Notification::assertSentTo($owner, PaymentRejected::class);

        // Bukti baru menggantikan yang ditolak, kembali menunggu verifikasi
        $again = $this->workflow()->submitPayment($draft->fresh(), PaymentType::AdmissionFee, $this->pdf());
        $this->assertTrue($again->is($payment));
        $this->assertSame(PaymentStatus::Pending, $again->status);
    }

    public function test_agent_owner_receives_the_notification(): void
    {
        $agent = $this->agent();
        $student = $this->student();
        $student->update(['user_id' => null, 'agent_id' => $agent->id]);
        $this->uploadRequired($student);
        $this->workflow()->submit($student);
        $this->actingAs($this->superAdmin());
        $this->workflow()->startReview($student);

        $this->workflow()->requestRevision($student->fresh(), 'Please check the passport scan.');

        Notification::assertSentTo($agent->user, RevisionRequested::class);
    }
}

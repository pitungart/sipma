<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LoaStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\LoaIssued;
use App\Notifications\PaymentRejected;
use App\Notifications\PaymentSubmitted;
use App\Notifications\RevisionRequested;
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

        $this->workflow()->verifyPayment($payment);
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
        }

        $old = $medical->file_path;
        $this->workflow()->uploadDocument($student->fresh(), DocumentType::MedicalStatement, $this->file());
        Storage::disk('local')->assertMissing($old);
        $this->assertSame(DocumentStatus::Pending, $medical->fresh()->status);

        $this->workflow()->submit($student->fresh());
        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
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

    public function test_upload_rejects_files_over_300_kb_and_wrong_photo_type(): void
    {
        $student = $this->student();

        try {
            $this->workflow()->uploadDocument($student, DocumentType::Declaration, $this->pdf(301));
            $this->fail('Oversized file accepted.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $this->workflow()->uploadDocument($student, DocumentType::Photo, $this->pdf());
    }

    public function test_loa_waits_for_verified_payments_when_the_program_charges_fees(): void
    {
        $student = $this->student($this->program(admissionFee: 500000), StudentStatus::Approved);
        $this->actingAs($this->superAdmin());

        $this->assertSame([PaymentType::AdmissionFee], $this->workflow()->outstandingPayments($student)->all());

        $this->expectException(WorkflowException::class);
        $this->workflow()->issueLoa($student, $this->pdf());
    }

    public function test_free_programs_need_no_payment_before_loa(): void
    {
        $student = $this->student($this->program(), StudentStatus::Approved);
        $this->actingAs($this->superAdmin());

        $this->assertTrue($this->workflow()->requiredPaymentTypes($student)->isEmpty());
        $this->workflow()->issueLoa($student, $this->pdf());

        $this->assertSame(StudentStatus::LoaIssued, $student->fresh()->status);
    }

    public function test_loa_numbers_are_automatic_sequential_and_kept_on_reupload(): void
    {
        $this->actingAs($this->superAdmin());
        $year = now()->year;

        $first = $this->workflow()->issueLoa($this->student(status: StudentStatus::Approved), $this->pdf());
        $second = $this->workflow()->issueLoa($this->student(status: StudentStatus::Approved), $this->pdf());
        $manual = $this->workflow()->issueLoa($this->student(status: StudentStatus::Approved), $this->pdf(), 'KUI/123/2026');

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
        $next = $this->workflow()->issueLoa($this->student(status: StudentStatus::Approved), $this->pdf());
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

    public function test_tuition_is_not_accepted_when_the_program_does_not_charge_it(): void
    {
        $student = $this->student($this->program(admissionFee: 100000), StudentStatus::Approved);

        $this->expectException(WorkflowException::class);
        $this->workflow()->submitPayment($student, PaymentType::TuitionFee, $this->pdf());
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

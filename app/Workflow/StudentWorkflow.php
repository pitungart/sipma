<?php

namespace App\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LoaStatus;
use App\Enums\NumberType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Loa;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\LoaIssued;
use App\Notifications\PaymentRejected;
use App\Notifications\PaymentSubmitted;
use App\Notifications\RevisionRequested;
use App\Support\Numbering;
use App\Support\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * Satu-satunya tempat yang mengubah status pendaftaran (keputusan 6 Oktober 2026):
 *
 *   draft ─submit→ submitted ─startReview→ in_review ─approve→ approved ─issueLoa→ loa_issued
 *                     ↑                        │
 *                     └──── submit ── revision ←┘ requestRevision
 *
 * Pembayaran terjadi SETELAH disetujui (template), menyimpang dari BPMN to-be yang membayar
 * sebelum submit. LOA hanya bisa diterbitkan setelah semua biaya program (> Rp 0) terverifikasi.
 *
 * Service ini hanya menjaga keadaan; siapa yang boleh memanggilnya diperiksa Policy di layar.
 * Activity log terisi otomatis oleh model (causer = pengguna yang sedang masuk).
 */
final class StudentWorkflow
{
    // ── Pemilik (mahasiswa / agen) ───────────────────────────────────────────

    /**
     * UC-08 / UC-03: unggah atau unggah ulang satu dokumen. Satu berkas per jenis (kecuali
     * "lainnya"); versi lama yang belum disetujui diganti dan statusnya kembali "menunggu".
     */
    public function uploadDocument(Student $student, DocumentType $type, UploadedFile $file): Document
    {
        $this->expect($student, [StudentStatus::Draft, StudentStatus::Revision], 'upload_document');

        Validator::validate(['file' => $file], ['file' => $type->uploadRules()]);

        $existing = $type === DocumentType::Other
            ? null
            : $student->documents()->where('type', $type)->latest()->first();

        if ($existing?->status === DocumentStatus::Approved) {
            throw WorkflowException::because('document_locked', ['document' => $type->getLabel()]);
        }

        $stored = PrivateFiles::store($file, PrivateFiles::studentDirectory($student->getKey(), 'documents'));

        $attributes = [
            ...$stored,
            'type' => $type,
            'status' => DocumentStatus::Pending,
            'revision_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];

        if ($existing) {
            $old = $existing->file_path;
            $existing->update($attributes);
            PrivateFiles::delete($old);

            return $existing;
        }

        return $student->documents()->create($attributes);
    }

    /**
     * UC-11: ajukan (atau ajukan ulang setelah revisi). Ditolak bila checklist belum lengkap.
     */
    public function submit(Student $student): Student
    {
        $this->expect($student, [StudentStatus::Draft, StudentStatus::Revision], 'submit');

        $checklist = SubmissionChecklist::for($student);

        if (! $checklist->isComplete()) {
            throw WorkflowException::incomplete($checklist->missing());
        }

        $resubmitted = $student->status === StudentStatus::Revision;

        $student->update([
            'status' => StudentStatus::Submitted,
            'submitted_at' => now(),
            // Nomor pendaftaran diberikan saat pertama kali diajukan; pengajuan ulang tetap memakainya
            'registration_number' => $student->registration_number ?? Numbering::next(NumberType::Application),
        ]);

        Notification::send($this->kui(), new ApplicationSubmitted($student, $resubmitted));

        return $student;
    }

    /**
     * UC-10: unggah bukti bayar satu jenis biaya, hanya setelah pendaftaran disetujui.
     */
    public function submitPayment(Student $student, PaymentType $type, UploadedFile $proof): Payment
    {
        $this->expect($student, [StudentStatus::Approved], 'pay');

        if (! $this->requiredPaymentTypes($student)->contains($type)) {
            throw WorkflowException::because('payment_not_required', ['type' => $type->getLabel()]);
        }

        Validator::validate(['file' => $proof], ['file' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:'.Document::MAX_FILE_SIZE_KB]]);

        $existing = $student->payments()->where('type', $type)->latest()->first();

        if ($existing?->status === PaymentStatus::Verified) {
            throw WorkflowException::because('payment_already_verified', ['type' => $type->getLabel()]);
        }

        $account = PaymentAccount::query()
            ->for($type, $student->program_id)
            ->orderByRaw('program_id is null, fee_type is null') // rekening paling spesifik dulu
            ->first();

        $attributes = [
            'type' => $type,
            'amount' => $this->feeFor($student, $type),
            'payment_account_id' => $account?->getKey(),
            'va_number' => $account?->va_number, // salinan historis
            'proof_file' => PrivateFiles::store($proof, PrivateFiles::studentDirectory($student->getKey(), 'payments'))['file_path'],
            'status' => PaymentStatus::Pending,
            'rejection_note' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];

        if ($existing) {
            $old = $existing->proof_file;
            $existing->update($attributes);
            PrivateFiles::delete($old);
            $payment = $existing;
        } else {
            $payment = $student->payments()->create($attributes);
        }

        Notification::send($this->kui(), new PaymentSubmitted($payment));

        return $payment;
    }

    // ── Staf KUI (Super Admin) ───────────────────────────────────────────────

    /**
     * UC-18: mulai memeriksa pendaftaran yang masuk.
     */
    public function startReview(Student $student): Student
    {
        $this->expect($student, [StudentStatus::Submitted], 'start_review');

        $student->update(['status' => StudentStatus::InReview]);

        return $student;
    }

    /**
     * UC-16 / UC-17: tandai satu dokumen. Revisi & tolak wajib beralasan (R-3.9).
     */
    public function reviewDocument(Document $document, DocumentStatus $status, ?string $note = null): Document
    {
        $this->expect($document->student, [StudentStatus::InReview], 'review_document');

        if ($status === DocumentStatus::Pending) {
            throw WorkflowException::because('invalid_review');
        }

        if ($status !== DocumentStatus::Approved && blank($note)) {
            throw WorkflowException::because('note_required');
        }

        $document->update([
            'status' => $status,
            'revision_note' => $status === DocumentStatus::Approved ? null : $note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $document;
    }

    /**
     * UC-17: kembalikan ke pemilik. Butuh minimal satu dokumen bertanda revisi/tolak atau catatan umum.
     */
    public function requestRevision(Student $student, ?string $note = null): Student
    {
        $this->expect($student, [StudentStatus::InReview], 'request_revision');

        $flagged = $student->documents()
            ->whereIn('status', [DocumentStatus::Revision, DocumentStatus::Rejected])
            ->exists();

        if (! $flagged && blank($note)) {
            throw WorkflowException::because('revision_reason_required');
        }

        $student->update(['status' => StudentStatus::Revision, 'revision_note' => $note]);

        $student->owner()?->notify(new RevisionRequested($student));

        return $student;
    }

    /**
     * UC-18: setujui. Semua dokumen wajib harus sudah disetujui per dokumen.
     */
    public function approve(Student $student): Student
    {
        $this->expect($student, [StudentStatus::InReview], 'approve');

        $approved = $student->documents()
            ->where('status', DocumentStatus::Approved)
            ->pluck('type')
            ->map(fn (DocumentType $type): string => $type->value);

        $pending = collect(DocumentType::required())
            ->reject(fn (DocumentType $type): bool => $approved->contains($type->value));

        if ($pending->isNotEmpty()) {
            throw WorkflowException::because('documents_not_approved', [
                'documents' => $pending->map->getLabel()->implode(', '),
            ]);
        }

        $student->update(['status' => StudentStatus::Approved, 'revision_note' => null]);

        $student->owner()?->notify(new ApplicationApproved($student));

        return $student;
    }

    /**
     * UC-20: verifikasi bukti bayar.
     */
    public function verifyPayment(Payment $payment): Payment
    {
        $this->expectPending($payment);

        $payment->update([
            'status' => PaymentStatus::Verified,
            'receipt_number' => $payment->receipt_number ?? Numbering::next(NumberType::Receipt),
            'rejection_note' => null,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return $payment;
    }

    public function rejectPayment(Payment $payment, string $note): Payment
    {
        $this->expectPending($payment);

        if (blank($note)) {
            throw WorkflowException::because('note_required');
        }

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'rejection_note' => $note,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        $payment->student->owner()?->notify(new PaymentRejected($payment));

        return $payment;
    }

    /**
     * UC-26: unggah LOA bertanda tangan. Hanya setelah disetujui dan semua biaya terverifikasi.
     * Nomor LOA otomatis (Sistem → Penomoran) bila staf tidak mengisinya.
     */
    public function issueLoa(Student $student, UploadedFile $file, ?string $loaNumber = null): Loa
    {
        $this->expect($student, [StudentStatus::Approved], 'issue_loa');

        if (($outstanding = $this->outstandingPayments($student))->isNotEmpty()) {
            throw WorkflowException::because('payments_outstanding', [
                'types' => $outstanding->map->getLabel()->implode(', '),
            ]);
        }

        Validator::validate(['file' => $file], ['file' => ['file', 'mimes:pdf', 'max:5120']]);

        return DB::transaction(function () use ($student, $file, $loaNumber): Loa {
            $old = $student->loa?->file_path;

            // Nomor manual menang; tanpa itu, unggah ulang memakai nomor lama, LOA baru dapat nomor otomatis.
            $number = filled($loaNumber) ? $loaNumber : ($student->loa?->loa_number ?? Numbering::next(NumberType::Loa));

            $loa = $student->loa()->updateOrCreate([], [
                'loa_number' => $number,
                'file_path' => PrivateFiles::store($file, PrivateFiles::studentDirectory($student->getKey(), 'loa'))['file_path'],
                'status' => LoaStatus::Uploaded,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'downloaded_at' => null,
            ]);

            if ($old !== $loa->file_path) {
                PrivateFiles::delete($old);
            }

            $student->update(['status' => StudentStatus::LoaIssued]);

            DB::afterCommit(fn () => $student->owner()?->notify(new LoaIssued($student)));

            return $loa;
        });
    }

    // ── Pembayaran: aturan bersama ───────────────────────────────────────────

    /**
     * Jenis biaya yang wajib dibayar: biaya program yang nominalnya lebih dari Rp 0.
     * Selama biaya resmi belum ditetapkan KUI (seeder Rp 0), tidak ada yang wajib dibayar.
     *
     * @return Collection<int, PaymentType>
     */
    public function requiredPaymentTypes(Student $student): Collection
    {
        return collect(PaymentType::cases())
            ->filter(fn (PaymentType $type): bool => (float) $this->feeFor($student, $type) > 0)
            ->values();
    }

    /**
     * @return Collection<int, PaymentType>
     */
    public function outstandingPayments(Student $student): Collection
    {
        $verified = $student->payments()
            ->where('status', PaymentStatus::Verified)
            ->pluck('type')
            ->map(fn (PaymentType $type): string => $type->value);

        return $this->requiredPaymentTypes($student)
            ->reject(fn (PaymentType $type): bool => $verified->contains($type->value))
            ->values();
    }

    private function feeFor(Student $student, PaymentType $type): string
    {
        return (string) ($student->program?->{$type->value} ?? '0');
    }

    // ── Pembantu ─────────────────────────────────────────────────────────────

    /**
     * @param  list<StudentStatus>  $allowed
     */
    private function expect(Student $student, array $allowed, string $action): void
    {
        if (! in_array($student->status, $allowed, true)) {
            throw WorkflowException::transition($student->status, $action);
        }
    }

    private function expectPending(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw WorkflowException::because('payment_not_pending');
        }
    }

    /**
     * Penerima notifikasi sisi KUI: semua Super Admin aktif.
     *
     * @return Collection<int, User>
     */
    private function kui(): Collection
    {
        return User::query()->where('role', UserRole::SuperAdmin)->where('is_active', true)->get();
    }
}

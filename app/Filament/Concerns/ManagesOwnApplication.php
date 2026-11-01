<?php

namespace App\Filament\Concerns;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Filament\Support\FormModal;
use App\Filament\Support\TemplateDownload;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Student;
use App\Support\ApplicantDetails;
use App\Support\FileTemplates;
use App\Support\UploadLimits;
use App\Workflow\StudentWorkflow;
use App\Workflow\SubmissionChecklist;
use App\Workflow\WorkflowException;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Detail pendaftaran dari sisi PEMILIK (agen untuk mahasiswanya, mahasiswa mandiri untuk
 * dirinya): kelengkapan & pengajuan (UC-11), dokumen (UC-08, UC-03), pembayaran (UC-10),
 * dan LOA (UC-02). Dipakai halaman agen dan panel /app agar aturannya satu.
 *
 * Semua argumen dari browser (dokumen, pembayaran) dicari di dalam pendaftaran ini saja.
 */
trait ManagesOwnApplication
{
    abstract protected function applicant(): Student;

    /**
     * Teks persetujuan data saat mengajukan (UU PDP); berbeda untuk agen dan mahasiswa.
     */
    abstract protected function consentLabel(): string;

    /**
     * Judul halaman diikuti badge status, agar status terbaca tanpa menggulir.
     */
    public function getHeading(): string|Htmlable
    {
        return new HtmlString(
            '<span class="sipma-heading-status">'.e($this->getTitle())
            .Blade::render('<x-sipma.status-badge :status="$status" />', ['status' => $this->applicant()->status])
            .'</span>'
        );
    }

    public function canChange(): bool
    {
        return Gate::allows('update', $this->applicant());
    }

    // ── Aksi kepala halaman ──────────────────────────────────────────────────

    /**
     * UC-11: persetujuan data (UU PDP) diminta sebelum data dikunci.
     */
    protected function submitAction(): Action
    {
        return FormModal::apply(Action::make('submit'))
            ->label(__('admin.applicant.actions.submit'))
            ->icon('lucide-send')
            ->visible(fn (): bool => $this->canChange())
            ->disabled(fn (): bool => ! $this->checklist()->isComplete())
            ->tooltip(fn (): ?string => $this->checklist()->isComplete()
                ? null
                : __('workflow.errors.incomplete', ['count' => $this->checklist()->missing()->count()]))
            ->modalHeading(fn (): string => __('admin.applicant.submit_heading', ['name' => $this->applicant()->full_name]))
            ->modalDescription(__('agent.students.submit_description'))
            ->modalIcon('lucide-send')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.applicant.actions.submit'))
            ->form([
                Checkbox::make('consent')
                    ->label(fn (): string => $this->consentLabel())
                    ->accepted(),
            ])
            ->action(fn () => $this->run(fn (StudentWorkflow $w) => $w->submit($this->applicant()), 'submitted'));
    }

    /**
     * UC-02: LOA bisa diunduh pemilik begitu terbit (unduhan pertama dicatat).
     */
    protected function downloadLoaAction(): Action
    {
        return Action::make('downloadLoa')
            ->label(__('agent.students.payments.download_loa'))
            ->icon('lucide-download')
            ->visible(fn (): bool => $this->applicant()->loa !== null && Gate::allows('download', $this->applicant()->loa))
            ->url(fn (): string => route('files.loa', $this->applicant()->loa));
    }

    // ── Aksi per baris (dipanggil dari view dengan argumen) ──────────────────

    /**
     * UC-08 / UC-03: unggah atau ganti satu dokumen selama draf / perlu revisi. Dokumen yang
     * sudah disetujui KUI tidak bisa diganti. Ketentuan format tampil sebelum unggah (R-3.8).
     */
    public function uploadDocumentAction(): Action
    {
        $type = fn (array $arguments): DocumentType => DocumentType::from($arguments['type'] ?? '');

        return FormModal::apply(Action::make('uploadDocument'))
            ->label(fn (array $arguments): string => $this->applicant()->documents()->where('type', $type($arguments))->exists()
                ? __('admin.applicant.actions.replace_document')
                : __('admin.applicant.actions.upload_document'))
            // "Unggah" dan "Ganti" sama-sama primary solid; ganti berikon panah memutar
            ->icon(fn (array $arguments): string => $this->applicant()->documents()->where('type', $type($arguments))->exists() ? 'lucide-refresh-cw' : 'lucide-upload')
            ->color('primary')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('admin.applicant.actions.upload_document').' · '.$type($arguments)->getLabel())
            ->modalDescription(fn (array $arguments): string|Htmlable => TemplateDownload::uploadHint($type($arguments)))
            ->modalIcon('lucide-upload')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->form(fn (array $arguments): array => [
                FileUpload::make('file')
                    ->label(__('admin.applicant.fields.document_file'))
                    ->acceptedFileTypes(collect($type($arguments)->extensions())
                        ->map(fn (string $ext): string => $ext === 'pdf' ? 'application/pdf' : 'image/'.($ext === 'jpg' ? 'jpeg' : $ext))
                        ->unique()->values()->all())
                    ->maxSize(UploadLimits::document($type($arguments)))
                    ->storeFiles(false) // StudentWorkflow menyimpannya di disk privat
                    ->required(),
            ])
            ->action(fn (array $arguments, array $data) => $this->run(
                fn (StudentWorkflow $w) => $w->uploadDocument($this->applicant(), $type($arguments), $data['file']),
                'document_uploaded',
            ));
    }

    /**
     * UC-10: unggah / ganti bukti bayar satu jenis biaya setelah disetujui (keputusan 8 Oktober
     * 2026: satu bukti per mahasiswa per jenis biaya). Bukti terverifikasi tidak bisa diganti.
     */
    public function uploadPaymentAction(): Action
    {
        $type = fn (array $arguments): PaymentType => PaymentType::from($arguments['type'] ?? '');

        return FormModal::apply(Action::make('uploadPayment'))
            ->label(fn (array $arguments): string => $this->applicant()->payments()->where('type', $type($arguments))->exists()
                ? __('agent.students.payments.replace')
                : __('agent.students.payments.upload'))
            ->icon(fn (array $arguments): string => $this->applicant()->payments()->where('type', $type($arguments))->exists() ? 'lucide-refresh-cw' : 'lucide-upload')
            ->color('primary')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('agent.students.payments.upload').' · '.$type($arguments)->getLabel())
            ->modalDescription(fn (): string => __('agent.students.payments.proof_hint', ['max' => UploadLimits::label(UploadLimits::paymentProof())]))
            ->modalIcon('lucide-receipt')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('agent.students.payments.send'))
            ->form([
                FileUpload::make('file')
                    ->label(__('agent.students.payments.proof'))
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(fn (): int => UploadLimits::paymentProof())
                    ->storeFiles(false) // StudentWorkflow menyimpannya di disk privat
                    ->required(),
            ])
            ->action(fn (array $arguments, array $data) => $this->run(
                fn (StudentWorkflow $w) => $w->submitPayment($this->applicant(), $type($arguments), $data['file']),
                'payment_uploaded',
                ability: 'pay',
            ));
    }

    public function previewPaymentAction(): Action
    {
        return Action::make('previewPayment')
            ->label(__('agent.students.payments.view_proof'))
            ->icon('lucide-eye')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => $this->payment($arguments)->type->getLabel())
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.admin.applicants.preview', [
                'url' => route('files.payment', $this->payment($arguments)),
                'image' => ! str_ends_with((string) $this->payment($arguments)->proof_file, '.pdf'),
                'title' => $this->payment($arguments)->type->getLabel(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.applicant.actions.close'));
    }

    /**
     * Unduh template dokumen (hanya unduh; dikelola Super Admin di Sistem → Unggahan berkas).
     */
    public function downloadTemplateAction(): Action
    {
        return TemplateDownload::action();
    }

    public function previewDocumentAction(): Action
    {
        return Action::make('previewDocument')
            ->label(__('admin.applicant.actions.preview'))
            ->icon('lucide-eye')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => $this->document($arguments)->type->getLabel())
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.admin.applicants.preview', [
                'url' => route('files.document', $this->document($arguments)),
                'image' => str_starts_with((string) $this->document($arguments)->mime_type, 'image/'),
                'title' => $this->document($arguments)->type->getLabel(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.applicant.actions.close'));
    }

    // ── Data untuk partial filament.applicant.detail ─────────────────────────

    /**
     * @return array<string, mixed>
     */
    protected function applicationViewData(bool $withSource = false): array
    {
        $student = $this->applicant()->loadMissing(['program', 'nationality', 'homeUniversityCountry', 'loa']);
        $documents = ApplicantDetails::documents($student);

        return [
            'applicant' => $student,
            'canChange' => $this->canChange(),
            'profile' => ApplicantDetails::profile($student, withSource: $withSource),
            'documents' => $documents,
            'templates' => FileTemplates::uploaded(),
            'requiredDone' => $documents->filter(fn (array $item): bool => $item['type']->isRequired()
                && in_array($item['document']?->status, [DocumentStatus::Pending, DocumentStatus::Approved], true))->count(),
            'checklist' => $this->canChange() ? $this->checklist()->items() : new Collection,
            'paymentsOpen' => in_array($student->status, [StudentStatus::Approved, StudentStatus::LoaIssued], true),
            'canPay' => $student->status === StudentStatus::Approved,
            'fees' => ApplicantDetails::fees($student),
            'loa' => $student->loa,
        ];
    }

    protected function checklist(): SubmissionChecklist
    {
        return SubmissionChecklist::for($this->applicant());
    }

    /**
     * Jalankan langkah alur atas nama pemilik; kesalahan alur tampil sebagai notifikasi.
     * `update` = ubah data/dokumen (draf, perlu revisi); `pay` = unggah bukti bayar (pemilik,
     * status dijaga StudentWorkflow).
     */
    protected function run(Closure $step, string $successKey, string $ability = 'update'): void
    {
        $student = $this->applicant();

        $ability === 'pay'
            ? abort_unless($student->isOwnedBy(auth()->user()) && Gate::allows('create', Payment::class), 403)
            : Gate::authorize('update', $student);

        try {
            app()->call($step);
        } catch (WorkflowException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->body($e->missing->isNotEmpty() ? $e->missing->pluck('label')->map(fn (string $l): string => '• '.e($l))->implode('<br>') : null)
                ->persistent($e->missing->isNotEmpty())
                ->send();

            return;
        }

        $student->refresh();

        Notification::make()->success()->title(__("agent.students.done.{$successKey}"))->send();
    }

    private function payment(array $arguments): Payment
    {
        $payment = $this->applicant()->payments()->find($arguments['payment'] ?? null);
        abort_if($payment === null, 404);

        return $payment;
    }

    private function document(array $arguments): Document
    {
        $document = $this->applicant()->documents()->find($arguments['document'] ?? null);
        abort_if($document === null, 404);

        return $document;
    }
}

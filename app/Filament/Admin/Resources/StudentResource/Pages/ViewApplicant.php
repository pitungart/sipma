<?php

namespace App\Filament\Admin\Resources\StudentResource\Pages;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\NumberType;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Support\FormModal;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Student;
use App\Support\ActivityDescriber;
use App\Support\Numbering;
use App\Support\Rupiah;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Detail pendaftar (halaman penuh): garis waktu + tab Profil · Dokumen · Pembayaran · Riwayat.
 * Aksi kepala mengikuti StudentWorkflow (UC-16–UC-18, UC-26); dokumen & pembayaran hanya untuk
 * pemegang ability `verify` — Admin Fakultas melihat data ringkas saja (keputusan 6 Oktober 2026).
 *
 * @property Student $record
 */
class ViewApplicant extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected static string $view = 'filament.admin.applicants.view';

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name;
    }

    public function getSubheading(): ?string
    {
        return collect([$this->record->registration_number, $this->record->passport_number, $this->record->program?->name, $this->record->agent?->company_name ?? __('admin.dashboard.source_self')])
            ->filter()
            ->implode(' · ');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('admin.groups.admissions'),
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            $this->record->full_name,
        ];
    }

    public function canVerify(): bool
    {
        return Gate::allows('verify', $this->record);
    }

    // ── Aksi status (kepala halaman) ─────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $visibleWhen = fn (StudentStatus ...$statuses): Closure => fn (): bool => $this->canVerify()
            && in_array($this->record->status, $statuses, true);

        return [
            // Atas nama pendaftar (keputusan #6): ubah data & ajukan selama draf / perlu revisi
            Action::make('editApplicant')
                ->label(__('admin.applicant.actions.edit'))
                ->icon('lucide-pencil')
                ->color('gray')
                ->visible(fn (): bool => StudentResource::canEdit($this->record))
                ->url(fn (): string => StudentResource::getUrl('edit', ['record' => $this->record])),

            Action::make('submit')
                ->label(__('admin.applicant.actions.submit'))
                ->icon('lucide-send')
                ->visible($visibleWhen(StudentStatus::Draft, StudentStatus::Revision))
                ->requiresConfirmation()
                ->modalHeading(__('admin.applicant.submit_heading', ['name' => $this->record->full_name]))
                ->modalDescription(__('admin.applicant.submit_description'))
                ->modalIcon('lucide-send')
                ->modalSubmitActionLabel(__('admin.applicant.actions.submit'))
                ->action(fn () => $this->run(fn (StudentWorkflow $w) => $w->submit($this->record), 'submitted')),

            Action::make('startReview')
                ->label(__('admin.applicant.actions.start_review'))
                ->icon('lucide-search-check')
                ->visible($visibleWhen(StudentStatus::Submitted))
                ->action(fn () => $this->run(fn (StudentWorkflow $w) => $w->startReview($this->record), 'started_review')),

            FormModal::apply(Action::make('requestRevision'))
                ->label(__('admin.applicant.actions.request_revision'))
                ->icon('lucide-undo-2')
                ->color('gray')
                ->visible($visibleWhen(StudentStatus::InReview))
                ->modalHeading(__('admin.applicant.actions.request_revision'))
                ->modalDescription(__('admin.applicant.revision_modal'))
                ->modalIcon('lucide-undo-2')
                ->modalIconColor('danger')
                ->modalWidth(MaxWidth::Large)
                ->modalSubmitActionLabel(__('admin.applicant.actions.send_revision'))
                ->form([
                    Textarea::make('note')
                        ->label(__('admin.applicant.fields.general_note'))
                        ->helperText(__('admin.applicant.general_note_hint'))
                        ->rows(3),
                ])
                ->action(fn (array $data) => $this->run(fn (StudentWorkflow $w) => $w->requestRevision($this->record, $data['note'] ?? null), 'revision_sent')),

            Action::make('approve')
                ->label(__('admin.applicant.actions.approve'))
                ->icon('lucide-circle-check')
                ->visible($visibleWhen(StudentStatus::InReview))
                ->disabled(fn (): bool => $this->unapprovedRequired()->isNotEmpty())
                ->tooltip(fn (): ?string => $this->unapprovedRequired()->isNotEmpty()
                    ? __('workflow.errors.documents_not_approved', ['documents' => $this->unapprovedRequired()->map->getLabel()->implode(', ')])
                    : null)
                ->requiresConfirmation()
                ->modalHeading(__('admin.applicant.approve_heading', ['name' => $this->record->full_name]))
                ->modalDescription(__('admin.applicant.approve_description'))
                ->modalIcon('lucide-circle-check')
                ->modalSubmitActionLabel(__('admin.applicant.actions.approve'))
                ->action(fn () => $this->run(fn (StudentWorkflow $w) => $w->approve($this->record), 'approved')),

            FormModal::apply(Action::make('issueLoa'))
                ->label(__('admin.applicant.actions.issue_loa'))
                ->icon('lucide-file-up')
                ->visible($visibleWhen(StudentStatus::Approved))
                ->disabled(fn (): bool => $this->outstanding()->isNotEmpty())
                ->tooltip(fn (): ?string => $this->outstanding()->isNotEmpty()
                    ? __('workflow.errors.payments_outstanding', ['types' => $this->outstanding()->map->getLabel()->implode(', ')])
                    : null)
                ->modalHeading(__('admin.applicant.actions.issue_loa'))
                ->modalDescription(__('admin.applicant.loa_modal', ['name' => $this->record->full_name]))
                ->modalIcon('lucide-file-up')
                ->modalWidth(MaxWidth::Large)
                ->modalSubmitActionLabel(__('admin.applicant.actions.issue_loa_submit'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('admin.applicant.fields.loa_file'))
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(5120)
                        ->storeFiles(false) // diteruskan ke StudentWorkflow yang menyimpannya di disk privat
                        ->required(),
                    TextInput::make('loa_number')
                        ->label(__('admin.applicant.fields.loa_number'))
                        ->placeholder(fn (): string => $this->record->loa?->loa_number ?? Numbering::preview(NumberType::Loa))
                        ->helperText(fn (): string => $this->record->loa?->loa_number
                            ? __('admin.applicant.loa_number_keep', ['number' => $this->record->loa->loa_number])
                            : __('admin.applicant.loa_number_hint'))
                        ->maxLength(100)
                        ->unique('loas', 'loa_number', ignorable: fn () => $this->record->loa),
                ])
                ->action(fn (array $data) => $this->run(
                    fn (StudentWorkflow $w) => $w->issueLoa($this->record, $data['file'], $data['loa_number'] ?: null),
                    'loa_issued',
                )),
        ];
    }

    // ── Aksi per dokumen (tab Dokumen) ───────────────────────────────────────

    /**
     * Unggah / ganti dokumen atas nama pendaftar (draf atau perlu revisi). Ketentuan format
     * tampil sebelum kontrol unggah (R-3.8).
     */
    public function uploadDocumentAction(): Action
    {
        $type = fn (array $arguments): DocumentType => DocumentType::from($arguments['type'] ?? '');

        return FormModal::apply(Action::make('uploadDocument'))
            ->label(fn (array $arguments): string => $this->record->documents()->where('type', $type($arguments))->exists()
                ? __('admin.applicant.actions.replace_document')
                : __('admin.applicant.actions.upload_document'))
            ->icon('lucide-upload')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('admin.applicant.actions.upload_document').' · '.$type($arguments)->getLabel())
            ->modalDescription(fn (array $arguments): string => $type($arguments)->hint())
            ->modalIcon('lucide-upload')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->form(fn (array $arguments): array => [
                FileUpload::make('file')
                    ->label(__('admin.applicant.fields.document_file'))
                    ->acceptedFileTypes(collect($type($arguments)->extensions())
                        ->map(fn (string $ext): string => $ext === 'pdf' ? 'application/pdf' : 'image/'.($ext === 'jpg' ? 'jpeg' : $ext))
                        ->unique()->values()->all())
                    ->maxSize(Document::MAX_FILE_SIZE_KB)
                    ->storeFiles(false) // StudentWorkflow menyimpannya di disk privat
                    ->required(),
            ])
            ->action(function (array $arguments, array $data) use ($type): void {
                abort_unless($this->canVerify(), 403);

                $this->run(fn (StudentWorkflow $w) => $w->uploadDocument($this->record, $type($arguments), $data['file']), 'document_uploaded');
            });
    }

    public function previewDocumentAction(): Action
    {
        return $this->previewAction('previewDocument', fn (array $arguments): array => [
            'url' => route('files.document', $this->document($arguments)),
            'image' => str_starts_with((string) $this->document($arguments)->mime_type, 'image/'),
            'title' => $this->document($arguments)->type->getLabel(),
        ]);
    }

    public function approveDocumentAction(): Action
    {
        return Action::make('approveDocument')
            ->label(__('admin.applicant.actions.approve_document'))
            ->icon('lucide-check')
            ->color('success')
            ->size('sm')
            ->action(fn (array $arguments) => $this->run(
                fn (StudentWorkflow $w) => $w->reviewDocument($this->document($arguments), DocumentStatus::Approved),
                'document_approved',
            ));
    }

    public function reviseDocumentAction(): Action
    {
        return $this->documentNoteAction('reviseDocument', DocumentStatus::Revision, 'lucide-undo-2', 'warning');
    }

    public function rejectDocumentAction(): Action
    {
        return $this->documentNoteAction('rejectDocument', DocumentStatus::Rejected, 'lucide-x', 'danger');
    }

    private function documentNoteAction(string $name, DocumentStatus $status, string $icon, string $color): Action
    {
        $key = $status === DocumentStatus::Revision ? 'revise_document' : 'reject_document';

        return FormModal::apply(Action::make($name))
            ->label(__("admin.applicant.actions.{$key}"))
            ->icon($icon)
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __("admin.applicant.actions.{$key}").' · '.$this->document($arguments)->type->getLabel())
            ->modalIcon($icon)
            ->modalIconColor($color)
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->form([
                Textarea::make('note')
                    ->label(__('admin.applicant.fields.document_note'))
                    ->helperText(__('admin.applicant.document_note_hint'))
                    ->rows(3)
                    ->required(),
            ])
            ->action(fn (array $arguments, array $data) => $this->run(
                fn (StudentWorkflow $w) => $w->reviewDocument($this->document($arguments), $status, $data['note']),
                'document_marked',
            ));
    }

    // ── Aksi pembayaran (tab Pembayaran) ─────────────────────────────────────

    public function previewPaymentAction(): Action
    {
        return $this->previewAction('previewPayment', fn (array $arguments): array => [
            'url' => route('files.payment', $this->payment($arguments)),
            'image' => ! str_ends_with((string) $this->payment($arguments)->proof_file, '.pdf'),
            'title' => $this->payment($arguments)->type->getLabel(),
        ]);
    }

    public function verifyPaymentAction(): Action
    {
        return Action::make('verifyPayment')
            ->label(__('admin.payment.actions.verify'))
            ->icon('lucide-check')
            ->color('success')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('admin.payment.verify_heading'))
            ->modalDescription(fn (array $arguments): string => __('admin.payment.verify_description', [
                'type' => $this->payment($arguments)->type->getLabel(),
                'amount' => Rupiah::format($this->payment($arguments)->amount),
            ]))
            ->modalIcon('lucide-check')
            ->modalIconColor('success')
            ->action(fn (array $arguments) => $this->run(fn (StudentWorkflow $w) => $w->verifyPayment($this->payment($arguments)), 'payment_verified'));
    }

    public function rejectPaymentAction(): Action
    {
        return FormModal::apply(Action::make('rejectPayment'))
            ->label(__('admin.payment.actions.reject'))
            ->icon('lucide-x')
            ->color('gray')
            ->size('sm')
            ->modalHeading(__('admin.payment.reject_heading'))
            ->modalIcon('lucide-x')
            ->modalIconColor('danger')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->form([
                Textarea::make('note')->label(__('admin.payment.fields.rejection_note'))->rows(3)->required(),
            ])
            ->action(fn (array $arguments, array $data) => $this->run(
                fn (StudentWorkflow $w) => $w->rejectPayment($this->payment($arguments), $data['note']),
                'payment_rejected',
            ));
    }

    // ── Data untuk view ──────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $student = $this->record->loadMissing(['program', 'academicPeriod', 'nationality', 'homeUniversityCountry', 'agent', 'loa']);

        return [
            'canVerify' => $this->canVerify(),
            'canReview' => $this->canVerify() && $student->status === StudentStatus::InReview,
            'canUpload' => $this->canVerify() && $student->status->isEditable(),
            'profile' => $this->profile($student),
            'documents' => $this->documents($student),
            'fees' => $this->fees($student),
            'payments' => $student->payments()->with('paymentAccount')->latest()->get(),
            'history' => ActivityDescriber::forStudent($student),
        ];
    }

    /**
     * @return list<array{label: string, value: ?string, mono?: bool}>
     */
    private function profile(Student $s): array
    {
        $date = fn ($value): ?string => $value?->translatedFormat('j F Y');

        return [
            ['label' => __('admin.applicant.fields.registration_number'), 'value' => $s->registration_number ?? __('admin.applicant.registration_number_pending'), 'mono' => filled($s->registration_number)],
            ['label' => __('workflow.fields.email'), 'value' => $s->email],
            ['label' => __('workflow.fields.phone_number'), 'value' => $s->phone_number],
            ['label' => __('workflow.fields.gender'), 'value' => $s->gender?->getLabel()],
            ['label' => __('admin.applicant.fields.birth'), 'value' => collect([$s->place_of_birth, $date($s->date_of_birth)])->filter()->implode(', ') ?: null],
            ['label' => __('workflow.fields.nationality_code'), 'value' => $s->nationality?->name],
            ['label' => __('admin.applicant.fields.religion'), 'value' => $s->religion?->getLabel()],
            ['label' => __('workflow.fields.permanent_address'), 'value' => collect([$s->permanent_address, $s->state, $s->post_code])->filter()->implode(', ') ?: null],
            ['label' => __('workflow.fields.home_university'), 'value' => collect([$s->home_university, $s->homeUniversityCountry?->name])->filter()->implode(' · ') ?: null],
            ['label' => __('workflow.fields.passport_number'), 'value' => $s->passport_number, 'mono' => true],
            ['label' => __('admin.applicant.fields.passport_validity'), 'value' => collect([$date($s->date_of_issued_passport), $date($s->date_of_passport_expiry)])->filter()->implode(' – ') ?: null],
            ['label' => __('admin.program.label'), 'value' => $s->program?->name],
            ['label' => __('admin.period.label'), 'value' => $s->academicPeriod?->name],
            ['label' => __('admin.dashboard.source'), 'value' => $s->agent?->company_name ?? __('admin.dashboard.source_self')],
            ['label' => __('admin.applicant.fields.submitted_at'), 'value' => $s->submitted_at?->translatedFormat('j F Y, H.i')],
        ];
    }

    /**
     * Semua jenis wajib (termasuk yang belum diunggah) lalu dokumen tambahan yang ada.
     *
     * @return Collection<int, array{type: DocumentType, document: ?Document}>
     */
    private function documents(Student $student): Collection
    {
        $uploaded = $student->documents()->latest()->get();

        $required = collect(DocumentType::required())->map(fn (DocumentType $type): array => [
            'type' => $type,
            'document' => $uploaded->first(fn (Document $d): bool => $d->type === $type),
        ]);

        $extra = $uploaded
            ->reject(fn (Document $d): bool => $d->type->isRequired())
            ->map(fn (Document $d): array => ['type' => $d->type, 'document' => $d]);

        return $required->concat($extra)->values();
    }

    /**
     * @return Collection<int, array{label: string, amount: string, status: ?PaymentStatus}>
     */
    private function fees(Student $student): Collection
    {
        $payments = $student->payments()->latest()->get();

        return app(StudentWorkflow::class)->requiredPaymentTypes($student)->map(fn ($type): array => [
            'label' => $type->getLabel(),
            'amount' => Rupiah::format($student->program->{$type->value}),
            'status' => $payments->first(fn (Payment $p): bool => $p->type === $type)?->status,
        ]);
    }

    /**
     * @return Collection<int, DocumentType>
     */
    private function unapprovedRequired(): Collection
    {
        $approved = $this->record->documents()->where('status', DocumentStatus::Approved)->pluck('type')->map->value;

        return collect(DocumentType::required())->reject(fn (DocumentType $t): bool => $approved->contains($t->value))->values();
    }

    private function outstanding(): Collection
    {
        return app(StudentWorkflow::class)->outstandingPayments($this->record);
    }

    // ── Pembantu ─────────────────────────────────────────────────────────────

    /**
     * Jalankan langkah alur; kesalahan alur tampil sebagai notifikasi merah, bukan halaman error.
     */
    private function run(Closure $step, string $successKey): void
    {
        abort_unless($this->canVerify(), 403);

        try {
            app()->call($step);
        } catch (WorkflowException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                // Daftar butir yang kurang; body dirender sebagai HTML tersanitasi, jadi baris baru memakai <br>
                ->body($e->missing->isNotEmpty() ? $e->missing->pluck('label')->map(fn (string $l): string => '• '.e($l))->implode('<br>') : null)
                ->persistent($e->missing->isNotEmpty())
                ->send();

            return;
        }

        $this->record->refresh();

        Notification::make()->success()->title(__("admin.applicant.done.{$successKey}"))->send();
    }

    private function previewAction(string $name, Closure $file): Action
    {
        return Action::make($name)
            ->label(__('admin.applicant.actions.preview'))
            ->icon('lucide-eye')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => $file($arguments)['title'])
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.admin.applicants.preview', $file($arguments)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.applicant.actions.close'));
    }

    /**
     * Argumen aksi datang dari browser: selalu dicari di dalam pendaftar ini, dan hanya untuk
     * pemegang ability `verify` — tombol yang disembunyikan tetap bisa dipanggil lewat Livewire.
     */
    private function document(array $arguments): Document
    {
        abort_unless($this->canVerify(), 403);

        return $this->record->documents()->findOrFail($arguments['document'] ?? null);
    }

    private function payment(array $arguments): Payment
    {
        abort_unless($this->canVerify(), 403);

        return $this->record->payments()->findOrFail($arguments['payment'] ?? null);
    }
}

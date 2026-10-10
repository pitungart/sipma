<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Student;
use App\Workflow\StudentWorkflow;
use Illuminate\Support\Collection;

/**
 * Isi halaman detail pendaftar yang dipakai bersama panel admin dan agen: baris data diri
 * dan daftar dokumen per jenis.
 */
final class ApplicantDetails
{
    /**
     * @return list<array{label: string, value: ?string, mono?: bool}>
     */
    public static function profile(Student $s, bool $withSource = true): array
    {
        $date = fn ($value): ?string => $value?->translatedFormat('j F Y');

        return array_values(array_filter([
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
            $withSource ? ['label' => __('admin.dashboard.source'), 'value' => $s->agent?->company_name ?? __('admin.dashboard.source_self')] : null,
            ['label' => __('admin.applicant.fields.submitted_at'), 'value' => $s->submitted_at?->translatedFormat('j F Y, H.i')],
        ]));
    }

    /**
     * Biaya wajib program (> Rp 0) beserta rekening tujuan dan bukti bayar terbarunya.
     *
     * @return Collection<int, array{type: PaymentType, label: string, amount: string, account: ?PaymentAccount, payment: ?Payment, status: ?PaymentStatus}>
     */
    public static function fees(Student $student): Collection
    {
        $workflow = app(StudentWorkflow::class);
        $payments = $student->payments()->latest()->get();

        return $workflow->requiredPaymentTypes($student)->map(function (PaymentType $type) use ($student, $workflow, $payments): array {
            $payment = $payments->first(fn (Payment $p): bool => $p->type === $type);

            return [
                'type' => $type,
                'label' => $type->getLabel(),
                'amount' => Rupiah::format($workflow->feeFor($student, $type)),
                'account' => PaymentAccount::bestFor($type, $student->program_id),
                'payment' => $payment,
                'status' => $payment?->status,
            ];
        });
    }

    /**
     * Semua jenis wajib (termasuk yang belum diunggah), lalu jenis opsional: yang diminta
     * ($optional) selalu tampil, sisanya hanya bila sudah diunggah.
     *
     * @param  list<DocumentType>  $optional
     * @return Collection<int, array{type: DocumentType, document: ?Document}>
     */
    public static function documents(Student $student, array $optional = []): Collection
    {
        $uploaded = $student->documents()->latest()->get();
        $shown = [...DocumentType::required(), ...$optional];

        $listed = collect($shown)->map(fn (DocumentType $type): array => [
            'type' => $type,
            'document' => $uploaded->first(fn (Document $d): bool => $d->type === $type),
        ]);

        $extra = $uploaded
            ->reject(fn (Document $d): bool => in_array($d->type, $shown, true))
            ->map(fn (Document $d): array => ['type' => $d->type, 'document' => $d]);

        return $listed->concat($extra)->values();
    }
}

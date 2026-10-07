<?php

namespace App\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Validasi kelengkapan sebelum submit (include UC-11). Menghasilkan satu butir per syarat
 * dengan label dua bahasa, sehingga layar dapat menampilkan ringkasan yang tertaut ke field
 * (R-3.3) dan pesan per item (R-3.4) — bukan satu error umum.
 */
final class SubmissionChecklist
{
    /**
     * Data personal yang wajib terisi. Agama sengaja opsional (data pribadi spesifik, UU PDP).
     */
    public const REQUIRED_FIELDS = [
        'full_name',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'nationality_code',
        'email',
        'phone_number',
        'permanent_address',
        'home_university',
        'home_university_country_code',
        'passport_number',
        'date_of_issued_passport',
        'date_of_passport_expiry',
        'program_id',
    ];

    public function __construct(private readonly Student $student) {}

    public static function for(Student $student): self
    {
        return new self($student);
    }

    /**
     * @return Collection<int, array{key: string, kind: string, label: string, done: bool}>
     */
    public function items(): Collection
    {
        $documents = $this->student->documents()->latest()->get()->groupBy(fn (Document $d): string => $d->type->value);

        $fields = collect(self::REQUIRED_FIELDS)->map(fn (string $field): array => [
            'key' => $field,
            'kind' => 'field',
            'label' => __("workflow.fields.{$field}"),
            'done' => filled($this->student->getAttribute($field)),
        ]);

        // Dokumen wajib dianggap ada bila versi terbarunya belum ditandai revisi/ditolak.
        $required = collect(DocumentType::required())->map(fn (DocumentType $type): array => [
            'key' => $type->value,
            'kind' => 'document',
            'label' => $type->getLabel(),
            'done' => in_array($documents->get($type->value)?->first()?->status, [DocumentStatus::Pending, DocumentStatus::Approved], true),
        ]);

        // Dokumen opsional yang diminta revisi tetap harus diunggah ulang sebelum diajukan lagi.
        $optional = $documents
            ->reject(fn (Collection $versions, string $type): bool => DocumentType::from($type)->isRequired())
            ->filter(fn (Collection $versions): bool => in_array($versions->first()->status, [DocumentStatus::Revision, DocumentStatus::Rejected], true))
            ->map(fn (Collection $versions, string $type): array => [
                'key' => $type,
                'kind' => 'document',
                'label' => DocumentType::from($type)->getLabel(),
                'done' => false,
            ])
            ->values();

        return $fields->concat($required)->concat($optional)->values();
    }

    /**
     * @return Collection<int, array{key: string, kind: string, label: string, done: bool}>
     */
    public function missing(): Collection
    {
        return $this->items()->reject(fn (array $item): bool => $item['done'])->values();
    }

    public function isComplete(): bool
    {
        return $this->missing()->isEmpty();
    }
}

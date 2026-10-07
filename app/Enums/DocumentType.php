<?php

namespace App\Enums;

use App\Models\Document;
use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case Photo = 'photo';
    case GuarantorFinancial = 'guarantor_financial';
    case StudentFinancial = 'student_financial';
    case Declaration = 'declaration';
    case MedicalStatement = 'medical_statement';
    case Passport = 'passport';
    case Transcript = 'transcript';
    case Recommendation = 'recommendation';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("enums.document_type.{$this->value}");
    }

    /**
     * Dokumen yang harus ada sebelum pendaftaran bisa diajukan: 5 dokumen wajib SOP KUI
     * ditambah scan paspor (nomor paspor memang diisi di data personal). Keputusan 6 Oktober 2026.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [
            self::Photo,
            self::GuarantorFinancial,
            self::StudentFinancial,
            self::Declaration,
            self::MedicalStatement,
            self::Passport,
        ];
    }

    public function isRequired(): bool
    {
        return in_array($this, self::required(), true);
    }

    /**
     * Ekstensi yang diterima. Foto harus berupa gambar; dokumen lain boleh PDF atau gambar scan.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return $this === self::Photo ? ['jpg', 'jpeg', 'png'] : ['pdf', 'jpg', 'jpeg', 'png'];
    }

    /**
     * Aturan validasi unggahan, dipakai semua form (mahasiswa, agen) dan service penyimpanan.
     *
     * @return list<string>
     */
    public function uploadRules(): array
    {
        return ['file', 'mimes:'.implode(',', $this->extensions()), 'max:'.Document::MAX_FILE_SIZE_KB];
    }

    /**
     * Ketentuan yang ditampilkan SEBELUM kontrol unggah (R-3.8), bukan sebagai error setelah gagal.
     */
    public function hint(): string
    {
        $base = __('workflow.documents.format', [
            'types' => mb_strtoupper(implode(', ', array_unique(array_map(
                fn (string $ext): string => $ext === 'jpeg' ? 'jpg' : $ext,
                $this->extensions(),
            )))),
            'max' => Document::MAX_FILE_SIZE_KB,
        ]);

        return $this === self::Photo ? __('workflow.documents.photo_rules').' '.$base : $base;
    }
}

<?php

namespace App\Enums;

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
        return match ($this) {
            self::Photo => 'Photo',
            self::GuarantorFinancial => 'Guarantor Financial Statement',
            self::StudentFinancial => 'Student Financial Statement',
            self::Declaration => 'Declaration',
            self::MedicalStatement => 'Medical Statement',
            self::Passport => 'Passport',
            self::Transcript => 'Transcript',
            self::Recommendation => 'Recommendation Letter',
            self::Other => 'Other',
        };
    }

    /**
     * 5 dokumen wajib sesuai SOP KUI.
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
        ];
    }

    public function isRequired(): bool
    {
        return in_array($this, self::required(), true);
    }
}

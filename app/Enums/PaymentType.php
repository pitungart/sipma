<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasLabel
{
    case AdmissionFee = 'admission_fee';
    case TuitionFee = 'tuition_fee';

    public function getLabel(): string
    {
        return match ($this) {
            self::AdmissionFee => 'Admission Fee',
            self::TuitionFee => 'Tuition Fee',
        };
    }
}

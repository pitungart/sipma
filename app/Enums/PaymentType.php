<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasLabel
{
    case AdmissionFee = 'admission_fee';
    case TuitionFee = 'tuition_fee';

    public function getLabel(): string
    {
        return __("enums.payment_type.{$this->value}");
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis kelamin mahasiswa (kolom students.gender).
 * "Other" untuk pendaftar yang tidak terwakili dua nilai pertama.
 */
enum Gender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("enums.gender.{$this->value}");
    }
}

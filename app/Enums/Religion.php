<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Agama mahasiswa (kolom students.religion): enam agama yang diakui di Indonesia + "Other"
 * untuk pendaftar asing di luar daftar tersebut.
 *
 * Agama termasuk data pribadi spesifik (UU No. 27/2022 tentang PDP), jadi kolomnya opsional
 * dan tujuan pengumpulannya disebut pada teks persetujuan.
 */
enum Religion: string implements HasLabel
{
    case Islam = 'islam';
    case Protestant = 'protestant';
    case Catholic = 'catholic';
    case Hindu = 'hindu';
    case Buddhist = 'buddhist';
    case Confucian = 'confucian';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("enums.religion.{$this->value}");
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Peran dosen pada satu mata kuliah dalam SK Mengajar.
 */
enum TeachingRole: string implements HasLabel
{
    case Coordinator = 'coordinator';
    case Lecturer = 'lecturer';
    case Assistant = 'assistant';

    public function getLabel(): string
    {
        return __("enums.teaching_role.{$this->value}");
    }
}

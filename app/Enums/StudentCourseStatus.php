<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status mata kuliah yang ditempuh mahasiswa. Warna mengikuti R-1.4 dan setiap status wajib punya
 * label + ikon (R-1.5).
 */
enum StudentCourseStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case Dropped = 'dropped';

    public function getLabel(): string
    {
        return __("enums.student_course_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::Dropped => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'lucide-clock',
            self::Completed => 'lucide-circle-check',
            self::Dropped => 'lucide-circle-minus',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pemisah antar atribut nomor (Sistem → Penomoran). Pilihan tetap, satu pemisah per jenis nomor.
 */
enum NumberSeparator: string implements HasLabel
{
    case Slash = 'slash';
    case Dot = 'dot';
    case Dash = 'dash';
    case Underscore = 'underscore';
    case Space = 'space';
    case None = 'none';

    public function character(): string
    {
        return match ($this) {
            self::Slash => '/',
            self::Dot => '.',
            self::Dash => '-',
            self::Underscore => '_',
            self::Space => ' ',
            self::None => '',
        };
    }

    public function getLabel(): string
    {
        return __("numbering.separators.{$this->value}");
    }
}

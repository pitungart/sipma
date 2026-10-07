<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Nomor yang dibuat sistem dan formatnya bisa diatur Super Admin di Sistem → Penomoran
 * (keputusan 7 Oktober 2026). Format bawaan dipakai sampai diubah.
 */
enum NumberType: string implements HasLabel
{
    case Application = 'application';
    case Loa = 'loa';
    case Mou = 'mou';
    case Receipt = 'receipt';

    public function getLabel(): string
    {
        return __("numbering.types.{$this->value}.label");
    }

    public function icon(): string
    {
        return match ($this) {
            self::Application => 'lucide-clipboard-list',
            self::Loa => 'lucide-file-badge',
            self::Mou => 'lucide-handshake',
            self::Receipt => 'lucide-receipt-text',
        };
    }

    /**
     * Kapan nomor ini diberikan — ditampilkan di halaman pengaturan.
     */
    public function issuedWhen(): string
    {
        return __("numbering.types.{$this->value}.when");
    }

    /**
     * Susunan bawaan (juga diisi NumberFormatSeeder), mis. LOA/SIPMA/2026/0001.
     *
     * @return array{separator: NumberSeparator, segments: list<array<string, mixed>>}
     */
    public function defaultFormat(): array
    {
        $text = fn (string $value): array => ['type' => NumberSegmentType::Text->value, 'value' => $value];
        $year = fn (int $digits): array => ['type' => NumberSegmentType::Year->value, 'digits' => $digits];
        $sequence = NumberSegmentType::Sequence->defaults();

        return match ($this) {
            self::Application => ['separator' => NumberSeparator::Dash, 'segments' => [$text('SP'), $year(2), $sequence]],
            self::Loa => ['separator' => NumberSeparator::Slash, 'segments' => [$text('LOA'), $text('SIPMA'), $year(4), $sequence]],
            self::Mou => ['separator' => NumberSeparator::Slash, 'segments' => [$text('MOU'), $text('SIPMA'), $year(4), $sequence]],
            self::Receipt => ['separator' => NumberSeparator::Slash, 'segments' => [$text('KW'), $text('SIPMA'), $year(4), $sequence]],
        };
    }

    /**
     * Kolom waktu terbit nomor — untuk menampilkan "terakhir terbit" yang benar.
     */
    public function issuedAtColumn(): string
    {
        return match ($this) {
            self::Application => 'submitted_at',
            self::Loa => 'issued_at',
            self::Mou, self::Receipt => 'verified_at',
        };
    }

    /**
     * Tabel & kolom tempat nomor disimpan — dipakai untuk menjamin nomor baru belum pernah terbit.
     *
     * @return array{0: string, 1: string}
     */
    public function column(): array
    {
        return match ($this) {
            self::Application => ['students', 'registration_number'],
            self::Loa => ['loas', 'loa_number'],
            self::Mou => ['mous', 'mou_number'],
            self::Receipt => ['payments', 'receipt_number'],
        };
    }
}

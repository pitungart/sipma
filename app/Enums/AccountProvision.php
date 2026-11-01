<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Hasil penyiapan akun portal saat mahasiswa ditandai Aktif (StudentAccounts::provision).
 * Akun yang gagal disiapkan tidak menggagalkan aktivasi; admin diberi tahu lewat label ini.
 */
enum AccountProvision: string implements HasColor, HasLabel
{
    case Existing = 'existing'; // pendaftar mandiri, akun sudah ada
    case Created = 'created'; // akun baru + undangan dikirim
    case Linked = 'linked'; // email cocok dengan akun mahasiswa yang belum punya pendaftaran
    case MissingEmail = 'missing_email';
    case EmailTaken = 'email_taken'; // email dipakai akun lain (mis. agen, atau mahasiswa lain)

    public function getLabel(): string
    {
        return __("workflow.accounts.{$this->value}");
    }

    public function getColor(): string
    {
        return $this->hasAccount() ? 'success' : 'warning';
    }

    public function hasAccount(): bool
    {
        return in_array($this, [self::Existing, self::Created, self::Linked], true);
    }
}

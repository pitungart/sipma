<?php

namespace App\Policies;

use App\Enums\MouStatus;
use App\Enums\UserRole;
use App\Models\Mou;
use App\Models\User;

/**
 * UC-13 Upload MOU / revisi MOU: agen pemilik profil.
 * UC-25 Verifikasi MOU (approve / reject): hanya Super Admin (via Gate::before).
 */
class MouPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Agent);
    }

    public function view(User $user, Mou $mou): bool
    {
        return $this->isOwner($user, $mou);
    }

    /**
     * Agen harus sudah mengisi profil (tabel agents) sebelum upload MOU.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Agent) && $user->agent()->exists();
    }

    /**
     * MOU yang sudah disetujui tidak bisa diubah; revisi = upload MOU baru atau ganti file yang pending/rejected.
     */
    public function update(User $user, Mou $mou): bool
    {
        return $this->isOwner($user, $mou) && $mou->status !== MouStatus::Approved;
    }

    public function delete(User $user, Mou $mou): bool
    {
        return $this->isOwner($user, $mou) && $mou->status === MouStatus::Pending;
    }

    public function verify(User $user, Mou $mou): bool
    {
        return false;
    }

    private function isOwner(User $user, Mou $mou): bool
    {
        return $user->hasRole(UserRole::Agent) && $mou->agent?->user_id === $user->id;
    }
}

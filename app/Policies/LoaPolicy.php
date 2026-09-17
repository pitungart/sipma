<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Loa;
use App\Models\User;

/**
 * UC-02 Download LOA: agen & mahasiswa pemilik data, setelah LOA diunggah.
 * UC-26 Upload LOA: hanya Super Admin (via Gate::before).
 */
class LoaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Agent, UserRole::Student);
    }

    public function view(User $user, Loa $loa): bool
    {
        return $loa->student->isOwnedBy($user);
    }

    public function download(User $user, Loa $loa): bool
    {
        return $loa->student->isOwnedBy($user) && $loa->status->isAvailable();
    }
}

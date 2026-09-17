<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisaStatus;

/**
 * Status visa read-only untuk agen & mahasiswa pemilik data; diubah hanya oleh Super Admin (via Gate::before).
 */
class VisaStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Agent, UserRole::Student);
    }

    public function view(User $user, VisaStatus $visaStatus): bool
    {
        return $visaStatus->student->isOwnedBy($user);
    }
}

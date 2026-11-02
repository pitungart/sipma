<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisaStatus;

/**
 * Status visa: dilihat Admin Fakultas (fakultasnya) serta agen & mahasiswa pemilik data; diubah
 * hanya oleh Super Admin (via Gate::before). Tampilan di panel agen & portal ditunda (2 November 2026).
 */
class VisaStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Agent, UserRole::Student);
    }

    public function view(User $user, VisaStatus $visaStatus): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return $user->faculty_id !== null && $visaStatus->student?->program?->faculty_id === $user->faculty_id;
        }

        return $visaStatus->student->isOwnedBy($user);
    }
}

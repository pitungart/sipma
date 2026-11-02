<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StudentCard;
use App\Models\User;
use App\Policies\Concerns\SuperAdminOnly;

/**
 * KTM dikelola hanya oleh Super Admin (via Gate::before). Admin Fakultas melihat KTM fakultasnya,
 * mahasiswa melihat miliknya setelah Aktif; agen tidak punya akses.
 */
class StudentCardPolicy
{
    use SuperAdminOnly;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Student);
    }

    public function view(User $user, StudentCard $card): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return $user->faculty_id !== null && $card->student?->program?->faculty_id === $user->faculty_id;
        }

        return $user->hasRole(UserRole::Student)
            && $card->student?->isOwnedBy($user) === true
            && $card->student->status->hasAcademicAccess();
    }

    public function reissue(User $user, StudentCard $card): bool
    {
        return false;
    }

    public function revoke(User $user, StudentCard $card): bool
    {
        return false;
    }
}

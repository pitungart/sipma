<?php

namespace App\Policies;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

/**
 * Super Admin diizinkan untuk semua aksi lewat Gate::before (AppServiceProvider).
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Agent, UserRole::Student);
    }

    /**
     * UC-28 Admin Fakultas: read-only mahasiswa di program fakultasnya.
     * UC-01 Agen / mahasiswa: data yang didaftarkan / miliknya sendiri.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return $user->faculty_id !== null
                && $student->program?->faculty_id === $user->faculty_id;
        }

        return $student->isOwnedBy($user);
    }

    /**
     * Agen baru bisa mendaftarkan mahasiswa setelah MOU disetujui.
     * Mahasiswa mandiri hanya punya satu data pendaftaran.
     */
    public function create(User $user): bool
    {
        return match ($user->role) {
            UserRole::Agent => $user->agent?->hasApprovedMou() === true,
            UserRole::Student => $user->student()->doesntExist(),
            default => false,
        };
    }

    /**
     * UC-07 / UC-03: isi data atau revisi hanya saat status draft / revision.
     */
    public function update(User $user, Student $student): bool
    {
        return $student->isOwnedBy($user) && $student->status->isEditable();
    }

    /**
     * UC-11 Submit pendaftaran.
     */
    public function submit(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $student->isOwnedBy($user) && $student->status === StudentStatus::Draft;
    }

    /**
     * UC-15 s/d UC-18 Verifikasi & update status: hanya Super Admin.
     */
    public function verify(User $user, Student $student): bool
    {
        return false;
    }
}

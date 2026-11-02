<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lecturer;
use App\Models\User;

/**
 * Master Dosen: Super Admin (via Gate::before) dan Admin Fakultas untuk fakultasnya sendiri.
 */
class LecturerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function view(User $user, Lecturer $lecturer): bool
    {
        return $this->managesLecturer($user, $lecturer);
    }

    public function create(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function update(User $user, Lecturer $lecturer): bool
    {
        return $this->managesLecturer($user, $lecturer);
    }

    public function delete(User $user, Lecturer $lecturer): bool
    {
        return $this->managesLecturer($user, $lecturer);
    }

    public function restore(User $user, Lecturer $lecturer): bool
    {
        return $this->managesLecturer($user, $lecturer);
    }

    public function forceDelete(User $user, Lecturer $lecturer): bool
    {
        return false;
    }

    private function isFacultyAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) && $user->faculty_id !== null;
    }

    private function managesLecturer(User $user, Lecturer $lecturer): bool
    {
        return $this->isFacultyAdmin($user) && $lecturer->faculty_id === $user->faculty_id;
    }
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Program;
use App\Models\User;

/**
 * UC-27 Kelola program: Super Admin (via Gate::before) dan Admin Fakultas untuk fakultasnya sendiri.
 */
class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function view(User $user, Program $program): bool
    {
        return $this->managesProgram($user, $program);
    }

    public function create(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function update(User $user, Program $program): bool
    {
        return $this->managesProgram($user, $program);
    }

    public function delete(User $user, Program $program): bool
    {
        return $this->managesProgram($user, $program);
    }

    public function restore(User $user, Program $program): bool
    {
        return $this->managesProgram($user, $program);
    }

    private function isFacultyAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) && $user->faculty_id !== null;
    }

    private function managesProgram(User $user, Program $program): bool
    {
        return $this->isFacultyAdmin($user) && $program->faculty_id === $user->faculty_id;
    }
}

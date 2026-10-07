<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\User;

/**
 * Periode pendaftaran mengikuti kepemilikan program (UC-27): Super Admin (via Gate::before)
 * dan Admin Fakultas untuk program di fakultasnya sendiri.
 */
class AcademicPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function view(User $user, AcademicPeriod $period): bool
    {
        return $this->managesPeriod($user, $period);
    }

    public function create(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function update(User $user, AcademicPeriod $period): bool
    {
        return $this->managesPeriod($user, $period);
    }

    public function delete(User $user, AcademicPeriod $period): bool
    {
        return $this->managesPeriod($user, $period);
    }

    private function isFacultyAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) && $user->faculty_id !== null;
    }

    private function managesPeriod(User $user, AcademicPeriod $period): bool
    {
        return $this->isFacultyAdmin($user) && $period->program->faculty_id === $user->faculty_id;
    }
}

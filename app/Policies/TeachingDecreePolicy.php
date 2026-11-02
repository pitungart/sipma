<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TeachingDecree;
use App\Models\User;

/**
 * SK Mengajar: Super Admin (via Gate::before) dan Admin Fakultas untuk fakultasnya sendiri, termasuk
 * berkasnya. Agen dan mahasiswa ditolak.
 */
class TeachingDecreePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function view(User $user, TeachingDecree $decree): bool
    {
        return $this->managesDecree($user, $decree);
    }

    public function create(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function update(User $user, TeachingDecree $decree): bool
    {
        return $this->managesDecree($user, $decree);
    }

    public function delete(User $user, TeachingDecree $decree): bool
    {
        return $this->managesDecree($user, $decree);
    }

    public function restore(User $user, TeachingDecree $decree): bool
    {
        return $this->managesDecree($user, $decree);
    }

    public function forceDelete(User $user, TeachingDecree $decree): bool
    {
        return false;
    }

    private function isFacultyAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) && $user->faculty_id !== null;
    }

    private function managesDecree(User $user, TeachingDecree $decree): bool
    {
        return $this->isFacultyAdmin($user) && $decree->faculty_id === $user->faculty_id;
    }
}

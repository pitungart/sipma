<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

/**
 * Paket mata kuliah: Super Admin (via Gate::before) dan Admin Fakultas untuk program fakultasnya.
 * Mahasiswa dan agen hanya melihat rubrik lewat katalog dan form, bukan menu ini.
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function view(User $user, Course $course): bool
    {
        return $this->managesCourse($user, $course);
    }

    public function create(User $user): bool
    {
        return $this->isFacultyAdmin($user);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->managesCourse($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->managesCourse($user, $course);
    }

    public function restore(User $user, Course $course): bool
    {
        return $this->managesCourse($user, $course);
    }

    public function forceDelete(User $user, Course $course): bool
    {
        return false;
    }

    private function isFacultyAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) && $user->faculty_id !== null;
    }

    private function managesCourse(User $user, Course $course): bool
    {
        return $this->isFacultyAdmin($user) && $course->program?->faculty_id === $user->faculty_id;
    }
}

<?php

namespace App\Policies;

use App\Policies\Concerns\SuperAdminOnly;

/**
 * UC-22 Kelola fakultas: hanya Super Admin (lolos lewat Gate::before di AppServiceProvider).
 * Penolakan untuk role lain datang dari trait SuperAdminOnly, yang menulis method-nya secara
 * eksplisit karena Filament mengizinkan aksi yang method-nya tidak ada di policy.
 */
class FacultyPolicy
{
    use SuperAdminOnly;
}

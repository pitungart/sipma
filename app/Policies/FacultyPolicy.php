<?php

namespace App\Policies;

/**
 * UC-22 Kelola fakultas: hanya Super Admin.
 *
 * Super Admin diizinkan lewat Gate::before (AppServiceProvider). Role lain ditolak
 * karena tidak ada method yang didefinisikan. Policy ini tetap harus ada: tanpa policy,
 * Filament mengizinkan semua aksi pada resource.
 */
class FacultyPolicy
{
}

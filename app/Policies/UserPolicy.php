<?php

namespace App\Policies;

use App\Policies\Concerns\SuperAdminOnly;

/**
 * UC-23 Kelola admin: hanya Super Admin (lolos lewat Gate::before di AppServiceProvider).
 * Lihat catatan di trait SuperAdminOnly soal method yang harus ditulis eksplisit.
 */
class UserPolicy
{
    use SuperAdminOnly;
}

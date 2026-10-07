<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Sumber daya yang hanya boleh disentuh Super Admin (KUI).
 *
 * Method-nya harus ditulis eksplisit walau selalu mengembalikan false: Filament menganggap
 * aksi DIIZINKAN bila policy tidak memiliki method-nya (Resource::can() memeriksa method_exists),
 * sehingga policy kosong justru membuka menu untuk Admin Fakultas.
 *
 * Super Admin sendiri lolos lebih dulu lewat Gate::before di AppServiceProvider.
 */
trait SuperAdminOnly
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function restore(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}

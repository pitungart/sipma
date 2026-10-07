<?php

namespace App\Policies;

use App\Policies\Concerns\SuperAdminOnly;

/**
 * Master negara: hanya Super Admin. Isinya berasal dari CountrySeeder dan di panel hanya
 * bisa dinonaktifkan. Lihat catatan di trait SuperAdminOnly.
 */
class CountryPolicy
{
    use SuperAdminOnly;
}

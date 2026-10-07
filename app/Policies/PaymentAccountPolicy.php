<?php

namespace App\Policies;

use App\Policies\Concerns\SuperAdminOnly;

/**
 * Master rekening tujuan pembayaran: hanya Super Admin, karena rekening dipegang KUI.
 * Lihat catatan di trait SuperAdminOnly.
 */
class PaymentAccountPolicy
{
    use SuperAdminOnly;
}

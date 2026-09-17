<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

/**
 * UC-10 Upload bukti pembayaran: agen & mahasiswa pemilik data.
 * UC-19/20 Lihat + verifikasi pembayaran: hanya Super Admin (via Gate::before).
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Agent, UserRole::Student);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->student->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Agent, UserRole::Student);
    }

    /**
     * Bukti bayar boleh diganti selama belum diverifikasi.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $payment->student->isOwnedBy($user)
            && $payment->status !== PaymentStatus::Verified;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $payment->student->isOwnedBy($user)
            && $payment->status === PaymentStatus::Pending;
    }

    public function verify(User $user, Payment $payment): bool
    {
        return false;
    }
}

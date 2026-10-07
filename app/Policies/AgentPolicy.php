<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\User;

/**
 * UC-12 Kelola profil agen: agen pemilik profil.
 * UC-24 Kelola agen: hanya Super Admin (via Gate::before).
 * MOU (UC-13 / UC-25): lihat MouPolicy.
 */
class AgentPolicy
{
    /**
     * UC-24 daftar agen: hanya Super Admin (lewat Gate::before). Wajib ditulis eksplisit —
     * Filament menganggap method policy yang tidak ada sebagai "diizinkan".
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Agent $agent): bool
    {
        return $agent->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Agent) && $user->agent()->doesntExist();
    }

    public function update(User $user, Agent $agent): bool
    {
        return $agent->user_id === $user->id;
    }

    /**
     * Agen tidak dihapus dari panel; data pendaftarnya terikat ke agen.
     */
    public function delete(User $user, Agent $agent): bool
    {
        return false;
    }
}

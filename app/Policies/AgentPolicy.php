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
}

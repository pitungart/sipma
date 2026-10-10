<?php

namespace App\Filament\Agent\Concerns;

use App\Models\Agent;
use App\Workflow\AgentOnboarding;
use Filament\Facades\Filament;

/**
 * Halaman panel /agent selalu bekerja atas profil agen milik user yang masuk — tidak pernah
 * menerima ID agen dari browser, jadi agen lain tidak terjangkau (R-4.10).
 */
trait InteractsWithAgent
{
    protected function agent(): ?Agent
    {
        return Filament::auth()->user()?->agent()->first();
    }

    protected function onboarding(): AgentOnboarding
    {
        return AgentOnboarding::for($this->agent());
    }
}

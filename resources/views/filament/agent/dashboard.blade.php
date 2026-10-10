{{-- Dasbor agen: onboarding sampai MOU disetujui, lalu dasbor kerja --}}
<x-filament-panels::page class="sipma-agent-home">
    @include($workspace ? 'filament.agent.dashboard-workspace' : 'filament.agent.dashboard-onboarding')
</x-filament-panels::page>

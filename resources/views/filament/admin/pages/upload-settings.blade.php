{{-- Sistem → Unggahan berkas: batas server di atas (peringatan bila ada isian melebihinya), lalu form --}}
<x-filament-panels::page>
    @if ($server !== PHP_INT_MAX)
        <div @class(['sipma-alert', $overServer->isNotEmpty() ? 'sipma-tone-warning' : 'sipma-tone-info']) role="status">
            <x-filament::icon :icon="$overServer->isNotEmpty() ? 'lucide-triangle-alert' : 'lucide-server'" class="sipma-alert-icon" />
            <div>
                <b>{{ __('admin.uploads.server_title', ['max' => \App\Support\UploadLimits::label($server)]) }}</b>
                @if ($overServer->isNotEmpty())
                    {{ __('admin.uploads.server_over', ['fields' => $overServer->implode(', ')]) }}
                @else
                    {{ __('admin.uploads.server_ok') }}
                @endif
            </div>
        </div>
    @endif

    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>
</x-filament-panels::page>

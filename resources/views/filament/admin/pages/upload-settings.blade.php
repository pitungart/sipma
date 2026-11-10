{{-- Sistem → Unggahan berkas: batas server di atas (peringatan bila ada isian melebihinya), lalu form --}}
<x-filament-panels::page>
    @if ($server !== PHP_INT_MAX)
        <x-sipma.notice
            :tone="$overServer->isNotEmpty() ? 'warning' : 'info'"
            :icon="$overServer->isNotEmpty() ? 'lucide-triangle-alert' : 'lucide-server'"
            :title="__('admin.uploads.server_title', ['max' => \App\Support\UploadLimits::label($server)])"
            role="status"
            class="sipma-page-alert"
        >
            @if ($overServer->isNotEmpty())
                {{ __('admin.uploads.server_over', ['fields' => $overServer->implode(', ')]) }}
            @else
                {{ __('admin.uploads.server_ok') }}
            @endif
        </x-sipma.notice>
    @endif

    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>
</x-filament-panels::page>

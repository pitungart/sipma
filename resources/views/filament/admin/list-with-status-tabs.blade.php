{{-- Halaman daftar bawaan Filament tanpa bar tab di atas tabel; tab dirender di kepala kartu (status-tabs) --}}
<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>

{{-- Daftar pendaftar: tabel bertab status, atau kanban lima kolom (?view=kanban) --}}
<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    @if ($this->isBoard())
        @include('filament.admin.applicants.kanban', ['columns' => $this->boardColumns(), 'canMove' => $this->canMoveCards()])

        {{-- Pada halaman bertabel, modal aksi biasanya dirender oleh tabel; di mode kanban tabel tidak tampil --}}
        <x-filament-actions::modals />
    @else
        <div class="flex flex-col gap-y-6">
            {{ $this->table }}
        </div>
    @endif
</x-filament-panels::page>

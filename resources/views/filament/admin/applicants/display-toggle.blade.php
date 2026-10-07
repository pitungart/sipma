{{--
    Sakelar "Tabel | Kanban" (template): wadah abu membulat, pil putih untuk pilihan aktif.
    Status aktif dibaca langsung dari $wire.display agar ikut berubah tanpa memuat ulang aksi.
--}}
<div class="sipma-segment sipma-segment-lg" role="group" aria-label="{{ __('admin.applicant.display.label') }}">
    @foreach (['table' => 'lucide-table-2', 'kanban' => 'lucide-square-kanban'] as $mode => $icon)
        <button
            type="button"
            class="sipma-segment-btn"
            wire:click="$set('display', @js($mode))"
            x-bind:class="{ 'is-active': $wire.display === @js($mode) }"
            x-bind:aria-pressed="($wire.display === @js($mode)).toString()"
        >
            <x-filament::icon :icon="$icon" class="sipma-segment-icon" />
            {{ __("admin.applicant.display.{$mode}") }}
        </button>
    @endforeach
</div>

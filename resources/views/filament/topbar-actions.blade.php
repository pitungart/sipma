{{--
    Sisi kanan topbar sebelum lonceng notifikasi: pemilih bahasa lalu tombol tema.
    Tombol tema memakai event bawaan Filament (theme-changed) sehingga pilihan tersimpan
    di localStorage yang sama dengan pengalih tema Filament.
--}}
@if (filament()->auth()->check())
    <div class="sipma-topbar-actions">
        @include('filament.language-switcher')

        <button
            type="button"
            class="sipma-icon-btn"
            x-data="{}"
            x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')"
            x-bind:aria-label="$store.theme === 'dark' ? @js(__('admin.shell.theme_light')) : @js(__('admin.shell.theme_dark'))"
            x-bind:title="$store.theme === 'dark' ? @js(__('admin.shell.theme_light')) : @js(__('admin.shell.theme_dark'))"
        >
            <x-filament::icon icon="lucide-sun" class="sipma-icon" x-show="$store.theme === 'dark'" x-cloak />
            <x-filament::icon icon="lucide-moon" class="sipma-icon" x-show="$store.theme !== 'dark'" />
        </button>
    </div>
@endif

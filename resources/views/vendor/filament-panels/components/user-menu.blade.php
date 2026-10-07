{{--
    Menu profil SIPMA (menimpa view bawaan Filament agar sama dengan template):
    pemicu avatar inisial + chevron; isi: nama & peran, Profil saya, Ganti tema, Keluar.
--}}
@use('App\Filament\Support\InitialsAvatarProvider')

@php
    $user = filament()->auth()->user();
    $items = filament()->getUserMenuItems();

    $profileItem = $items['profile'] ?? $items['account'] ?? null;
    $profileUrl = $profileItem?->getUrl() ?? (filament()->hasProfile() ? filament()->getProfileUrl() : null);

    $logoutItem = $items['logout'] ?? null;

    $items = \Illuminate\Support\Arr::except($items, ['account', 'logout', 'profile']);

    $name = filament()->getUserName($user);
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    placement="bottom-end"
    teleport
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu'])
    "
>
    <x-slot name="trigger">
        <button
            aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
            type="button"
            class="sipma-user-trigger"
        >
            <span class="sipma-avatar sipma-avatar-solid" aria-hidden="true">{{ InitialsAvatarProvider::initialsOf($name) }}</span>
            <x-filament::icon icon="lucide-chevron-down" class="sipma-user-trigger-chevron" />
        </button>
    </x-slot>

    <div class="sipma-user-card">
        <span class="sipma-user-card-name">{{ $name }}</span>
        <span class="sipma-user-card-role">{{ $user->role?->getLabel() ?? __('admin.shell.org') }}</span>
    </div>

    <x-filament::dropdown.list>
        @if ($profileUrl)
            <x-filament::dropdown.list.item
                :href="$profileUrl"
                :icon="$profileItem?->getIcon() ?? 'lucide-circle-user'"
                tag="a"
            >
                {{ $profileItem?->getLabel() ?? __('admin.shell.profile') }}
            </x-filament::dropdown.list.item>
        @endif

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <x-filament::dropdown.list.item
                icon="lucide-sun-moon"
                x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark'); close()"
            >
                {{ __('admin.shell.theme_toggle') }}
            </x-filament::dropdown.list.item>
        @endif

        @foreach ($items as $key => $item)
            @php($itemPostAction = $item->getPostAction())

            <x-filament::dropdown.list.item
                :action="$itemPostAction"
                :color="$item->getColor()"
                :href="$item->getUrl()"
                :icon="$item->getIcon()"
                :method="filled($itemPostAction) ? 'post' : null"
                :tag="filled($itemPostAction) ? 'form' : 'a'"
                :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
            >
                {{ $item->getLabel() }}
            </x-filament::dropdown.list.item>
        @endforeach

        <x-filament::dropdown.list.item
            :action="$logoutItem?->getUrl() ?? filament()->getLogoutUrl()"
            color="danger"
            :icon="$logoutItem?->getIcon() ?? 'lucide-log-out'"
            method="post"
            tag="form"
            class="sipma-logout"
        >
            {{ $logoutItem?->getLabel() ?? __('filament-panels::layout.actions.logout.label') }}
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}

{{-- Kaki sidebar: kartu pengguna yang sedang masuk (template: avatar inisial, nama, email) --}}
@use('App\Filament\Support\InitialsAvatarProvider')

@php($user = filament()->auth()->user())

@if ($user)
    <div class="sipma-sidebar-user">
        <span class="sipma-avatar sipma-avatar-soft" aria-hidden="true">
            {{ InitialsAvatarProvider::initialsOf(filament()->getUserName($user)) }}
        </span>

        <span class="sipma-sidebar-user-identity">
            <span class="sipma-sidebar-user-name">{{ filament()->getUserName($user) }}</span>
            <span class="sipma-sidebar-user-email">{{ $user->email }}</span>
        </span>
    </div>
@endif

{{-- Kaki sidebar: kartu pengguna yang sedang masuk (template: avatar inisial, nama, email) --}}

@php($user = filament()->auth()->user())

@if ($user)
    <div class="sipma-sidebar-user">
        <x-sipma.avatar :name="filament()->getUserName($user)" :id="$user->getKey()" :src="$user->avatarUrl()" :focus="$user->avatar_focus" size="lg" />

        <span class="sipma-sidebar-user-identity">
            <span class="sipma-sidebar-user-name">{{ filament()->getUserName($user) }}</span>
            <span class="sipma-sidebar-user-email">{{ $user->email }}</span>
        </span>
    </div>
@endif

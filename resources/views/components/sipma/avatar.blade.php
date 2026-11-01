{{-- Avatar untuk semua panel. Ada foto ($src, User::avatarUrl): foto bulat yang diposisikan sesuai
     $focus (User::avatar_focus, lihat App\Support\AvatarFocus; berkasnya tidak dipotong). Tanpa foto:
     inisial tonal — latar lembut + teks pekat dari satu hue yang diturunkan dari ID record
     (InitialsAvatarProvider::hueOf), jadi tiap orang punya warna sendiri.
     Ukuran: sm 30px, md 32px (bawaan), lg 34px, xl 128px (kartu profil). --}}
@props(['name', 'id', 'size' => 'md', 'src' => null, 'focus' => null])

@if ($src)
    <span {{ $attributes->class(['sipma-avatar sipma-avatar-photo', 'sipma-avatar-'.$size => $size !== 'md']) }}>
        <img src="{{ $src }}" alt="" style="{{ \App\Support\AvatarFocus::style($focus) }}">
    </span>
@else
    <span
        {{ $attributes->class(['sipma-avatar', 'sipma-avatar-'.$size => $size !== 'md'])->style(['--avatar-h: '.\App\Filament\Support\InitialsAvatarProvider::hueOf($id)]) }}
        aria-hidden="true"
    >{{ \App\Filament\Support\InitialsAvatarProvider::initialsOf($name) }}</span>
@endif

{{-- Kolom Nama lengkap di menu Administrator: avatar inisial, nama, email --}}

@php($record = $getRecord())

<div class="sipma-person px-3 py-4">
    <x-sipma.avatar :name="$record->name" :id="$record->getKey()" :src="$record->avatarUrl()" :focus="$record->avatar_focus" />
    <div>
        <div class="sipma-person-name">{{ $record->name }}</div>
        <div class="sipma-person-meta">{{ $record->email }}</div>
    </div>
</div>

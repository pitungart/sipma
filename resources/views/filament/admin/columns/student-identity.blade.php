{{-- Kolom nama di Mahasiswa Asing: avatar inisial, nama, nomor pendaftaran · kewarganegaraan --}}

@php($record = $getRecord())

<div class="sipma-person px-3 py-4">
    <x-sipma.avatar :name="$record->full_name" :id="$record->getKey()" />
    <div>
        <div class="sipma-person-name">{{ $record->full_name }}</div>
        <div class="sipma-person-meta">{{ collect([$record->registration_number, $record->nationality?->name])->filter()->implode(' · ') }}</div>
    </div>
</div>

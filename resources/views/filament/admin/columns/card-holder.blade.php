{{-- Kolom pemegang di KTM digital: avatar inisial, nama, nomor pendaftaran · kewarganegaraan --}}

@php($student = $getRecord()->student)

<div class="sipma-person px-3 py-4">
    <x-sipma.avatar :name="$student->full_name" :id="$student->getKey()" />
    <div>
        <div class="sipma-person-name">{{ $student->full_name }}</div>
        <div class="sipma-person-meta">{{ collect([$student->registration_number, $student->nationality?->name])->filter()->implode(' · ') }}</div>
    </div>
</div>

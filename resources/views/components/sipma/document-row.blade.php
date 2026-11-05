{{-- Satu baris dokumen pendaftaran (admin, agen, mahasiswa): tile, label + tag Wajib/Opsional,
     berkas atau ketentuan unggah, catatan revisi, dan status. Tombol dikirim lewat slot. --}}
@props(['item'])

@php
    use App\Enums\DocumentStatus;

    $type = $item['type'];
    $doc = $item['document'];
    $needsFix = in_array($doc?->status, [DocumentStatus::Revision, DocumentStatus::Rejected], true);
@endphp

<li {{ $attributes->class('sipma-row') }}>
    <span @class(['sipma-tile', 'sipma-tone-'.($doc?->status->getColor() ?? 'gray')])>
        <x-filament::icon :icon="$needsFix ? 'lucide-file-warning' : 'lucide-file-text'" class="sipma-tile-icon" />
    </span>
    <div class="sipma-row-main">
        <div class="sipma-row-title">
            {{ $type->getLabel() }}
            <span @class(['sipma-row-tag', 'sipma-tone-primary is-tonal' => $type->isRequired()])>{{ $type->isRequired() ? __('admin.applicant.required') : __('agent.students.optional') }}</span>
        </div>
        <div class="sipma-row-meta">
            @if ($doc)
                {{ $doc->original_name }} · {{ \Illuminate\Support\Number::fileSize($doc->file_size ?? 0) }} · {{ $doc->updated_at->translatedFormat('j M Y') }}
            @else
                {{ $type->hint() }}
            @endif
        </div>
        @if ($needsFix && filled($doc->revision_note))
            <div class="sipma-row-note"><b>{{ __('agent.mou.note') }}</b> {{ $doc->revision_note }}</div>
        @endif
    </div>
    @if ($doc)
        <x-sipma.status-badge :status="$doc->status" />
    @else
        {{-- Belum ada: merah bila wajib, abu bila opsional (boleh kosong) --}}
        <span @class(['sipma-status', $type->isRequired() ? 'sipma-tone-danger' : 'sipma-tone-gray'])><span class="sipma-status-dot"></span>{{ __('admin.applicant.missing') }}</span>
    @endif
    @if ($slot->hasActualContent())
        <div class="sipma-row-actions">{{ $slot }}</div>
    @endif
</li>

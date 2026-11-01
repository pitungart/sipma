{{-- Baris LOA di tab Pembayaran & LOA (admin, agen, mahasiswa): nomor, tanggal terbit/unduh,
     tombol unduh; bila belum terbit, keterangan kapan LOA terbit. --}}
@props(['loa' => null])

<div {{ $attributes->class('sipma-row sipma-row-loa') }}>
    <span @class(['sipma-tile', $loa ? 'sipma-tone-success' : 'sipma-tone-gray'])>
        <x-filament::icon icon="lucide-file-check" class="sipma-tile-icon" />
    </span>
    <div class="sipma-row-main">
        <div class="sipma-row-title">
            {{ __('admin.applicant.loa') }}
            @if ($loa?->loa_number)
                <span class="sipma-row-tag sipma-mono">{{ $loa->loa_number }}</span>
            @endif
        </div>
        <div class="sipma-row-meta">
            @if ($loa)
                {{ __('agent.students.payments.loa_issued', ['date' => $loa->issued_at?->translatedFormat('j F Y') ?? $loa->created_at->translatedFormat('j F Y')]) }}
                @if ($loa->downloaded_at)
                    · {{ __('agent.students.payments.loa_downloaded', ['date' => $loa->downloaded_at->translatedFormat('j M Y')]) }}
                @endif
            @else
                {{ __('agent.students.payments.loa_waiting') }}
            @endif
        </div>
    </div>
    @if ($loa)
        <x-filament::button tag="a" :href="route('files.loa', $loa)" icon="lucide-download" size="sm">
            {{ __('agent.students.payments.download_loa') }}
        </x-filament::button>
    @else
        <span class="sipma-status sipma-tone-gray"><span class="sipma-status-dot"></span>{{ __('agent.students.payments.loa_pending') }}</span>
    @endif
</div>

{{-- Garis waktu langkah, bentuknya sama dengan kepala wizard (keputusan 26 Oktober 2026): ikon bulat,
     judul + keterangan, pemisah chevron setinggi kartu, tanpa nomor. Selesai = solid + centang,
     sekarang = tonal bernada langkah, berikutnya = tonal abu.
     Bawaan: langkah pendaftaran dari :status; bisa juga diberi :steps lain (mis. onboarding agen). --}}
@props(['status' => null, 'steps' => null, 'label' => null])

@php($steps ??= \App\Workflow\StatusTimeline::for($status))

<ol {{ $attributes->class('sipma-timeline-steps') }} aria-label="{{ $label ?? __('workflow.timeline_label') }}">
    @foreach ($steps as $step)
        <li
            @class(['sipma-step', 'is-'.$step['state'], 'sipma-tone-'.$step['tone']])
            @if ($step['state'] === 'current') aria-current="step" @endif
        >
            <span class="sipma-step-icon">
                <x-filament::icon :icon="$step['state'] === 'done' ? 'lucide-check' : $step['icon']" class="sipma-step-svg" />
            </span>
            <span class="sipma-step-text">
                <span class="sipma-step-label">{{ $step['label'] }}</span>
                <span class="sipma-step-desc">{{ $step['description'] }}</span>
            </span>

            @unless ($loop->last)
                <svg class="sipma-step-sep" aria-hidden="true" fill="none" preserveAspectRatio="none" viewBox="0 0 22 80">
                    <path d="M0 -2L20 40L0 82" stroke="currentColor" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                </svg>
            @endunless
        </li>
    @endforeach
</ol>

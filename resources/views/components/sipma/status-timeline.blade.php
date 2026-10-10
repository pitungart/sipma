{{-- Garis waktu langkah (template "stepper"): selesai = centang, sekarang = pil lembut.
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
                <span class="sipma-step-kicker">{{ str_pad((string) $step['number'], 2, '0', STR_PAD_LEFT) }}</span>
                <span class="sipma-step-label">{{ $step['label'] }}</span>
                <span class="sipma-step-desc">{{ $step['description'] }}</span>
            </span>
        </li>
        @unless ($loop->last)
            <li @class(['sipma-step-line', 'is-done' => $step['state'] === 'done']) aria-hidden="true"></li>
        @endunless
    @endforeach
</ol>

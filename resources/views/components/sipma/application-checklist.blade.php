{{-- Kelengkapan pendaftaran (UC-11), dipakai dasbor mahasiswa dan detail pendaftaran pemilik:
     skor, kemajuan formulir, lalu data mahasiswa (chip) dan dokumen (2 kolom) berdampingan 50:50. --}}
@props(['checklist'])

@php
    $done = $checklist->where('done', true)->count();
    $total = $checklist->count();
    $percent = (int) round($done / max(1, $total) * 100);
    $groups = [
        'field' => ['label' => __('agent.students.checklist_fields'), 'missing' => __('agent.students.checklist_fields_missing')],
        'document' => ['label' => __('agent.students.checklist_documents'), 'missing' => __('agent.students.checklist_documents_missing')],
    ];
@endphp

<section {{ $attributes->class('sipma-card sipma-clip') }}>
    <header class="sipma-card-head sipma-card-head-ruled sipma-checklist-head">
        <div class="sipma-checklist-title">
            <span @class(['sipma-tile', $done === $total ? 'sipma-tone-success' : 'sipma-tone-primary'])>
                <x-filament::icon icon="lucide-list-checks" class="sipma-tile-icon" />
            </span>
            <div class="sipma-min-0">
                <h2 class="sipma-card-title">{{ __('agent.students.checklist_title') }}</h2>
                <p class="sipma-card-desc">{{ __('agent.students.checklist_desc') }}</p>
            </div>
        </div>
        <span @class(['sipma-checklist-score', $done === $total ? 'sipma-tone-success' : 'sipma-tone-warning'])>
            {{ __('agent.students.checklist_status') }}
            <b>{{ $done }}/{{ $total }}</b>
        </span>
    </header>

    <div class="sipma-checklist-progress">
        <div class="sipma-checklist-progress-top">
            <span>{{ __('agent.students.checklist_progress') }}</span>
            <b>{{ $percent }}%</b>
        </div>
        <div @class(['sipma-checklist-bar', 'is-complete' => $done === $total]) role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
            <span style="width: {{ $percent }}%"></span>
        </div>
    </div>

    <div class="sipma-checklist-groups">
        @foreach ($groups as $kind => $group)
            @php
                $items = $checklist->where('kind', $kind);
                $groupDone = $items->where('done', true)->count();
                $complete = $groupDone === $items->count();
            @endphp
            <div @class(['sipma-checklist-group', 'is-'.$kind])>
                <div class="sipma-checklist-group-head">
                    <h3 class="sipma-check-heading">{{ __('agent.students.checklist_group', ['label' => $group['label'], 'done' => $groupDone, 'total' => $items->count()]) }}</h3>
                    <span @class(['sipma-pill', $complete ? 'sipma-tone-success' : 'sipma-tone-warning'])>
                        {{ $complete ? __('agent.students.checklist_complete') : $group['missing'] }}
                    </span>
                </div>
                <ul class="sipma-checklist-items">
                    @foreach ($items as $item)
                        <li @class(['is-done' => $item['done'], 'is-fix' => ! $item['done'] && ($item['status'] ?? null) !== null])>
                            <x-filament::icon :icon="$item['done'] ? 'lucide-circle-check' : (($item['status'] ?? null) !== null ? 'lucide-circle-alert' : 'lucide-circle-dashed')" class="sipma-check-icon" />
                            <span class="sipma-min-0">{{ $item['label'] }}</span>
                            @if ($kind === 'document')
                                @php
                                    // Ada (menunggu/disetujui) · perlu diperbaiki (revisi/ditolak) · kosong (belum diunggah, merah: wajib)
                                    [$tone, $text] = match (true) {
                                        $item['done'] => ['success', __('agent.students.checklist_present')],
                                        $item['status'] !== null => ['danger', __('agent.students.checklist_fix')],
                                        default => ['danger', __('agent.students.checklist_absent')],
                                    };
                                @endphp
                                <span @class(['sipma-pill', 'sipma-tone-'.$tone])>{{ $text }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>

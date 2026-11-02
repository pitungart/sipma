{{-- Ringkasan rubrik: total SKS, MK selesai, IP --}}
@props(['summary'])

<dl {{ $attributes->class('sipma-def-grid') }}>
    <div>
        <dt>{{ __('workflow.rubric.total') }}</dt>
        <dd>{{ $summary->credits }}</dd>
    </div>
    <div>
        <dt>{{ __('workflow.rubric.completed') }}</dt>
        <dd>{{ $summary->completed }}/{{ $summary->total }}</dd>
    </div>
    <div>
        <dt>{{ __('workflow.rubric.gpa') }}</dt>
        <dd>{{ $summary->gpaLabel() }}</dd>
    </div>
</dl>

{{-- Rubrik akademik saat memilih program (K8): MK aktif tanpa nilai, read-only. Kolom semester hanya
     tampil bila ada MK yang mengisinya. --}}
@props(['program', 'withHeading' => false])

@php
    $courses = $program->courses()->active()->packageOrder()->get();
    $withSemester = $courses->whereNotNull('semester')->isNotEmpty();
@endphp

<div {{ $attributes->class('sipma-rubric') }}>
    @if ($withHeading)
        <p class="sipma-rubric-title">{{ __('workflow.rubric.title') }}</p>
    @endif

    @if ($courses->isEmpty())
        <p class="sipma-rubric-empty">{{ __('workflow.rubric.empty') }}</p>
    @else
        <div class="sipma-rubric-scroll">
            <table class="sipma-rubric-table">
                <thead>
                    <tr>
                        <th>{{ __('workflow.rubric.code') }}</th>
                        <th>{{ __('workflow.rubric.course') }}</th>
                        @if ($withSemester)
                            <th>{{ __('workflow.rubric.semester') }}</th>
                        @endif
                        <th class="is-end">{{ __('workflow.rubric.credits') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                        <tr>
                            <td class="sipma-mono">{{ $course->code }}</td>
                            <td>{{ $course->localizedName() }}</td>
                            @if ($withSemester)
                                <td>{{ $course->semester ?? '—' }}</td>
                            @endif
                            <td class="is-end">{{ $course->credits ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="{{ $withSemester ? 3 : 2 }}">{{ __('workflow.rubric.total') }}</td>
                        <td class="is-end">{{ $courses->sum('credits') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

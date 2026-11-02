{{-- Rubrik mahasiswa: ringkasan program & IP, lalu mata kuliah. Nilai MK yang di-drop tidak ditampilkan. --}}
@php
    use App\Support\EnrollmentPeriod;
@endphp

<x-filament-panels::page>
    @if ($rows->isEmpty())
        <section class="sipma-card sipma-section">
            <x-sipma.empty icon="lucide-book-open" :title="__('student.rubric.empty_title')" :description="__('student.rubric.empty_body')" />
        </section>
    @else
        <section class="sipma-card sipma-section">
            <dl class="sipma-def-grid">
                <div>
                    <dt>{{ __('admin.program.label') }}</dt>
                    <dd>{{ $student->program?->name }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.academic_student.fields.faculty') }}</dt>
                    <dd>{{ $student->program?->faculty?->name }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.applicant.fields.enrollment_period') }}</dt>
                    <dd>{{ EnrollmentPeriod::label($student) ?? '—' }}</dd>
                </div>
            </dl>

            <x-sipma.academic-summary :summary="$summary" class="sipma-subsection" />
        </section>

        <section class="sipma-card sipma-section">
            <div class="sipma-rubric-scroll">
                <table class="sipma-rubric-table">
                    <thead>
                        <tr>
                            <th>{{ __('workflow.rubric.code') }}</th>
                            <th>{{ __('workflow.rubric.course') }}</th>
                            <th class="is-end">{{ __('workflow.rubric.credits') }}</th>
                            <th>{{ __('workflow.rubric.grade') }}</th>
                            <th>{{ __('admin.dashboard.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @class(['is-muted' => $row->isDropped()])>
                                <td class="sipma-mono">{{ $row->course?->code }}</td>
                                <td>{{ $row->course?->localizedName() }}</td>
                                <td class="is-end">{{ $row->effectiveCredits() ?? '—' }}</td>
                                <td>{{ $row->isDropped() ? '—' : ($row->grade?->getLabel() ?? '—') }}</td>
                                <td><x-sipma.course-status :row="$row" :student="$student" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-filament-panels::page>

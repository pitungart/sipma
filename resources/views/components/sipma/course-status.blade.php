{{-- Status MK. Program sudah selesai tetapi nilainya belum ada: "Belum dinilai", bukan "Sedang ditempuh". --}}
@props(['row', 'student'])

@if ($row->status === \App\Enums\StudentCourseStatus::Active && $student->status === \App\Enums\StudentStatus::Completed)
    <span class="sipma-status sipma-tone-warning"><span class="sipma-status-dot"></span>{{ __('workflow.rubric.not_graded') }}</span>
@else
    <x-sipma.status-badge :status="$row->status" />
@endif

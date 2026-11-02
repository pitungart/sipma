{{-- Ringkasan paket saat tabel disaring ke satu program --}}
<div class="sipma-course-summary">
    <span class="sipma-course-summary-name">{{ $program->name }}</span>
    <span class="sipma-pill sipma-tone-primary">{{ trans_choice('admin.course.summary_courses', $count, ['count' => $count]) }}</span>
    <span class="sipma-pill sipma-tone-info">{{ trans_choice('admin.course.summary_credits', $credits, ['count' => $credits]) }}</span>
</div>

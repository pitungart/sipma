{{-- Pratinjau berkas privat di dalam modal; tautan unduh untuk berkas yang tidak bisa dipratinjau browser --}}
<div class="sipma-preview">
    @if ($image)
        <img src="{{ $url }}" alt="{{ $title }}" class="sipma-preview-image">
    @else
        <iframe src="{{ $url }}" title="{{ $title }}" class="sipma-preview-frame"></iframe>
    @endif

    <a href="{{ $url }}?download=1" class="sipma-link">{{ __('admin.applicant.actions.download') }}</a>
</div>

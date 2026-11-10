{{-- Isi modal aksi: apa fungsi aksi ini, dan peringatan bila ada --}}
<div class="sipma-action-info">
    <x-sipma.notice :title="__('admin.notice.action_title')">{{ $text }}</x-sipma.notice>

    @if (filled($warning ?? null))
        <x-sipma.notice tone="warning" :title="__('admin.notice.warning_title')">{{ $warning }}</x-sipma.notice>
    @endif
</div>

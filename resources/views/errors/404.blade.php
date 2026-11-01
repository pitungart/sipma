<x-portal.error-page code="404" :title="__('portal.errors.404.title')" :message="__('portal.errors.404.message')">
    {{-- Alamat yang dibuka, agar salah ketik mudah terlihat --}}
    <p class="mx-auto mt-4 max-w-full truncate">
        <span class="sr-only">{{ __('portal.errors.404.address') }}</span>
        <code class="rounded-control bg-card/80 px-2.5 py-1 text-caption text-ink-muted ring-1 ring-inset ring-line">/{{ ltrim(request()->path(), '/') }}</code>
    </p>
</x-portal.error-page>

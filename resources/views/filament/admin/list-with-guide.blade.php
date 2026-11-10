{{-- Daftar dengan panduan fungsi tombol di atas tabel. Halamannya menyediakan actionGuide() dan actionGuideIntro() (boleh kosong). --}}
<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        @php($guide = $this->actionGuide())

        @if ($guide !== [])
            <x-sipma.notice :title="__('admin.notice.guide_title')">
                @if (filled($intro = $this->actionGuideIntro()))
                    <p>{{ $intro }}</p>
                @endif
                <ul>
                    @foreach ($guide as $item)
                        <li><strong>{{ $item['label'] }}:</strong> {{ $item['text'] }}</li>
                    @endforeach
                </ul>
            </x-sipma.notice>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>

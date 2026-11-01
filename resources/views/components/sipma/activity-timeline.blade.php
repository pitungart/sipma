{{-- Timeline aktivitas (widget dashboard, tab Riwayat pendaftar): node ikon bertone yang
     disambung garis vertikal. Item dari ActivityDescriber::describe(); $withDate menambah
     tanggal-jam lengkap di depan waktu relatif. --}}
@props(['items', 'withDate' => false])

<ol {{ $attributes->class('sipma-timeline') }}>
    @foreach ($items as $item)
        <li class="sipma-timeline-item">
            <span class="sipma-timeline-node sipma-tone-{{ $item['tone'] }}">
                <x-filament::icon :icon="$item['icon']" class="sipma-timeline-icon" />
            </span>
            <div class="sipma-timeline-body">
                <div class="sipma-timeline-text">{{ $item['text'] }}</div>
                <div class="sipma-timeline-time">
                    @if ($withDate)
                        <time>{{ $item['at'] }}</time> ·
                    @endif
                    {{ $item['time'] }}
                </div>
            </div>
        </li>
    @endforeach
</ol>

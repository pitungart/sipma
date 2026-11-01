{{-- Tab Pembayaran & LOA sebelum pendaftaran disetujui: dua kartu terkunci (biaya, LOA).
     Tombol nonaktif hanya untuk pemilik pendaftaran (agen / mahasiswa); admin tidak membayar. --}}
@props(['buttons' => true])

<div {{ $attributes->class('sipma-locked-grid') }}>
    @foreach ([
        ['icon' => 'lucide-credit-card', 'tone' => 'primary', 'title' => __('agent.students.payments.fees_title'), 'badge' => __('agent.students.payments.fees_inactive'), 'body' => __('agent.students.payments.fees_locked_body'), 'button' => __('agent.students.payments.pay_now'), 'button_icon' => 'lucide-wallet'],
        ['icon' => 'lucide-award', 'tone' => 'success', 'title' => __('agent.students.payments.loa_title'), 'badge' => __('agent.students.payments.loa_locked'), 'body' => __('agent.students.payments.loa_locked_body'), 'button' => __('agent.students.payments.download_loa'), 'button_icon' => 'lucide-download'],
    ] as $panel)
        <article class="sipma-locked-card">
            <header class="sipma-locked-head">
                <span class="sipma-locked-title">
                    <x-filament::icon :icon="$panel['icon']" @class(['sipma-locked-icon', 'sipma-tone-'.$panel['tone']]) />
                    {{ $panel['title'] }}
                </span>
                <span class="sipma-pill sipma-tone-gray">{{ $panel['badge'] }}</span>
            </header>
            <div class="sipma-locked-body">
                <p>{{ $panel['body'] }}</p>
                @if ($buttons)
                    <x-filament::button color="gray" :icon="$panel['button_icon']" disabled>{{ $panel['button'] }}</x-filament::button>
                @endif
            </div>
        </article>
    @endforeach
</div>

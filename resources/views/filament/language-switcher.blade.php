@use('App\Support\Locale')

{{--
    Pemilih bahasa panel (template): pemicu bendera bulat, panel berjudul "Bahasa" dengan
    bendera, nama bahasa, wilayah, dan tanda centang pada bahasa aktif.
    `teleport` wajib seperti menu profil bawaan: .fi-topbar ber-overflow-x-clip dianggap wadah
    pemotong oleh Floating UI, sehingga panel absolut di-flip ke atas layar.
--}}
<x-filament::dropdown placement="bottom-end" teleport width="xs" class="sipma-lang">
    <x-slot name="trigger">
        <button type="button" class="sipma-icon-btn" aria-label="{{ __('portal.language.label') }}" title="{{ __('portal.language.label') }}">
            <img src="{{ asset('images/flags/'.app()->getLocale().'.svg') }}" alt="" class="sipma-flag">
            <span class="sr-only">{{ __('portal.language.'.app()->getLocale()) }}</span>
        </button>
    </x-slot>

    <div class="sipma-menu-kicker">{{ __('portal.language.label') }}</div>

    <x-filament::dropdown.list>
        @foreach (Locale::SUPPORTED as $code)
            <x-filament::dropdown.list.item
                tag="a"
                :href="route('locale.switch', $code)"
                :aria-current="app()->getLocale() === $code ? 'true' : null"
            >
                <span class="sipma-lang-option">
                    <img src="{{ asset('images/flags/'.$code.'.svg') }}" alt="" class="sipma-flag sipma-flag-sm">
                    <span class="sipma-lang-option-text">
                        <span class="sipma-lang-option-name" lang="{{ $code }}">{{ __('portal.language.'.$code) }}</span>
                        <span class="sipma-lang-option-region">{{ __('portal.language.region.'.$code) }}</span>
                    </span>
                    @if (app()->getLocale() === $code)
                        <x-filament::icon icon="lucide-check" class="sipma-lang-check" />
                    @endif
                </span>
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>

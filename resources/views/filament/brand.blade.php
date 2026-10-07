{{-- Lockup merek di kepala sidebar: logo (atau kotak "S") + nama sistem + institusi --}}
@use('App\Filament\Support\SipmaTheme')

@php($logo = SipmaTheme::logoPath())

<span class="sipma-brand">
    @if ($logo)
        <img src="{{ asset($logo) }}" alt="{{ __('portal.brand.logo_alt') }}" class="sipma-brand-logo">
    @else
        <span class="sipma-brand-mark" aria-hidden="true">S</span>
    @endif

    <span class="sipma-brand-text">
        <span class="sipma-brand-name">SIPMA</span>
        <span class="sipma-brand-org">{{ __('admin.shell.org') }}</span>
    </span>
</span>

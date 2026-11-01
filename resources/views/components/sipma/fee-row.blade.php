{{-- Satu baris biaya pendaftaran (admin, agen, mahasiswa): nominal, rekening tujuan, bukti bayar
     terbaru beserta statusnya. Item dari ApplicantDetails::fees(); tombol dikirim lewat slot. --}}
@props(['fee'])

@php
    use App\Enums\PaymentStatus;

    $payment = $fee['payment'];
    $account = $fee['account'];
@endphp

<li {{ $attributes->class('sipma-row') }}>
    <span @class(['sipma-tile', 'sipma-tone-'.($payment?->status->getColor() ?? 'warning')])>
        <x-filament::icon icon="lucide-receipt" class="sipma-tile-icon" />
    </span>
    <div class="sipma-row-main">
        <div class="sipma-row-title">
            {{ $fee['label'] }}
            <span class="sipma-pay-amount">{{ $fee['amount'] }}</span>
            @if ($payment?->receipt_number)
                <span class="sipma-row-tag sipma-mono">{{ __('admin.payment.fields.receipt_number') }} {{ $payment->receipt_number }}</span>
            @endif
        </div>
        <div class="sipma-row-meta">
            @if ($account)
                {{ $account->bank_name }} · {{ $account->account_name }} ·
                <span class="sipma-mono sipma-pay-va">VA {{ $account->va_number }}</span>
            @else
                {{ __('agent.students.payments.no_account') }}
            @endif
            @if ($payment)
                · {{ __('agent.students.payments.uploaded_at', ['date' => $payment->updated_at->translatedFormat('j M Y, H.i')]) }}
            @endif
        </div>
        @if ($payment?->status === PaymentStatus::Rejected && filled($payment->rejection_note))
            <div class="sipma-row-note"><b>{{ __('agent.mou.note') }}</b> {{ $payment->rejection_note }}</div>
        @endif
    </div>
    @if ($payment)
        <x-sipma.status-badge :status="$payment->status" />
    @else
        <span class="sipma-status sipma-tone-warning"><span class="sipma-status-dot"></span>{{ __('admin.payment.unpaid') }}</span>
    @endif
    @if ($slot->isNotEmpty())
        <div class="sipma-row-actions">{{ $slot }}</div>
    @endif
</li>

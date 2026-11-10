{{-- KTM mahasiswa: belum terbit, dicabut (tanpa unduhan), atau pratinjau kartu + Unduh JPG / Cetak --}}
@php
    use App\Enums\StudentCardStatus;
    use App\Filament\App\Pages\StudentCard;
@endphp

<x-filament-panels::page>
    @if (! $card)
        <section class="sipma-card sipma-section">
            <x-sipma.empty icon="lucide-id-card" :title="__('student.card.pending_title')" :description="__('student.card.pending_body')" />
        </section>
    @elseif ($revoked)
        <section class="sipma-card sipma-section">
            <x-sipma.empty
                icon="lucide-id-card"
                :title="__('student.card.revoked_title', ['number' => $card->card_number])"
                :description="__('student.card.revoked_body', ['date' => $card->revoked_at?->translatedFormat('j F Y'), 'reason' => $card->revoke_reason])"
            />
        </section>
    @else
        @if ($card->status === StudentCardStatus::Expired)
            <x-sipma.notice tone="warning" :title="__('student.card.expired_title')" class="sipma-page-alert">{{ __('student.card.expired_notice') }}</x-sipma.notice>
        @endif

        <section class="sipma-card sipma-section sipma-idcard-page">
            <div class="sipma-idcard-frame">
                <img src="{{ StudentCard::fileUrl($card) }}" alt="{{ __('student.card.title') }} {{ $card->card_number }}" class="sipma-idcard">
            </div>

            <div class="sipma-idcard-side">
                <dl class="sipma-agent-facts">
                    <div>
                        <dt>{{ __('admin.student_card.fields.status') }}</dt>
                        <dd><x-sipma.status-badge :status="$card->status" /></dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.card_number') }}</dt>
                        <dd class="sipma-mono">{{ $card->card_number }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.issued_at') }}</dt>
                        <dd>{{ $card->issued_at->translatedFormat('j F Y') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.valid_until') }}</dt>
                        <dd>{{ $card->valid_until->translatedFormat('j F Y') }}</dd>
                    </div>
                </dl>

                <div class="sipma-actions-inline sipma-idcard-actions">
                    <x-filament::button tag="a" :href="StudentCard::fileUrl($card, download: true)" icon="lucide-download">
                        {{ __('student.card.download') }}
                    </x-filament::button>
                    <x-filament::button tag="a" :href="StudentCard::fileUrl($card)" target="_blank" color="gray" icon="lucide-printer">
                        {{ __('student.card.print') }}
                    </x-filament::button>
                </div>
            </div>
        </section>
    @endif
</x-filament-panels::page>

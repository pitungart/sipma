@php
    use App\Enums\DocumentStatus;
    use App\Enums\PaymentStatus;
    use App\Enums\StudentStatus;
    use App\Support\Rupiah;

    $record = $this->record;
    $tabs = $canVerify
        ? ['profile' => __('admin.applicant.tabs.profile'), 'documents' => __('admin.applicant.tabs.documents'), 'payments' => __('admin.applicant.tabs.payments'), 'history' => __('admin.applicant.tabs.history')]
        : ['profile' => __('admin.applicant.tabs.profile'), 'history' => __('admin.applicant.tabs.history')];
@endphp

<x-filament-panels::page class="sipma-applicant">
    <div class="sipma-applicant-status">
        <x-sipma.status-badge :status="$record->status" />
        <x-sipma.status-timeline :status="$record->status" />
    </div>

    @if ($record->status === StudentStatus::Revision && filled($record->revision_note))
        <div class="sipma-alert sipma-tone-danger" role="status">
            <x-filament::icon icon="lucide-triangle-alert" class="sipma-alert-icon" />
            <div><b>{{ __('admin.applicant.revision_note') }}</b> {{ $record->revision_note }}</div>
        </div>
    @endif

    <div class="sipma-card sipma-clip" x-data="{ tab: 'profile' }">
        <nav class="sipma-master-tabs" role="tablist">
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    role="tab"
                    class="sipma-master-tab"
                    x-on:click="tab = @js($key)"
                    x-bind:class="{ 'is-active': tab === @js($key) }"
                    x-bind:aria-selected="(tab === @js($key)).toString()"
                >
                    <span>{{ $label }}</span>
                    @if ($key === 'documents')
                        <span class="sipma-master-tab-count">{{ $documents->filter(fn ($d) => $d['document']?->status === DocumentStatus::Approved)->count() }}/{{ $documents->count() }}</span>
                    @elseif ($key === 'payments' && $payments->isNotEmpty())
                        <span class="sipma-master-tab-count">{{ $payments->count() }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        {{-- Profil --}}
        <section x-show="tab === 'profile'" class="sipma-section">
            <dl class="sipma-def-grid">
                @foreach ($profile as $row)
                    <div>
                        <dt>{{ $row['label'] }}</dt>
                        <dd @class(['sipma-mono' => $row['mono'] ?? false])>{{ $row['value'] ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($canVerify)
            {{-- Dokumen --}}
            <section x-show="tab === 'documents'" x-cloak class="sipma-section">
                @if ($canUpload)
                    <p class="sipma-section-hint">{{ __('admin.applicant.upload_on_behalf') }}</p>
                @elseif (! $canReview)
                    <p class="sipma-section-hint">{{ __('admin.applicant.review_locked', ['status' => $record->status->getLabel()]) }}</p>
                @endif

                <ul class="sipma-rows">
                    @foreach ($documents as $item)
                        @php($doc = $item['document'])
                        <li class="sipma-row">
                            <span @class(['sipma-tile', 'sipma-tone-'.($doc?->status->getColor() ?? 'gray')])>
                                <x-filament::icon icon="lucide-file-text" class="sipma-tile-icon" />
                            </span>
                            <div class="sipma-row-main">
                                <div class="sipma-row-title">
                                    {{ $item['type']->getLabel() }}
                                    @if ($item['type']->isRequired())
                                        <span class="sipma-row-tag">{{ __('admin.applicant.required') }}</span>
                                    @endif
                                </div>
                                <div class="sipma-row-meta">
                                    @if ($doc)
                                        {{ $doc->original_name }} · {{ \Illuminate\Support\Number::fileSize($doc->file_size ?? 0) }} · {{ $doc->updated_at->translatedFormat('j M Y') }}
                                    @else
                                        {{ __('admin.applicant.not_uploaded') }}
                                    @endif
                                </div>
                                @if ($doc && filled($doc->revision_note) && $doc->status !== DocumentStatus::Approved)
                                    <div class="sipma-row-note">{{ $doc->revision_note }}</div>
                                @endif
                            </div>
                            @if ($doc)
                                <x-sipma.status-badge :status="$doc->status" />
                                <div class="sipma-row-actions">
                                    {{ ($this->previewDocumentAction)(['document' => $doc->getKey()]) }}
                                    @if ($canUpload && $doc->status !== DocumentStatus::Approved)
                                        {{ ($this->uploadDocumentAction)(['type' => $item['type']->value]) }}
                                    @endif
                                    @if ($canReview)
                                        @if ($doc->status !== DocumentStatus::Approved)
                                            {{ ($this->approveDocumentAction)(['document' => $doc->getKey()]) }}
                                        @endif
                                        @if ($doc->status !== DocumentStatus::Revision)
                                            {{ ($this->reviseDocumentAction)(['document' => $doc->getKey()]) }}
                                        @endif
                                        @if ($doc->status !== DocumentStatus::Rejected)
                                            {{ ($this->rejectDocumentAction)(['document' => $doc->getKey()]) }}
                                        @endif
                                    @endif
                                </div>
                            @else
                                <span class="sipma-status sipma-tone-gray"><span class="sipma-status-dot"></span>{{ __('admin.applicant.missing') }}</span>
                                @if ($canUpload)
                                    <div class="sipma-row-actions">
                                        {{ ($this->uploadDocumentAction)(['type' => $item['type']->value]) }}
                                    </div>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Pembayaran --}}
            <section x-show="tab === 'payments'" x-cloak class="sipma-section">
                @if (! in_array($record->status, [StudentStatus::Approved, StudentStatus::LoaIssued], true))
                    <p class="sipma-section-hint">{{ __('admin.applicant.payment_after_approval') }}</p>
                @elseif ($fees->isEmpty())
                    <p class="sipma-section-hint">{{ __('admin.applicant.no_fees') }}</p>
                @endif

                @if ($fees->isNotEmpty())
                    <ul class="sipma-fees">
                        @foreach ($fees as $fee)
                            <li>
                                <span>{{ $fee['label'] }}</span>
                                <b>{{ $fee['amount'] }}</b>
                                @if ($fee['status'])
                                    <x-sipma.status-badge :status="$fee['status']" />
                                @else
                                    <span class="sipma-status sipma-tone-gray"><span class="sipma-status-dot"></span>{{ __('admin.payment.unpaid') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <ul class="sipma-rows">
                    @foreach ($payments as $payment)
                        <li class="sipma-row">
                            <span class="sipma-tile sipma-tone-{{ $payment->status->getColor() }}">
                                <x-filament::icon icon="lucide-receipt" class="sipma-tile-icon" />
                            </span>
                            <div class="sipma-row-main">
                                <div class="sipma-row-title">
                                    {{ $payment->type->getLabel() }} · {{ Rupiah::format($payment->amount) }}
                                    @if ($payment->receipt_number)
                                        <span class="sipma-row-tag">{{ __('admin.payment.fields.receipt_number') }} {{ $payment->receipt_number }}</span>
                                    @endif
                                </div>
                                <div class="sipma-row-meta">
                                    {{ collect([$payment->paymentAccount?->bank_name, $payment->va_number ? 'VA '.$payment->va_number : null])->filter()->implode(' · ') ?: __('admin.payment.no_account') }}
                                    · {{ $payment->created_at->translatedFormat('j M Y, H.i') }}
                                </div>
                                @if ($payment->status === PaymentStatus::Rejected && filled($payment->rejection_note))
                                    <div class="sipma-row-note">{{ $payment->rejection_note }}</div>
                                @endif
                            </div>
                            <x-sipma.status-badge :status="$payment->status" />
                            <div class="sipma-row-actions">
                                {{ ($this->previewPaymentAction)(['payment' => $payment->getKey()]) }}
                                @if ($payment->status === PaymentStatus::Pending)
                                    {{ ($this->verifyPaymentAction)(['payment' => $payment->getKey()]) }}
                                    {{ ($this->rejectPaymentAction)(['payment' => $payment->getKey()]) }}
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($record->loa)
                    <div class="sipma-row sipma-row-loa">
                        <span class="sipma-tile sipma-tone-success"><x-filament::icon icon="lucide-file-check" class="sipma-tile-icon" /></span>
                        <div class="sipma-row-main">
                            <div class="sipma-row-title">{{ __('admin.applicant.loa') }} {{ $record->loa->loa_number }}</div>
                            <div class="sipma-row-meta">{{ $record->loa->status->getLabel() }} · {{ $record->loa->issued_at?->translatedFormat('j M Y') }}</div>
                        </div>
                        <a href="{{ route('files.loa', $record->loa) }}" class="sipma-link">{{ __('admin.applicant.actions.download') }}</a>
                    </div>
                @endif
            </section>
        @endif

        {{-- Riwayat --}}
        <section x-show="tab === 'history'" x-cloak class="sipma-section">
            @if ($history->isEmpty())
                <x-sipma.empty icon="lucide-history" :title="__('admin.dashboard.activity_empty')" />
            @else
                <ul class="sipma-timeline">
                    @foreach ($history as $item)
                        <li class="sipma-timeline-item">
                            <span class="sipma-timeline-dot sipma-tone-{{ $item['tone'] }}"></span>
                            <div>
                                <div class="sipma-timeline-text">{{ $item['text'] }}</div>
                                <div class="sipma-timeline-time">{{ $item['at'] }} · {{ $item['time'] }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>

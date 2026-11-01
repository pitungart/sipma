@php
    use App\Enums\DocumentStatus;
    use App\Enums\PaymentStatus;
    use App\Enums\StudentStatus;

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

    {{-- Biaya program / VA belum siap: tombol Setujui nonaktif, beri tahu sebabnya di muka --}}
    @if ($paymentSetupProblem)
        <x-sipma.notice tone="warning" icon="lucide-triangle-alert">{{ $paymentSetupProblem }}</x-sipma.notice>
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
                        <span class="sipma-master-tab-count">{{ $documents->filter(fn ($d) => $d['type']->isRequired() && $d['document']?->status === DocumentStatus::Approved)->count() }}/{{ count(\App\Enums\DocumentType::required()) }}</span>
                    @elseif ($key === 'payments' && $paymentsOpen)
                        <span class="sipma-master-tab-count">{{ $fees->where('status', PaymentStatus::Verified)->count() }}/{{ $fees->count() }}</span>
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
                    <x-sipma.notice>{{ __('admin.applicant.upload_on_behalf') }}</x-sipma.notice>
                @elseif (! $canReview)
                    <x-sipma.notice tone="warning" icon="lucide-lock">{{ __('admin.applicant.review_locked', ['status' => $record->status->getLabel()]) }}</x-sipma.notice>
                @endif

                <ul class="sipma-rows">
                    @foreach ($documents as $item)
                        @php
                            $doc = $item['document'];
                        @endphp
                        <x-sipma.document-row :item="$item" wire:key="doc-{{ $item['type']->value }}-{{ $doc?->getKey() }}">
                            {{-- Template dari KUI: hanya unduh; dikelola di Sistem → Unggahan berkas --}}
                            @if (in_array($item['type']->value, $templates, true))
                                {{ ($this->downloadTemplateAction)(['type' => $item['type']->value]) }}
                            @endif
                            @if ($doc)
                                {{ ($this->previewDocumentAction)(['document' => $doc->getKey()]) }}
                            @endif
                            @if ($canUpload && $doc?->status !== DocumentStatus::Approved)
                                {{ ($this->uploadDocumentAction)(['type' => $item['type']->value]) }}
                            @endif
                            @if ($doc && $canReview)
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
                        </x-sipma.document-row>
                    @endforeach
                </ul>
            </section>

            {{-- Pembayaran --}}
            <section x-show="tab === 'payments'" x-cloak class="sipma-section">
                @if (! $paymentsOpen)
                    <x-sipma.payments-locked :buttons="false" />
                @else
                    <ul class="sipma-rows">
                        @foreach ($fees as $fee)
                            @php
                                $payment = $fee['payment'];
                            @endphp
                            <x-sipma.fee-row :fee="$fee" wire:key="fee-{{ $fee['type']->value }}-{{ $payment?->getKey() }}">
                                @if ($payment)
                                    {{ ($this->previewPaymentAction)(['payment' => $payment->getKey()]) }}
                                @endif
                                @if ($payment?->status === PaymentStatus::Pending)
                                    {{ ($this->verifyPaymentAction)(['payment' => $payment->getKey()]) }}
                                    {{ ($this->rejectPaymentAction)(['payment' => $payment->getKey()]) }}
                                @endif
                            </x-sipma.fee-row>
                        @endforeach
                    </ul>

                    <x-sipma.loa-row :loa="$record->loa" />
                @endif
            </section>
        @endif

        {{-- Riwayat --}}
        <section x-show="tab === 'history'" x-cloak class="sipma-section">
            @if ($history->isEmpty())
                <x-sipma.empty icon="lucide-history" :title="__('admin.dashboard.activity_empty')" />
            @else
                <x-sipma.activity-timeline :items="$history" with-date />
            @endif
        </section>
    </div>
</x-filament-panels::page>

{{-- Detail pendaftaran dari sisi pemilik (agen / mahasiswa mandiri): status, kelengkapan (UC-11),
     tab Profil · Dokumen · Pembayaran & LOA. Data dari ManagesOwnApplication::applicationViewData(). --}}
@php
    use App\Enums\DocumentStatus;
    use App\Enums\PaymentStatus;
    use App\Enums\StudentStatus;

    $record = $applicant;
    $requiredTotal = count(\App\Enums\DocumentType::required());
    $done = $checklist->where('done', true)->count();
@endphp

<div class="sipma-applicant-status">
    <x-sipma.status-badge :status="$record->status" />
    <x-sipma.status-timeline :status="$record->status" />
</div>

@if ($record->status === StudentStatus::Revision)
    <div class="sipma-alert sipma-tone-danger" role="status">
        <x-filament::icon icon="lucide-triangle-alert" class="sipma-alert-icon" />
        <div>
            <b>{{ __('agent.students.revision_title') }}</b>
            {{ filled($record->revision_note) ? $record->revision_note : __('agent.students.revision_body') }}
        </div>
    </div>
@endif

{{-- Kelengkapan sebelum diajukan --}}
@if ($checklist->isNotEmpty())
    <section class="sipma-card sipma-clip">
        <header class="sipma-card-head sipma-card-head-ruled">
            <div>
                <h2 class="sipma-card-title">{{ __('agent.students.checklist_title') }}</h2>
                <p class="sipma-card-desc">{{ __('agent.students.checklist_desc') }}</p>
            </div>
            <span @class(['sipma-status', $done === $checklist->count() ? 'sipma-tone-success' : 'sipma-tone-warning'])>
                <span class="sipma-status-dot"></span>{{ $done }}/{{ $checklist->count() }}
            </span>
        </header>
        <div class="sipma-check-progress" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $checklist->count() }}" aria-valuenow="{{ $done }}">
            <span style="width: {{ $checklist->count() ? round($done / $checklist->count() * 100) : 0 }}%"></span>
        </div>
        <div class="sipma-check-groups">
            @foreach (['field' => __('agent.students.checklist_fields'), 'document' => __('agent.students.checklist_documents')] as $kind => $heading)
                <div>
                    <h3 class="sipma-check-heading">{{ $heading }}</h3>
                    <ul class="sipma-check-list">
                        @foreach ($checklist->where('kind', $kind) as $item)
                            <li @class(['is-done' => $item['done']])>
                                <x-filament::icon :icon="$item['done'] ? 'lucide-circle-check' : 'lucide-circle'" class="sipma-check-icon" />
                                <span>{{ $item['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
@endif

<div class="sipma-card sipma-clip" x-data="{ tab: @js($canPay || $loa ? 'payments' : ($canChange ? 'documents' : 'profile')) }">
    <nav class="sipma-master-tabs" role="tablist">
        @foreach (['profile' => __('admin.applicant.tabs.profile'), 'documents' => __('admin.applicant.tabs.documents'), 'payments' => __('admin.applicant.tabs.payments')] as $key => $label)
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
                    <span class="sipma-master-tab-count">{{ $requiredDone }}/{{ $requiredTotal }}</span>
                @elseif ($key === 'payments' && $canPay && $fees->contains(fn ($fee) => $fee['status'] !== PaymentStatus::Verified && $fee['status'] !== PaymentStatus::Pending))
                    <span class="sipma-master-tab-dot" title="{{ __('agent.students.payments.action_needed') }}"></span>
                @endif
            </button>
        @endforeach
    </nav>

    {{-- Data --}}
    <section x-show="tab === 'profile'" x-cloak class="sipma-section">
        @if ($canChange)
            <p class="sipma-section-hint">{{ __('agent.students.profile_hint') }}</p>
        @endif
        <dl class="sipma-def-grid">
            @foreach ($profile as $row)
                <div>
                    <dt>{{ $row['label'] }}</dt>
                    <dd @class(['sipma-mono' => $row['mono'] ?? false, 'sipma-dd-empty' => blank($row['value'])])>{{ $row['value'] ?? '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Dokumen --}}
    <section x-show="tab === 'documents'" x-cloak class="sipma-section">
        <p class="sipma-section-hint">
            {{ $canChange ? __('agent.students.documents_intro') : __('agent.students.documents_locked', ['status' => $record->status->getLabel()]) }}
        </p>

        <ul class="sipma-rows">
            @foreach ($documents as $item)
                @php
                    $doc = $item['document'];
                    $needsFix = in_array($doc?->status, [DocumentStatus::Revision, DocumentStatus::Rejected], true);
                @endphp
                <li class="sipma-row" wire:key="doc-{{ $item['type']->value }}-{{ $doc?->getKey() }}">
                    <span @class(['sipma-tile', 'sipma-tone-'.($doc?->status->getColor() ?? 'gray')])>
                        <x-filament::icon :icon="$needsFix ? 'lucide-file-warning' : 'lucide-file-text'" class="sipma-tile-icon" />
                    </span>
                    <div class="sipma-row-main">
                        <div class="sipma-row-title">
                            {{ $item['type']->getLabel() }}
                            <span class="sipma-row-tag">{{ $item['type']->isRequired() ? __('admin.applicant.required') : __('agent.students.optional') }}</span>
                        </div>
                        <div class="sipma-row-meta">
                            @if ($doc)
                                {{ $doc->original_name }} · {{ \Illuminate\Support\Number::fileSize($doc->file_size ?? 0) }} · {{ $doc->updated_at->translatedFormat('j M Y') }}
                            @else
                                {{ $item['type']->hint() }}
                            @endif
                        </div>
                        @if ($needsFix && filled($doc->revision_note))
                            <div class="sipma-row-note"><b>{{ __('agent.mou.note') }}</b> {{ $doc->revision_note }}</div>
                        @endif
                    </div>
                    @if ($doc)
                        <x-sipma.status-badge :status="$doc->status" />
                    @else
                        <span class="sipma-status sipma-tone-gray"><span class="sipma-status-dot"></span>{{ __('admin.applicant.missing') }}</span>
                    @endif
                    <div class="sipma-row-actions">
                        @if ($doc)
                            {{ ($this->previewDocumentAction)(['document' => $doc->getKey()]) }}
                        @endif
                        @if ($canChange && $doc?->status !== DocumentStatus::Approved)
                            {{ ($this->uploadDocumentAction)(['type' => $item['type']->value]) }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Pembayaran & LOA (UC-10, UC-02) --}}
    <section x-show="tab === 'payments'" x-cloak class="sipma-section">
        @if (! $paymentsOpen)
            <x-sipma.empty icon="lucide-wallet" :title="__('agent.students.payments.locked_title')" :description="__('agent.students.payments.locked_body')" />
        @else
            @if ($fees->isEmpty())
                <p class="sipma-section-hint">{{ __('agent.students.payments.no_fees') }}</p>
            @elseif ($canPay)
                <ol class="sipma-pay-steps">
                    <li><span class="sipma-agent-rule-num">1</span><span>{{ __('agent.students.payments.step_transfer') }}</span></li>
                    <li><span class="sipma-agent-rule-num">2</span><span>{{ __('agent.students.payments.step_upload', ['max' => \App\Models\Document::MAX_FILE_SIZE_KB]) }}</span></li>
                    <li><span class="sipma-agent-rule-num">3</span><span>{{ __('agent.students.payments.step_wait') }}</span></li>
                </ol>
            @endif

            <ul class="sipma-rows">
                @foreach ($fees as $fee)
                    @php
                        $payment = $fee['payment'];
                        $account = $fee['account'];
                    @endphp
                    <li class="sipma-row" wire:key="fee-{{ $fee['type']->value }}-{{ $payment?->getKey() }}">
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
                        <div class="sipma-row-actions">
                            @if ($payment)
                                {{ ($this->previewPaymentAction)(['payment' => $payment->getKey()]) }}
                            @endif
                            @if ($canPay && $payment?->status !== PaymentStatus::Verified)
                                {{ ($this->uploadPaymentAction)(['type' => $fee['type']->value]) }}
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- LOA --}}
            <div class="sipma-row sipma-row-loa">
                <span @class(['sipma-tile', $loa ? 'sipma-tone-success' : 'sipma-tone-gray'])>
                    <x-filament::icon icon="lucide-file-check" class="sipma-tile-icon" />
                </span>
                <div class="sipma-row-main">
                    <div class="sipma-row-title">
                        {{ __('admin.applicant.loa') }}
                        @if ($loa?->loa_number)
                            <span class="sipma-row-tag sipma-mono">{{ $loa->loa_number }}</span>
                        @endif
                    </div>
                    <div class="sipma-row-meta">
                        @if ($loa)
                            {{ __('agent.students.payments.loa_issued', ['date' => $loa->issued_at?->translatedFormat('j F Y') ?? $loa->created_at->translatedFormat('j F Y')]) }}
                            @if ($loa->downloaded_at)
                                · {{ __('agent.students.payments.loa_downloaded', ['date' => $loa->downloaded_at->translatedFormat('j M Y')]) }}
                            @endif
                        @else
                            {{ $fees->isEmpty() ? __('agent.students.payments.loa_waiting_free') : __('agent.students.payments.loa_waiting') }}
                        @endif
                    </div>
                </div>
                @if ($loa)
                    <x-filament::button tag="a" :href="route('files.loa', $loa)" icon="lucide-download" size="sm">
                        {{ __('agent.students.payments.download_loa') }}
                    </x-filament::button>
                @else
                    <span class="sipma-status sipma-tone-gray"><span class="sipma-status-dot"></span>{{ __('agent.students.payments.loa_pending') }}</span>
                @endif
            </div>
        @endif
    </section>
</div>

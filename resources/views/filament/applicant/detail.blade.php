{{-- Detail pendaftaran dari sisi pemilik (agen / mahasiswa mandiri): status, kelengkapan (UC-11),
     tab Profil · Dokumen · Pembayaran & LOA. Data dari ManagesOwnApplication::applicationViewData(). --}}
@php
    use App\Enums\DocumentStatus;
    use App\Enums\PaymentStatus;
    use App\Enums\StudentStatus;

    $record = $applicant;
    $requiredTotal = count(\App\Enums\DocumentType::required());
@endphp

<x-sipma.status-timeline :status="$record->status" />

@if ($record->status === StudentStatus::Revision)
    <x-sipma.notice tone="danger" icon="lucide-triangle-alert" :title="__('agent.students.revision_title')" role="status" class="sipma-page-alert">
        {{ filled($record->revision_note) ? $record->revision_note : __('agent.students.revision_body') }}
    </x-sipma.notice>
@endif

{{-- Kelengkapan sebelum diajukan --}}
@if ($checklist->isNotEmpty())
    <x-sipma.application-checklist :checklist="$checklist" />
@endif

<div class="sipma-card sipma-clip" x-data="{ tab: @js($canPay || $loa ? 'payments' : ($canChange ? 'documents' : 'profile')) }">
    <nav class="sipma-master-tabs" role="tablist">
        @foreach (['profile' => ['lucide-user', __('admin.applicant.tabs.profile')], 'documents' => ['lucide-folder', __('admin.applicant.tabs.documents')], 'payments' => ['lucide-credit-card', __('admin.applicant.tabs.payments')]] as $key => [$icon, $label])
            <button
                type="button"
                role="tab"
                class="sipma-master-tab"
                x-on:click="tab = @js($key)"
                x-bind:class="{ 'is-active': tab === @js($key) }"
                x-bind:aria-selected="(tab === @js($key)).toString()"
            >
                <x-filament::icon :icon="$icon" class="sipma-master-tab-icon" />
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
            <x-sipma.notice :title="__('agent.students.profile_hint_title')">
                {!! __('agent.students.profile_hint', ['button' => '<b>"'.e(__('admin.applicant.actions.edit')).'"</b>']) !!}
            </x-sipma.notice>
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
        @if ($canChange)
            <x-sipma.notice :title="__('agent.students.documents_intro_title')">{{ __('agent.students.documents_intro') }}</x-sipma.notice>
        @else
            <x-sipma.notice tone="warning" icon="lucide-lock" :title="__('agent.students.documents_locked_title')">{{ __('agent.students.documents_locked', ['status' => $record->status->getLabel()]) }}</x-sipma.notice>
        @endif

        <ul class="sipma-rows">
            @foreach ($documents as $item)
                @php
                    $doc = $item['document'];
                @endphp
                <x-sipma.document-row :item="$item" wire:key="doc-{{ $item['type']->value }}-{{ $doc?->getKey() }}">
                    {{-- Template dari KUI (hanya unduh), bila Super Admin sudah mengunggahnya --}}
                    @if (in_array($item['type']->value, $templates, true))
                        {{ ($this->downloadTemplateAction)(['type' => $item['type']->value]) }}
                    @endif
                    @if ($doc)
                        {{ ($this->previewDocumentAction)(['document' => $doc->getKey()]) }}
                    @endif
                    @if ($canChange && $doc?->status !== DocumentStatus::Approved)
                        {{ ($this->uploadDocumentAction)(['type' => $item['type']->value]) }}
                    @endif
                </x-sipma.document-row>
            @endforeach
        </ul>
    </section>

    {{-- Pembayaran & LOA (UC-10, UC-02) --}}
    <section x-show="tab === 'payments'" x-cloak class="sipma-section">
        @if (! $paymentsOpen)
            <x-sipma.payments-locked />
        @else
            @if ($canPay)
                <ol class="sipma-pay-steps">
                    <li><span class="sipma-agent-rule-num">1</span><span>{{ __('agent.students.payments.step_transfer') }}</span></li>
                    <li><span class="sipma-agent-rule-num">2</span><span>{{ __('agent.students.payments.step_upload', ['max' => \App\Support\UploadLimits::label(\App\Support\UploadLimits::paymentProof())]) }}</span></li>
                    <li><span class="sipma-agent-rule-num">3</span><span>{{ __('agent.students.payments.step_wait') }}</span></li>
                </ol>
            @endif

            <ul class="sipma-rows">
                @foreach ($fees as $fee)
                    @php
                        $payment = $fee['payment'];
                    @endphp
                    <x-sipma.fee-row :fee="$fee" wire:key="fee-{{ $fee['type']->value }}-{{ $payment?->getKey() }}">
                        @if ($payment)
                            {{ ($this->previewPaymentAction)(['payment' => $payment->getKey()]) }}
                        @endif
                        @if ($canPay && $payment?->status !== PaymentStatus::Verified)
                            {{ ($this->uploadPaymentAction)(['type' => $fee['type']->value]) }}
                        @endif
                    </x-sipma.fee-row>
                @endforeach
            </ul>

            <x-sipma.loa-row :loa="$loa" />
        @endif
    </section>
</div>

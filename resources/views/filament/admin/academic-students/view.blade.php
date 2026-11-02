{{-- Detail Mahasiswa Asing: ringkasan tahap pasca-LOA, lalu tab Info · Visa · Riwayat
     (KTM, Rubrik, SK Mengajar menyusul lewat ViewAcademicStudent::tabs()). --}}
@php
    use App\Enums\StudentStatus;

    $record = $this->record;
    $tabs = $this->tabs();
@endphp

<x-filament-panels::page class="sipma-applicant">
    <section class="sipma-card">
        <dl class="sipma-def-grid sipma-section">
            @foreach ($facts as $fact)
                <div>
                    <dt>{{ $fact['label'] }}</dt>
                    <dd @class(['sipma-dd-empty' => blank($fact['value']) && ! isset($fact['badge'])])>
                        @if (isset($fact['badge']))
                            <x-sipma.status-badge :status="$fact['badge']" />
                        @elseif (isset($fact['tone']))
                            <span @class(['sipma-status', 'sipma-tone-'.$fact['tone']])><span class="sipma-status-dot"></span>{{ $fact['value'] }}</span>
                        @else
                            {{ $fact['value'] ?? '—' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($record->status === StudentStatus::LoaIssued)
        <x-sipma.notice class="sipma-page-alert">{{ __('admin.academic_student.notice.ready') }}</x-sipma.notice>
    @elseif ($record->user_id === null)
        <x-sipma.notice tone="warning" class="sipma-page-alert">
            {{ __('admin.academic_student.notice.no_account') }} {{ __('admin.academic_student.account.failed_hint') }}
        </x-sipma.notice>
    @endif

    <div class="sipma-card sipma-clip" x-data="{ tab: 'info' }">
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
                </button>
            @endforeach
        </nav>

        {{-- Info: data pribadi; Super Admin juga melihat status dokumen, biaya, dan LOA --}}
        <section x-show="tab === 'info'" class="sipma-section">
            <dl class="sipma-def-grid">
                @foreach ($profile as $row)
                    <div>
                        <dt>{{ $row['label'] }}</dt>
                        <dd @class(['sipma-mono' => $row['mono'] ?? false])>{{ $row['value'] ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($canVerify)
                <div class="sipma-subsection">
                    <h3 class="sipma-card-title">{{ __('admin.academic_student.sections.documents') }}</h3>
                    <p class="sipma-card-desc">{{ __('admin.academic_student.documents_hint') }}</p>
                    <ul class="sipma-rows">
                        @foreach ($documents as $item)
                            <x-sipma.document-row :item="$item" wire:key="doc-{{ $item['type']->value }}" />
                        @endforeach
                    </ul>
                </div>

                <div class="sipma-subsection">
                    <h3 class="sipma-card-title">{{ __('admin.academic_student.sections.payments') }}</h3>
                    <ul class="sipma-rows">
                        @foreach ($fees as $fee)
                            <x-sipma.fee-row :fee="$fee" wire:key="fee-{{ $fee['type']->value }}" />
                        @endforeach
                    </ul>
                    <x-sipma.loa-row :loa="$record->loa" />
                </div>
            @endif
        </section>

        {{-- Visa: diubah KUI, dilihat admin fakultas --}}
        <section x-show="tab === 'visa'" x-cloak class="sipma-section">
            <div class="sipma-subsection-head">
                <h3 class="sipma-card-title">{{ __('admin.academic_student.visa.status') }}</h3>
                @if ($canEditVisa)
                    {{ $this->editVisaAction }}
                @endif
            </div>

            @if ($visa)
                <dl class="sipma-def-grid">
                    <div>
                        <dt>{{ __('admin.academic_student.visa.status') }}</dt>
                        <dd><x-sipma.status-badge :status="$visa->status" /></dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.academic_student.visa.updated_by') }}</dt>
                        <dd @class(['sipma-dd-empty' => ! $visa->updater])>{{ $visa->updater?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.academic_student.visa.updated_at') }}</dt>
                        <dd>{{ $visa->updated_at?->translatedFormat('j F Y, H:i') }}</dd>
                    </div>
                    <div class="sipma-def-wide">
                        <dt>{{ __('admin.academic_student.visa.notes') }}</dt>
                        <dd @class(['sipma-dd-empty' => blank($visa->notes)])>{{ $visa->notes ?: '—' }}</dd>
                    </div>
                </dl>
            @else
                <x-sipma.empty icon="lucide-plane" :title="__('admin.academic_student.visa.none')" />
            @endif

            @unless ($canEditVisa)
                <x-sipma.notice tone="warning" icon="lucide-lock">{{ __('admin.academic_student.visa.readonly') }}</x-sipma.notice>
            @endunless
        </section>

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

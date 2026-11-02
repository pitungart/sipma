{{-- Detail Mahasiswa Asing: ringkasan tahap pasca-LOA, lalu tab Info · Visa · KTM · Rubrik · Riwayat
     (SK Mengajar menyusul lewat ViewAcademicStudent::tabs()). --}}
@php
    use App\Enums\StudentStatus;

    $record = $this->record;
    $tabs = $this->tabs();
    $initialTab = array_key_exists((string) request()->query('tab'), $tabs) ? request()->query('tab') : 'info';
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

    <div class="sipma-card sipma-clip" x-data="{ tab: @js($initialTab) }">
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
        <section x-show="tab === 'info'" x-cloak class="sipma-section">
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

        {{-- KTM: dikelola KUI, dilihat admin fakultas --}}
        <section x-show="tab === 'card'" x-cloak class="sipma-section">
            <div class="sipma-subsection-head">
                <h3 class="sipma-card-title">{{ __('admin.student_card.detail.title') }}</h3>
                <div class="sipma-actions-inline">
                    @if ($activeCard)
                        <x-filament::button tag="a" :href="route('files.student-card', ['card' => $activeCard, 'download' => 1])" color="gray" size="sm" icon="lucide-download">
                            {{ __('admin.student_card.actions.download') }}
                        </x-filament::button>
                    @endif

                    {{-- Dipanggil langsung, action tidak memeriksa visible() sendiri --}}
                    @foreach ([$this->issueCardAction, $this->reissueCardAction, $this->revokeCardAction] as $cardAction)
                        @if ($cardAction->isVisible())
                            {{ $cardAction }}
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($activeCard)
                <dl class="sipma-def-grid">
                    <div>
                        <dt>{{ __('admin.student_card.fields.card_number') }}</dt>
                        <dd class="sipma-mono">{{ $activeCard->card_number }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.status') }}</dt>
                        <dd><x-sipma.status-badge :status="$activeCard->status" /></dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.issued_at') }}</dt>
                        <dd>{{ $activeCard->issued_at->translatedFormat('j F Y') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.valid_until') }}</dt>
                        <dd>{{ $activeCard->valid_until->translatedFormat('j F Y') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.student_card.fields.issued_by') }}</dt>
                        <dd @class(['sipma-dd-empty' => ! $activeCard->issuer])>{{ $activeCard->issuer?->name ?? '—' }}</dd>
                    </div>
                </dl>
            @else
                <x-sipma.empty
                    icon="lucide-id-card"
                    :title="__('admin.student_card.detail.none')"
                    :description="match ($record->status) {
                        StudentStatus::LoaIssued => __('admin.student_card.detail.not_ready'),
                        StudentStatus::Completed => __('admin.student_card.detail.none_completed'),
                        default => $canManageCards ? __('admin.student_card.detail.none_enrolled') : null,
                    }"
                />
            @endif

            @if ($pastCards->isNotEmpty())
                <div class="sipma-subsection">
                    <h3 class="sipma-card-title">{{ __('admin.student_card.detail.history') }}</h3>
                    <ul class="sipma-rows">
                        @foreach ($pastCards as $card)
                            <li class="sipma-row" wire:key="card-{{ $card->getKey() }}">
                                <span @class(['sipma-tile', 'sipma-tone-'.$card->status->getColor()])>
                                    <x-filament::icon icon="lucide-id-card" class="sipma-tile-icon" />
                                </span>
                                <div class="sipma-row-main">
                                    <div class="sipma-row-title sipma-mono">{{ $card->card_number }}</div>
                                    <div class="sipma-row-meta">
                                        {{ __('admin.student_card.detail.history_meta', ['issued' => $card->issued_at->translatedFormat('j M Y'), 'until' => $card->valid_until->translatedFormat('j M Y')]) }}
                                    </div>
                                    @if ($card->revoked_at)
                                        <div class="sipma-row-meta">
                                            {{ __('admin.student_card.detail.revoked_meta', ['date' => $card->revoked_at->translatedFormat('j M Y'), 'reason' => $card->revoke_reason]) }}
                                        </div>
                                    @endif
                                </div>
                                <x-sipma.status-badge :status="$card->status" />
                                <x-filament::icon-button
                                    tag="a"
                                    :href="route('files.student-card', ['card' => $card, 'download' => 1])"
                                    icon="lucide-download"
                                    color="gray"
                                    :tooltip="__('admin.student_card.actions.download')"
                                    :label="__('admin.student_card.actions.download')"
                                />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($canManageCards)
                <x-sipma.notice tone="warning" icon="lucide-lock">{{ __('admin.student_card.detail.readonly') }}</x-sipma.notice>
            @endunless
        </section>

        {{-- Rubrik: MK yang ditempuh; hanya bisa diubah selagi mahasiswa Aktif --}}
        <section x-show="tab === 'rubric'" x-cloak class="sipma-section">
            <div class="sipma-subsection-head">
                <h3 class="sipma-card-title">{{ __('admin.student_course.title') }}</h3>
                <div class="sipma-actions-inline">
                    {{-- Dipanggil langsung, action tidak memeriksa visible() sendiri --}}
                    @foreach ([$this->gradeCoursesAction, $this->applyPackageAction, $this->addCourseAction] as $courseAction)
                        @if ($courseAction->isVisible())
                            {{ $courseAction }}
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($courses->isEmpty())
                <x-sipma.empty
                    icon="lucide-book-open"
                    :title="__('admin.student_course.empty')"
                    :description="$record->status === StudentStatus::LoaIssued ? __('admin.student_course.empty_not_active') : __('admin.student_course.empty_active')"
                />
            @else
                <div class="sipma-rubric-scroll">
                    <table class="sipma-rubric-table">
                        <thead>
                            <tr>
                                <th>{{ __('workflow.rubric.code') }}</th>
                                <th>{{ __('workflow.rubric.course') }}</th>
                                <th>{{ __('workflow.rubric.semester') }}</th>
                                <th class="is-end">{{ __('workflow.rubric.credits') }}</th>
                                <th>{{ __('workflow.rubric.grade') }}</th>
                                <th>{{ __('admin.dashboard.status') }}</th>
                                @if ($canManageCourses)
                                    <th></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($courses as $row)
                                <tr wire:key="course-{{ $row->getKey() }}" @class(['is-muted' => $row->isDropped()])>
                                    <td class="sipma-mono">{{ $row->course?->code }}</td>
                                    <td>
                                        {{ $row->course?->localizedName() }}
                                        @if ($row->course?->program_id !== $record->program_id)
                                            <span class="sipma-row-tag">{{ $row->course?->program?->name }}</span>
                                        @endif
                                        @if ($row->isDropped() && filled($row->notes))
                                            <div class="sipma-row-meta">{{ $row->notes }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $row->course?->semester ?? '—' }}</td>
                                    <td class="is-end">{{ $row->effectiveCredits() ?? '—' }}</td>
                                    <td>{{ $row->grade?->getLabel() ?? '—' }}</td>
                                    <td><x-sipma.course-status :row="$row" :student="$record" /></td>
                                    @if ($canManageCourses)
                                        <td class="is-end">
                                            {{ $row->isDropped() ? ($this->restoreCourseAction)(['row' => $row->getKey()]) : ($this->dropCourseAction)(['row' => $row->getKey()]) }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-sipma.academic-summary :summary="$courseSummary" class="sipma-rubric-note" />
            @endif

            @if ($record->status === StudentStatus::Completed)
                <x-sipma.notice tone="warning" icon="lucide-lock" class="sipma-rubric-note">{{ __('admin.student_course.locked') }}</x-sipma.notice>
            @endif
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

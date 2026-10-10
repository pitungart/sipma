{{-- Dasbor mahasiswa mandiri: kerangka sama dengan panel lain; isi mengikuti tahap pendaftaran --}}
@php
    use App\Support\StudentHome;

    $done = $checklist->where('done', true)->count();
    // Belum ada periode tercatat (draf lama): tampilkan periode program yang sedang dibuka
    $period = $application ? ($application->academicPeriod ?? \App\Models\AcademicPeriod::currentFor($application->program_id)) : null;
@endphp

<x-filament-panels::page class="sipma-agent-home">
    @if ($application)
        <x-sipma.status-timeline :status="$application->status" />
    @endif

    <div class="sipma-agent-grid">
        {{-- Langkah berikutnya --}}
        <section @class(['sipma-card sipma-agent-next', 'sipma-tone-'.$next['tone']])>
            <span class="sipma-agent-next-icon">
                <x-filament::icon :icon="$next['icon']" class="sipma-agent-next-svg" />
            </span>
            <div class="sipma-min-0">
                <p class="sipma-agent-kicker">{{ __(in_array($stage, [StudentHome::WAITING, StudentHome::PAYMENT_REVIEW, StudentHome::LOA], true) ? 'agent.dashboard.status' : 'agent.dashboard.next_step') }}</p>
                <h2 class="sipma-agent-next-title">{{ $next['title'] }}</h2>
                <p class="sipma-agent-next-body">{{ $next['body'] }}</p>

                <div class="sipma-agent-next-ctas">
                    <x-filament::button
                        tag="a"
                        :href="$cta['url']"
                        :color="in_array($stage, [StudentHome::WAITING, StudentHome::PAYMENT_REVIEW], true) ? 'gray' : 'primary'"
                        icon="lucide-arrow-right"
                        icon-position="after"
                    >
                        {{ $cta['label'] }}
                    </x-filament::button>
                    @if ($stage === StudentHome::START)
                        <x-filament::button tag="a" :href="\App\Filament\App\Pages\Programs::getUrl()" color="gray">
                            {{ __('student.home.see_programs') }}
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </section>

        {{-- Ringkasan pendaftaran / yang perlu disiapkan --}}
        <section class="sipma-card sipma-clip">
            @if ($application)
                <header class="sipma-card-head sipma-card-head-ruled">
                    <h2 class="sipma-card-title">{{ __('student.home.your_application') }}</h2>
                    <x-sipma.status-badge :status="$application->status" />
                </header>
                <dl class="sipma-agent-facts">
                    <div class="sipma-agent-facts-wide">
                        <dt>{{ __('admin.program.label') }}</dt>
                        <dd>{{ $application->program?->name }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.period.label') }}</dt>
                        <dd @class(['is-empty' => ! $period])>
                            {{ $period
                                ? __('student.home.period_until', ['period' => $period->name, 'date' => $period->registration_closes_at->translatedFormat('j M Y')])
                                : __('student.home.no_period') }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.applicant.fields.registration_number') }}</dt>
                        <dd @class(['sipma-mono' => $application->registration_number, 'is-empty' => ! $application->registration_number])>
                            {{ $application->registration_number ?? __('admin.applicant.registration_number_pending') }}
                        </dd>
                    </div>
                </dl>
                <footer class="sipma-mou-foot">
                    <a href="{{ \App\Filament\App\Pages\MyApplication::getUrl() }}" class="sipma-link">{{ __('student.home.view_application') }}</a>
                </footer>
            @else
                <header class="sipma-card-head sipma-card-head-ruled">
                    <div>
                        <h2 class="sipma-card-title">{{ __('student.home.prepare_title') }}</h2>
                        <p class="sipma-card-desc">{{ __('student.home.prepare_desc') }}</p>
                    </div>
                </header>
                <ol class="sipma-agent-rules">
                    <li><span class="sipma-agent-rule-num">1</span><span>{{ __('student.home.prepare_passport') }}</span></li>
                    @foreach ($requiredDocuments as $type)
                        @continue($type === \App\Enums\DocumentType::Passport)
                        <li><span class="sipma-agent-rule-num">{{ $loop->iteration + 1 }}</span><span><b>{{ $type->getLabel() }}</b> — {{ $type->hint() }}</span></li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    {{-- Kelengkapan (draf / perlu revisi) --}}
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
                <span style="width: {{ round($done / max(1, $checklist->count()) * 100) }}%"></span>
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

    {{-- Program yang sedang dibuka (sebelum mendaftar) --}}
    @if ($stage === StudentHome::START)
        <section class="sipma-card sipma-clip">
            <header class="sipma-card-head sipma-card-head-ruled">
                <div>
                    <h2 class="sipma-card-title">{{ __('student.programs.open_title') }}</h2>
                    <p class="sipma-card-desc">{{ __('student.programs.description') }}</p>
                </div>
                <a href="{{ \App\Filament\App\Pages\Programs::getUrl() }}" class="sipma-link">{{ __('admin.dashboard.see_all') }}</a>
            </header>
            @if ($openPrograms->isEmpty())
                <x-sipma.empty icon="lucide-graduation-cap" :title="__('student.programs.empty')" :description="__('student.programs.empty_body')" />
            @else
                <ul class="sipma-todo">
                    @foreach ($openPrograms->take(5) as $program)
                        <li class="sipma-todo-row">
                            <span class="sipma-tile sipma-tone-primary"><x-filament::icon icon="lucide-graduation-cap" class="sipma-tile-icon" /></span>
                            <div class="sipma-row-main">
                                <div class="sipma-person-name">{{ $program->name }}</div>
                                <div class="sipma-person-meta">{{ $program->faculty?->name }}</div>
                            </div>
                            <x-filament::button tag="a" :href="\App\Filament\App\Pages\StartApplication::getUrl(['program' => $program->getKey()])" color="gray" size="sm">
                                {{ __('student.programs.choose') }}
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-filament-panels::page>

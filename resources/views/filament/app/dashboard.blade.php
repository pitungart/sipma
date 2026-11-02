{{-- Dasbor mahasiswa mandiri: layout mengikuti referensi (banner langkah berikutnya 7/12 + panel 5/12,
     lalu kartu program), tampilan tetap gaya SIPMA — kartu putih bergaris, warna solid, tanpa gradien/kaca --}}
@php
    use App\Enums\DocumentType;
    use App\Filament\App\Pages\MyApplication;
    use App\Filament\App\Pages\Programs;
    use App\Filament\App\Pages\StartApplication;
    use App\Support\StudentHome;
@endphp

<x-filament-panels::page class="sipma-agent-home">
    @if ($application)
        <x-sipma.status-timeline :status="$application->status" />
    @endif

    <div class="sipma-portal-grid">
        {{-- Langkah berikutnya: kartu primary solid; nada tahap tetap terlihat di titik label --}}
        <section class="sipma-portal-hero">
            {{-- Dekorasi: dua lingkaran buram (lavender & emas) di sisi kanan, jauh dari teks --}}
            <span class="sipma-portal-orb is-1" aria-hidden="true"></span>
            <span class="sipma-portal-orb is-2" aria-hidden="true"></span>

            <div class="sipma-portal-hero-body">
                <span @class(['sipma-portal-kicker', 'sipma-tone-'.$next['tone']])>
                    <span class="sipma-portal-kicker-dot"></span>
                    {{ __(in_array($stage, [StudentHome::WAITING, StudentHome::PAYMENT_REVIEW, StudentHome::LOA, StudentHome::ACTIVE, StudentHome::COMPLETED], true) ? 'student.home.status' : 'student.home.next_step') }}
                </span>
                <h2 class="sipma-portal-hero-title">{{ $next['title'] }}</h2>
                <p class="sipma-portal-hero-text">{{ $next['body'] }}</p>
            </div>

            <div class="sipma-portal-hero-ctas">
                <a href="{{ $cta['url'] }}" class="sipma-portal-btn is-light">
                    <span>{{ $cta['label'] }}</span>
                    <x-filament::icon icon="lucide-arrow-right" class="sipma-portal-btn-icon" />
                </a>
                @if ($stage === StudentHome::START)
                    <a href="{{ Programs::getUrl() }}" class="sipma-portal-btn is-outline">{{ __('student.home.see_programs') }}</a>
                @endif
            </div>
        </section>

        {{-- Ringkasan pendaftaran / yang perlu disiapkan --}}
        <section class="sipma-card sipma-clip sipma-portal-side">
            @if ($application)
                <header class="sipma-card-head sipma-card-head-ruled">
                    <div class="sipma-portal-head">
                        <span class="sipma-tile sipma-tone-primary"><x-filament::icon icon="lucide-file-text" class="sipma-tile-icon" /></span>
                        <h2 class="sipma-card-title">{{ __('student.home.your_application') }}</h2>
                    </div>
                    <x-sipma.status-badge :status="$application->status" />
                </header>
                <dl class="sipma-agent-facts">
                    <div class="sipma-agent-facts-wide">
                        <dt>{{ __('admin.program.label') }}</dt>
                        <dd>{{ $application->program?->name }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.applicant.fields.submitted_at') }}</dt>
                        <dd @class(['is-empty' => ! $application->submitted_at])>
                            {{ $application->submitted_at?->translatedFormat('j M Y') ?? __('student.home.not_submitted') }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('admin.applicant.fields.registration_number') }}</dt>
                        <dd @class(['sipma-mono' => $application->registration_number, 'is-empty' => ! $application->registration_number])>
                            {{ $application->registration_number ?? __('admin.applicant.registration_number_pending') }}
                        </dd>
                    </div>
                </dl>
                <footer class="sipma-portal-foot">
                    <a href="{{ MyApplication::getUrl() }}" class="sipma-link sipma-portal-more is-arrow">
                        {{ __('student.home.view_application') }}
                        <x-filament::icon icon="lucide-arrow-right" class="sipma-portal-more-icon" />
                    </a>
                </footer>
            @else
                <header class="sipma-card-head sipma-card-head-ruled">
                    <div class="sipma-portal-head">
                        <span class="sipma-tile sipma-tone-warning"><x-filament::icon icon="lucide-clipboard-check" class="sipma-tile-icon" /></span>
                        <div class="sipma-min-0">
                            <h2 class="sipma-card-title">{{ __('student.home.prepare_title') }}</h2>
                            {{-- Batas ukuran per jenis dokumen: satu kalimat bila semuanya sama, selain itu tampil di tiap berkas --}}
                            @php $sizes = collect($requiredDocuments)->map(fn (DocumentType $type): int => \App\Support\UploadLimits::document($type))->unique(); @endphp
                            <p class="sipma-card-desc">{{ $sizes->count() === 1 ? __('student.home.prepare_desc', ['max' => \App\Support\UploadLimits::label($sizes->first())]) : __('student.home.prepare_desc_each') }}</p>
                        </div>
                    </div>
                    <span class="sipma-pill sipma-tone-primary">{{ __('student.home.prepare_count', ['count' => count($requiredDocuments)]) }}</span>
                </header>
                <ol class="sipma-portal-prep">
                    {{-- Paspor lebih dulu: nomornya juga diisi di formulir --}}
                    @foreach (collect($requiredDocuments)->sortBy(fn (DocumentType $type): int => $type === DocumentType::Passport ? 0 : 1)->values() as $type)
                        <li>
                            <span class="sipma-portal-num">{{ $loop->iteration }}</span>
                            <div class="sipma-min-0">
                                <p class="sipma-portal-prep-name">{{ $type->getLabel() }}</p>
                                {{-- Format sama untuk semua berkas (kaki kartu); ketentuan khusus dan ukuran yang berbeda di sini --}}
                                @if ($type === DocumentType::Passport)
                                    <p class="sipma-portal-prep-hint">{{ __('student.home.prepare_passport_hint') }}</p>
                                @elseif ($type === DocumentType::Photo)
                                    <p class="sipma-portal-prep-hint">{{ __('workflow.documents.photo_rules') }}</p>
                                @endif
                                @if ($sizes->count() > 1)
                                    <p class="sipma-portal-prep-hint">{{ __('student.home.prepare_max', ['max' => \App\Support\UploadLimits::label(\App\Support\UploadLimits::document($type))]) }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
                <footer class="sipma-portal-foot">
                    <span>{{ __('student.home.prepare_format') }}</span>
                    <a href="{{ Programs::getUrl() }}" class="sipma-link">{{ __('student.home.see_programs') }}</a>
                </footer>
            @endif
        </section>
    </div>

    {{-- KTM (mahasiswa Aktif / Selesai): ringkasan, kartunya di menu KTM --}}
    @if ($application?->status->hasAcademicAccess())
        <section class="sipma-card sipma-portal-section">
            <div class="sipma-portal-card-row">
                <span @class(['sipma-tile', 'sipma-tone-'.($studentCard?->status->getColor() ?? 'gray')])>
                    <x-filament::icon icon="lucide-id-card" class="sipma-tile-icon" />
                </span>
                <div class="sipma-row-main">
                    <h2 class="sipma-card-title">{{ __('student.card.title') }}</h2>
                    <p class="sipma-card-desc">
                        @if ($studentCard)
                            <span class="sipma-mono">{{ $studentCard->card_number }}</span>
                            · {{ __('student.card.valid_until', ['date' => $studentCard->valid_until->translatedFormat('j F Y')]) }}
                        @else
                            {{ __('student.card.pending_title') }}
                        @endif
                    </p>
                </div>
                @if ($studentCard)
                    <x-sipma.status-badge :status="$studentCard->status" />
                @endif
                <a href="{{ \App\Filament\App\Pages\StudentCard::getUrl() }}" class="sipma-link sipma-portal-more">
                    {{ __('student.card.open') }}
                    <x-filament::icon icon="lucide-chevron-right" class="sipma-portal-more-icon" />
                </a>
            </div>
        </section>
    @endif

    {{-- Rubrik (mahasiswa Aktif / Selesai): progres MK, rinciannya di menu Rubrik akademik --}}
    @if ($rubric)
        <section class="sipma-card sipma-portal-section">
            <div class="sipma-portal-card-row">
                <span class="sipma-tile sipma-tone-primary">
                    <x-filament::icon icon="lucide-book-open" class="sipma-tile-icon" />
                </span>
                <div class="sipma-row-main">
                    <h2 class="sipma-card-title">{{ __('workflow.rubric.title') }}</h2>
                    <p class="sipma-card-desc">
                        @if ($rubric->total > 0)
                            {{ __('student.rubric.progress', ['completed' => $rubric->completed, 'total' => $rubric->total]) }}
                            @if ($rubric->gpa !== null)
                                · {{ __('workflow.rubric.gpa') }} {{ $rubric->gpaLabel() }}
                            @endif
                        @else
                            {{ __('student.rubric.empty_title') }}
                        @endif
                    </p>
                </div>
                <a href="{{ \App\Filament\App\Pages\AcademicRubric::getUrl() }}" class="sipma-link sipma-portal-more">
                    {{ __('student.rubric.open') }}
                    <x-filament::icon icon="lucide-chevron-right" class="sipma-portal-more-icon" />
                </a>
            </div>
        </section>
    @endif

    {{-- Kelengkapan (draf / perlu revisi): komponen yang sama dengan halaman Pendaftaran saya --}}
    @if ($checklist->isNotEmpty())
        <x-sipma.application-checklist :checklist="$checklist" />
    @endif

    {{-- Program yang sedang dibuka (sebelum mendaftar): tiga kartu, sisanya di menu Program --}}
    @if ($stage === StudentHome::START)
        <section class="sipma-card sipma-portal-section">
            <header class="sipma-portal-section-head">
                <div>
                    <h2 class="sipma-card-title">{{ __('student.programs.open_title') }}</h2>
                    <p class="sipma-card-desc">{{ __('student.programs.description') }}</p>
                </div>
                <a href="{{ Programs::getUrl() }}" class="sipma-link sipma-portal-more">
                    {{ __('admin.dashboard.see_all') }}
                    <x-filament::icon icon="lucide-chevron-right" class="sipma-portal-more-icon" />
                </a>
            </header>
            @if ($openPrograms->isEmpty())
                <x-sipma.empty icon="lucide-graduation-cap" :title="__('student.programs.empty')" :description="__('student.programs.empty_body')" />
            @else
                <div class="sipma-portal-programs">
                    @foreach ($openPrograms as $program)
                        <article class="sipma-portal-program">
                            <div class="sipma-portal-program-top">
                                <span class="sipma-portal-program-icon"><x-filament::icon icon="lucide-graduation-cap" class="sipma-tile-icon" /></span>
                                <span class="sipma-pill sipma-tone-success">{{ __('student.programs.open_badge') }}</span>
                            </div>
                            <div class="sipma-portal-program-body">
                                <p class="sipma-portal-program-faculty">{{ $program->faculty?->name }}</p>
                                <h3 class="sipma-portal-program-name">{{ $program->name }}</h3>
                                @if (filled($program->description))
                                    <p class="sipma-portal-program-desc">{{ $program->description }}</p>
                                @endif
                            </div>
                            <a href="{{ StartApplication::getUrl(['program' => $program->getKey()]) }}" class="sipma-portal-choose">
                                {{ __('student.programs.choose') }}
                                <x-filament::icon icon="lucide-arrow-right" class="sipma-portal-btn-icon" />
                            </a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-filament-panels::page>

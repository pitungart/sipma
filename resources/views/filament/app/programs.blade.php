{{-- UC-04: katalog program yang sedang dibuka (tanpa biaya, permintaan staf KUI). Layout referensi:
     grid kartu 4 kolom yang bisa dicari; kartu sama dengan kartu program di dasbor. --}}
@php
    use Illuminate\Support\Js;
@endphp

<x-filament-panels::page>
    @if ($programs->isEmpty())
        <section class="sipma-card">
            <x-sipma.empty icon="lucide-graduation-cap" :title="__('student.programs.empty')" :description="__('student.programs.empty_body')" />
        </section>
    @else
        {{-- Pencarian di browser: nama + fakultas, huruf kecil --}}
        <div
            x-data="{
                q: '',
                items: {{ Js::from($programs->map(fn ($program): string => mb_strtolower($program->name.' '.$program->faculty?->name))->values()) }},
                get none() { return this.q !== '' && ! this.items.some((item) => item.includes(this.q)) },
            }"
            x-on:sipma-program-search.window="q = $event.detail.trim().toLowerCase()"
        >
            <div class="sipma-portal-programs is-catalog">
                @foreach ($programs as $program)
                    @php
                        $chosen = $application?->program_id === $program->getKey();
                    @endphp
                    <article
                        x-show="! q || items[{{ $loop->index }}].includes(q)"
                        @class(['sipma-portal-program', 'is-chosen' => $chosen])
                    >
                        <div class="sipma-portal-program-top">
                            <span class="sipma-portal-program-icon"><x-filament::icon icon="lucide-graduation-cap" class="sipma-tile-icon" /></span>
                            @if ($chosen)
                                <span class="sipma-pill sipma-tone-success">{{ __('student.programs.chosen') }}</span>
                            @endif
                        </div>
                        <div class="sipma-portal-program-body">
                            <p class="sipma-portal-program-faculty">{{ $program->faculty?->name }}</p>
                            <h2 class="sipma-portal-program-name">{{ $program->name }}</h2>
                            @if (filled($program->description))
                                <p class="sipma-portal-program-desc">{{ $program->description }}</p>
                            @endif
                        </div>

                        {{-- Belum mendaftar → mulai pendaftaran; draf/revisi → ganti program (modal konfirmasi) --}}
                        @if ($canStart)
                            <a href="{{ \App\Filament\App\Pages\StartApplication::getUrl(['program' => $program->getKey()]) }}" class="sipma-portal-choose">
                                {{ __('student.programs.choose') }}
                                <x-filament::icon icon="lucide-arrow-right" class="sipma-portal-btn-icon" />
                            </a>
                        @elseif ($canChange && ! $chosen)
                            <button type="button" class="sipma-portal-choose" wire:click="mountAction('choose', {{ Js::from(['program' => $program->getKey()]) }})">
                                {{ __('student.programs.choose') }}
                                <x-filament::icon icon="lucide-arrow-right" class="sipma-portal-btn-icon" />
                            </button>
                        @endif
                    </article>
                @endforeach
            </div>

            <section class="sipma-card" x-cloak x-show="none">
                <x-sipma.empty icon="lucide-search-x" :title="__('student.programs.no_match')" />
            </section>
        </div>
    @endif
</x-filament-panels::page>

{{-- UC-04: katalog program yang sedang dibuka (tanpa biaya, permintaan staf KUI) --}}
<x-filament-panels::page>
    @if ($programs->isEmpty())
        <section class="sipma-card">
            <x-sipma.empty icon="lucide-graduation-cap" :title="__('student.programs.empty')" :description="__('student.programs.empty_body')" />
        </section>
    @else
        <div class="sipma-program-grid">
            @foreach ($programs as $item)
                @php
                    $program = $item['program'];
                    $period = $item['period'];
                    $chosen = $application?->program_id === $program->getKey();
                @endphp
                <article @class(['sipma-card sipma-program-card', 'is-chosen' => $chosen])>
                    <div class="sipma-program-head">
                        <span class="sipma-tile sipma-tone-primary"><x-filament::icon icon="lucide-graduation-cap" class="sipma-tile-icon" /></span>
                        @if ($chosen)
                            <span class="sipma-pill sipma-tone-success">{{ __('student.programs.chosen') }}</span>
                        @endif
                    </div>
                    <h2 class="sipma-program-name">{{ $program->name }}</h2>
                    <p class="sipma-program-faculty">{{ $program->faculty?->name }}</p>
                    @if (filled($program->description))
                        <p class="sipma-program-desc">{{ $program->description }}</p>
                    @endif
                    <div class="sipma-program-foot">
                        @if ($period)
                            <span class="sipma-program-period">
                                <x-filament::icon icon="lucide-calendar" class="sipma-todo-icon" />
                                {{ __('student.programs.period', ['period' => $period->name, 'date' => $period->registration_closes_at->translatedFormat('j M Y')]) }}
                            </span>
                        @endif
                        @if ($canStart)
                            <x-filament::button tag="a" :href="\App\Filament\App\Pages\StartApplication::getUrl(['program' => $program->getKey()])" size="sm">
                                {{ __('student.programs.choose') }}
                            </x-filament::button>
                        @elseif ($canChange && ! $chosen)
                            {{ ($this->chooseAction)(['program' => $program->getKey()]) }}
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>

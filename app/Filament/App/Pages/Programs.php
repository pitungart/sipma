<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Concerns\InteractsWithApplication;
use App\Models\AcademicPeriod;
use App\Models\Program;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * UC-04 Pilih program: katalog program yang periodenya sedang dibuka. Biaya tidak ditampilkan
 * (permintaan staf KUI, 8 Oktober 2026). "Pilih program ini" memulai pendaftaran, atau
 * mengganti program pendaftaran yang masih draf / perlu revisi.
 */
class Programs extends Page
{
    use InteractsWithApplication;

    protected static ?string $navigationIcon = 'lucide-graduation-cap';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'programs';

    protected static string $view = 'filament.app.programs';

    public static function getNavigationLabel(): string
    {
        return __('student.programs.title');
    }

    public function getTitle(): string
    {
        return __('student.programs.title');
    }

    public function getSubheading(): ?string
    {
        return __('student.programs.description');
    }

    /**
     * Ganti program pendaftaran sendiri (hanya selama bisa diubah).
     */
    public function chooseAction(): Action
    {
        return Action::make('choose')
            ->label(__('student.programs.choose'))
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('student.programs.change_heading', ['program' => $this->program($arguments)->name]))
            ->modalDescription(__('student.programs.change_description'))
            ->modalIcon('lucide-graduation-cap')
            ->action(function (array $arguments): void {
                $application = static::currentApplication();
                abort_if($application === null, 404);
                Gate::authorize('update', $application);

                $program = $this->program($arguments);
                $application->update([
                    'program_id' => $program->getKey(),
                    'academic_period_id' => AcademicPeriod::currentFor($program->getKey())?->getKey(),
                ]);

                Notification::make()->success()->title(__('student.programs.changed', ['program' => $program->name]))->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $application = static::currentApplication();

        return [
            'application' => $application,
            'canStart' => StartApplication::canAccess(),
            'canChange' => $application !== null && Gate::allows('update', $application),
            'programs' => $this->programs(),
        ];
    }

    /**
     * @return Collection<int, array{program: Program, period: ?AcademicPeriod}>
     */
    private function programs(): Collection
    {
        return Program::query()->openForRegistration()->with('faculty')->orderBy('name')->get()
            ->map(fn (Program $program): array => [
                'program' => $program,
                'period' => AcademicPeriod::currentFor($program->getKey()),
            ]);
    }

    /**
     * Hanya program yang sedang dibuka yang bisa dipilih, walau ID dikirim langsung.
     */
    private function program(array $arguments): Program
    {
        $program = Program::query()->openForRegistration()->find($arguments['program'] ?? null);
        abort_if($program === null, 404);

        return $program;
    }
}

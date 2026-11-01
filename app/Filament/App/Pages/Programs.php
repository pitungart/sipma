<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Concerns\InteractsWithApplication;
use App\Models\Program;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * UC-04 Pilih program: katalog program yang sedang dibuka (aktif). Biaya tidak ditampilkan
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

    /** @var Collection<int, Program>|null dipakai kepala & isi dalam satu permintaan */
    private ?Collection $openPrograms = null;

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
     * Kepala seperti referensi katalog: judul + deskripsi di kiri, pencarian di kanan.
     */
    public function getHeader(): ?View
    {
        return view('filament.app.programs-header', [
            'heading' => $this->getTitle(),
            'subheading' => $this->getSubheading(),
            'searchable' => $this->programs()->isNotEmpty(),
        ]);
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
                $application->update(['program_id' => $program->getKey()]);

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
     * @return Collection<int, Program>
     */
    private function programs(): Collection
    {
        return $this->openPrograms ??= Program::query()->openForRegistration()->with('faculty')->orderBy('name')->get();
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

<?php

namespace App\Filament\App\Pages;

use App\Enums\DocumentType;
use App\Filament\App\Concerns\InteractsWithApplication;
use App\Models\Program;
use App\Support\StudentHome;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;

/**
 * Dasbor mahasiswa mandiri (/app) dengan kerangka yang sama dengan panel lain: garis waktu
 * langkah (UC-01), kartu "langkah berikutnya", kelengkapan, dan program pilihan. Sebelum
 * mendaftar: ajakan mulai + yang perlu disiapkan + program yang sedang dibuka.
 */
class Dashboard extends BaseDashboard
{
    use InteractsWithApplication;

    protected static ?string $navigationIcon = 'lucide-house';

    protected static string $view = 'filament.app.dashboard';

    public function getHeader(): ?View
    {
        $home = new StudentHome(static::currentApplication());

        return view('filament.admin.dashboard-header', [
            'date' => now()->translatedFormat('l, j F Y'),
            'greeting' => __('student.home.greeting', ['name' => Filament::getUserName(Filament::auth()->user())]),
            'subheading' => __($home->stage() === StudentHome::START ? 'student.home.subheading_new' : 'student.home.subheading'),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label(__('student.application.actions.start'))
                ->icon('lucide-plus')
                ->visible(fn (): bool => StartApplication::canAccess())
                ->url(fn (): string => StartApplication::getUrl()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $application = static::currentApplication();
        $home = new StudentHome($application);
        $stage = $home->stage();

        return [
            'application' => $application,
            'stage' => $stage,
            'next' => $home->next(),
            'cta' => match ($stage) {
                StudentHome::START => ['label' => __('student.application.actions.start'), 'url' => StartApplication::getUrl()],
                StudentHome::LOA => ['label' => __('agent.students.payments.download_loa'), 'url' => route('files.loa', $application->loa)],
                StudentHome::WAITING, StudentHome::PAYMENT_REVIEW => ['label' => __('student.home.view_application'), 'url' => MyApplication::getUrl()],
                default => ['label' => __("student.home.stages.{$stage}.cta"), 'url' => MyApplication::getUrl()],
            },
            'checklist' => $application?->status->isEditable() ? $home->checklist()->items() : collect(),
            'requiredDocuments' => DocumentType::required(),
            'openPrograms' => $stage === StudentHome::START
                ? Program::query()->openForRegistration()->with('faculty')->orderBy('name')->get()
                : collect(),
        ];
    }
}

<?php

namespace App\Filament\App\Pages;

use App\Enums\StudentStatus;
use App\Filament\App\Concerns\InteractsWithApplication;
use App\Filament\Concerns\ManagesOwnApplication;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * "Pendaftaran saya": detail pendaftaran mahasiswa mandiri — isi & aturan sama dengan detail
 * mahasiswa di panel agen (ManagesOwnApplication, partial filament.applicant.detail).
 * Belum punya pendaftaran → diarahkan ke Mulai pendaftaran.
 */
class MyApplication extends Page
{
    use InteractsWithApplication;
    use ManagesOwnApplication;

    protected static ?string $navigationIcon = 'lucide-file-text';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'application';

    protected static string $view = 'filament.app.application';

    public ?Student $application = null;

    public static function getNavigationLabel(): string
    {
        return __('student.application.title');
    }

    /**
     * Tanda "perlu tindakan": merah saat diminta revisi.
     */
    public static function getNavigationBadge(): ?string
    {
        return static::currentApplication()?->status === StudentStatus::Revision ? '!' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        $this->application = static::currentApplication();

        if ($this->application === null) {
            $this->redirect(StartApplication::getUrl(), navigate: false);

            return;
        }

        Gate::authorize('view', $this->application);
    }

    protected function applicant(): Student
    {
        return $this->application;
    }

    protected function consentLabel(): string
    {
        return __('student.application.consent');
    }

    public function getTitle(): string
    {
        return __('student.application.title');
    }

    /**
     * Program dengan ikon, lalu nomor pendaftaran bila sudah ada.
     */
    public function getSubheading(): string|Htmlable|null
    {
        if ($this->application === null) {
            return null;
        }

        return new HtmlString(Blade::render(
            '<span class="sipma-heading-sub"><x-filament::icon icon="lucide-graduation-cap" class="sipma-heading-sub-icon" />{{ $program }}@if ($number)<span class="sipma-heading-sub-sep">·</span><span class="sipma-mono">{{ $number }}</span>@endif</span>',
            ['program' => $this->application->program?->name ?? '—', 'number' => $this->application->registration_number],
        ));
    }

    protected function getHeaderActions(): array
    {
        if ($this->application === null) {
            return [];
        }

        return [
            Action::make('edit')
                ->label(__('admin.applicant.actions.edit'))
                ->icon('lucide-pencil')
                ->color('gray')
                ->visible(fn (): bool => $this->canChange())
                ->url(fn (): string => EditApplication::getUrl()),

            $this->downloadLoaAction(),
            $this->submitAction(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->application ? $this->applicationViewData() : [];
    }
}

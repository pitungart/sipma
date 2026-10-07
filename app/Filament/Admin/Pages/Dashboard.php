<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\StudentResource;
use App\Models\Student;
use App\Support\ApplicantExport;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard /admin mengikuti template: tanggal + sapaan di atas, lalu grid enam kolom
 * (kartu angka, tren 4/6 + program 2/6, tabel 4/6 + agenda 2/6, aktivitas 3/6 + sumber 3/6).
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'lucide-house';

    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'lg' => 6];
    }

    public function getTitle(): string
    {
        return __('admin.dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.dashboard.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.main');
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.dashboard-header', [
            'date' => now()->translatedFormat('l, j F Y'),
            'greeting' => __('admin.dashboard.greeting', ['name' => Filament::getUserName(Filament::auth()->user())]),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('admin.dashboard.export'))
                ->icon('lucide-download')
                ->color('gray')
                ->action(fn (): StreamedResponse => ApplicantExport::download(
                    Student::query()->visibleTo(Filament::auth()->user()),
                )),

            // Pendaftaran atas nama pendaftar: hanya Super Admin (keputusan #6)
            Action::make('newApplication')
                ->label(__('admin.applicant.actions.new'))
                ->icon('lucide-plus')
                ->visible(fn (): bool => StudentResource::canCreate())
                ->url(fn (): string => StudentResource::getUrl('create')),
        ];
    }
}

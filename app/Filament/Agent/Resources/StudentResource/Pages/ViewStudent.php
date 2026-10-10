<?php

namespace App\Filament\Agent\Resources\StudentResource\Pages;

use App\Filament\Agent\Resources\StudentResource;
use App\Filament\Concerns\ManagesOwnApplication;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Detail mahasiswa agen: status + garis waktu (UC-01), kelengkapan & pengajuan (UC-11),
 * tab Profil · Dokumen · Pembayaran & LOA (UC-08, UC-03, UC-10, UC-02) — logikanya bersama
 * panel /app (ManagesOwnApplication). Record selalu dicari lewat getEloquentQuery agen,
 * jadi mahasiswa agen lain tidak terjangkau.
 *
 * @property Student $record
 */
class ViewStudent extends ViewRecord
{
    use ManagesOwnApplication;

    protected static string $resource = StudentResource::class;

    protected static string $view = 'filament.agent.students.view';

    protected function applicant(): Student
    {
        return $this->record;
    }

    protected function consentLabel(): string
    {
        return __('agent.students.consent');
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name;
    }

    public function getSubheading(): ?string
    {
        return collect([$this->record->registration_number, $this->record->passport_number, $this->record->program?->name])
            ->filter()
            ->implode(' · ');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            $this->record->full_name,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('agent.students.actions.delete'))
                ->icon('lucide-trash-2')
                ->color('gray')
                ->modalHeading(fn (): string => __('agent.students.delete_heading', ['name' => $this->record->full_name]))
                ->modalDescription(__('agent.students.delete_description'))
                ->successNotificationTitle(__('agent.students.done.deleted'))
                ->successRedirectUrl(StudentResource::getUrl()),

            Action::make('edit')
                ->label(__('admin.applicant.actions.edit'))
                ->icon('lucide-pencil')
                ->color('gray')
                ->visible(fn (): bool => StudentResource::canEdit($this->record))
                ->url(fn (): string => StudentResource::getUrl('edit', ['record' => $this->record])),

            $this->downloadLoaAction(),
            $this->submitAction(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->applicationViewData();
    }
}

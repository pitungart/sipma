<?php

namespace App\Filament\Agent\Resources\StudentResource\Pages;

use App\Enums\StudentStatus;
use App\Filament\Agent\Resources\StudentResource;
use App\Filament\Support\ApplicantForm;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * UC-07 per mahasiswa: wizard empat langkah yang bisa dilompati. Draf cukup berisi nama, email,
 * nomor paspor, dan program (keputusan 8 Oktober 2026); dokumen & pengajuan di halaman detail.
 */
class CreateStudent extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = StudentResource::class;

    public function getTitle(): string
    {
        return __('agent.students.create_title');
    }

    public function getSubheading(): ?string
    {
        return __('agent.students.create_description');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            __('agent.students.create_title'),
        ];
    }

    protected function getSteps(): array
    {
        return ApplicantForm::steps(StudentResource::programFields(), __('agent.students.program_step_hint'));
    }

    public function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(__('admin.applicant.actions.save_draft'));
    }

    /**
     * Pemilik = agen yang masuk; tidak pernah diambil dari isian form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...StudentResource::withCurrentPeriod($data),
            'agent_id' => Filament::auth()->user()->agent->getKey(),
            'user_id' => null,
            'status' => StudentStatus::Draft,
        ];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('agent.students.done.created'))
            ->body(__('agent.students.done.created_body'));
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('view', ['record' => $this->record]);
    }
}

<?php

namespace App\Filament\Admin\Resources\StudentResource\Pages;

use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Pendaftaran atas nama pendaftar oleh Super Admin (keputusan #6, 7 Oktober 2026): wizard empat
 * langkah seperti "Pendaftaran baru" di template. Hasilnya draf tanpa akun pemilik; dokumen
 * diunggah dan pendaftaran diajukan dari halaman detail.
 */
class CreateApplicant extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = StudentResource::class;

    public function getTitle(): string
    {
        return __('admin.applicant.create_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.applicant.create_description');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('admin.groups.admissions'),
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            __('admin.applicant.create_title'),
        ];
    }

    protected function getSteps(): array
    {
        return [
            Step::make('identity')->label(__('admin.applicant.steps.identity'))->description(__('admin.applicant.steps.identity_hint'))->icon('lucide-user')->columns(2)->schema(StudentResource::identityFields()),
            Step::make('contact')->label(__('admin.applicant.steps.contact'))->description(__('admin.applicant.steps.contact_hint'))->icon('lucide-map-pin')->columns(2)->schema(StudentResource::contactFields()),
            Step::make('passport')->label(__('admin.applicant.steps.passport'))->description(__('admin.applicant.steps.passport_hint'))->icon('lucide-book-user')->columns(2)->schema(StudentResource::passportFields()),
            Step::make('program')->label(__('admin.applicant.steps.program'))->description(__('admin.applicant.steps.program_hint'))->icon('lucide-graduation-cap')->columns(2)->schema(StudentResource::programFields()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => StudentStatus::Draft];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('admin.applicant.done.created'))
            ->body(__('admin.applicant.done.created_body'));
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('view', ['record' => $this->record]);
    }
}

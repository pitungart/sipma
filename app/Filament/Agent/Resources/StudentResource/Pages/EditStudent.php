<?php

namespace App\Filament\Agent\Resources\StudentResource\Pages;

use App\Filament\Agent\Resources\StudentResource;
use App\Filament\Concerns\JumpsToInvalidWizardStep;
use App\Filament\Support\ApplicantForm;
use App\Models\Student;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ubah data mahasiswa selama draf atau perlu revisi (StudentResource::canEdit → StudentPolicy::update).
 * Wizard empat langkah yang sama dengan saat membuat (keputusan 26 Oktober 2026) agar terasa familier;
 * semua langkah bisa dilompati dan Simpan/Batal tetap di bawah wizard sehingga bisa disimpan dari langkah mana pun.
 *
 * @property Student $record
 */
class EditStudent extends EditRecord
{
    use JumpsToInvalidWizardStep;

    protected static string $resource = StudentResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.applicant.edit_title', ['name' => $this->record->full_name]);
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            StudentResource::getUrl() => StudentResource::getPluralModelLabel(),
            StudentResource::getUrl('view', ['record' => $this->record]) => $this->record->full_name,
            __('admin.applicant.actions.edit'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make(ApplicantForm::steps(StudentResource::programFields(), __('agent.students.program_step_hint')))
                    ->skippable(),
            ])
            ->columns(null);
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('view', ['record' => $this->record]);
    }
}

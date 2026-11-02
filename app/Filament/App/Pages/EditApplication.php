<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Concerns\InteractsWithApplication;
use App\Filament\Concerns\JumpsToInvalidWizardStep;
use App\Filament\Support\ApplicantForm;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/**
 * Ubah data pendaftaran sendiri selama draf / perlu revisi (StudentPolicy::update).
 * Wizard empat langkah yang sama dengan Mulai pendaftaran (keputusan 26 Oktober 2026) agar terasa familier;
 * semua langkah bisa dilompati dan Simpan/Batal tetap di bawah wizard sehingga bisa disimpan dari langkah mana pun.
 *
 * @property Form $form
 */
class EditApplication extends Page implements HasForms
{
    use InteractsWithApplication;
    use InteractsWithForms, JumpsToInvalidWizardStep {
        JumpsToInvalidWizardStep::onValidationError insteadof InteractsWithForms;
    }

    protected static ?string $slug = 'application/edit';

    protected static string $view = 'filament.app.edit';

    protected static bool $shouldRegisterNavigation = false;

    public ?Student $application = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('admin.applicant.actions.edit');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [MyApplication::getUrl() => __('student.application.title'), __('admin.applicant.actions.edit')];
    }

    public function mount(): void
    {
        $this->application = static::currentApplication();
        abort_if($this->application === null, 404);
        Gate::authorize('update', $this->application);

        $this->form->fill($this->application->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Wizard::make(ApplicantForm::steps([
                    ApplicantForm::programSelect(fn (): array => static::programOptions($this->application))
                        ->helperText(__('student.application.program_hint'))
                        ->columnSpanFull(),
                    ApplicantForm::programRubric(),
                ], __('student.application.program_step_hint')))
                    ->skippable(),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label(__('admin.master.save'))->submit('save')->keyBindings(['mod+s']),
            Action::make('cancel')->label(__('admin.master.cancel'))->color('gray')->url(MyApplication::getUrl()),
        ];
    }

    public function save(): void
    {
        Gate::authorize('update', $this->application);

        $this->application->update($this->form->getState());

        Notification::make()->success()->title(__('student.application.saved'))->send();

        $this->redirect(MyApplication::getUrl(), navigate: false);
    }
}

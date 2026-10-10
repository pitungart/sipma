<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Concerns\InteractsWithApplication;
use App\Filament\Support\ApplicantForm;
use App\Models\AcademicPeriod;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/**
 * Ubah data pendaftaran sendiri selama draf / perlu revisi (StudentPolicy::update).
 *
 * @property Form $form
 */
class EditApplication extends Page implements HasForms
{
    use InteractsWithApplication;
    use InteractsWithForms;

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
                Section::make(__('admin.applicant.steps.identity'))->columns(2)->schema(ApplicantForm::identityFields()),
                Section::make(__('admin.applicant.steps.contact'))->columns(2)->schema(ApplicantForm::contactFields()),
                Section::make(__('admin.applicant.steps.passport'))->columns(2)->schema(ApplicantForm::passportFields()),
                Section::make(__('admin.applicant.steps.program'))->schema([
                    ApplicantForm::programSelect(fn (): array => static::programOptions($this->application))
                        ->helperText(__('student.application.program_hint')),
                ]),
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

        $data = $this->form->getState();

        if ($data['program_id'] !== $this->application->program_id || $this->application->academic_period_id === null) {
            $data['academic_period_id'] = AcademicPeriod::currentFor($data['program_id'])?->getKey();
        }

        $this->application->update($data);

        Notification::make()->success()->title(__('student.application.saved'))->send();

        $this->redirect(MyApplication::getUrl(), navigate: false);
    }
}

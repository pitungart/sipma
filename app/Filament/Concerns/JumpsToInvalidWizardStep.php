<?php

namespace App\Filament\Concerns;

use App\Filament\Support\ApplicantForm;
use Illuminate\Validation\ValidationException;

/**
 * Form pendaftar berbentuk wizard yang bisa dilompati: saat Simpan gagal karena isian wajib di
 * langkah lain, wizard dipindah ke langkah pertama yang bergalat agar pesannya terlihat
 * (Filament tidak melakukannya sendiri — galat di langkah tersembunyi tidak tampak).
 */
trait JumpsToInvalidWizardStep
{
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $index = ApplicantForm::firstStepWithError(array_keys($exception->errors()));

        $this->js(<<<JS
            (() => {
                const el = document.querySelector('.fi-fo-wizard')
                if (! el) return
                const wizard = Alpine.\$data(el)
                wizard.step = wizard.getSteps()[{$index}] ?? wizard.step
                wizard.scroll()
            })()
            JS);
    }
}

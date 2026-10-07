<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Workflow\WorkflowException;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;

/**
 * Pembantu aksi tabel yang menjalankan StudentWorkflow / MouWorkflow: kesalahan alur tampil
 * sebagai notifikasi merah, keberhasilan sebagai notifikasi hijau, dan pratinjau berkas privat
 * dibuka di modal.
 */
trait RunsWorkflow
{
    protected static function runStep(Closure $step, string $successTitle): void
    {
        try {
            app()->call($step);
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    /**
     * @param  Closure(mixed): array{url: string, image: bool, title: string}  $file
     */
    protected static function previewTableAction(Closure $file): Action
    {
        return Action::make('preview')
            ->iconButton()
            ->icon('lucide-eye')
            ->color('gray')
            ->tooltip(__('admin.applicant.actions.preview'))
            ->modalHeading(fn ($record): string => $file($record)['title'])
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalContent(fn ($record) => view('filament.admin.applicants.preview', $file($record)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.applicant.actions.close'));
    }
}

<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\MaxWidth;

/**
 * Tombol Pratinjau berkas privat dalam modal, dipakai detail Pendaftar dan Mahasiswa Asing.
 */
final class FilePreview
{
    /**
     * @param  Closure(array<string, mixed>): array{url: string, image: bool, title: string}  $file
     */
    public static function action(string $name, Closure $file): Action
    {
        return Action::make($name)
            ->label(__('admin.applicant.actions.preview'))
            ->icon('lucide-eye')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => $file($arguments)['title'])
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.admin.applicants.preview', $file($arguments)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.applicant.actions.close'));
    }
}

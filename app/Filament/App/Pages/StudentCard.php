<?php

namespace App\Filament\App\Pages;

use App\Enums\StudentCardStatus;
use App\Filament\App\Concerns\RequiresAcademicAccess;
use App\Models\StudentCard as StudentCardModel;
use Filament\Pages\Page;

/**
 * KTM mahasiswa di portal: pratinjau dan unduhan. KTM yang dicabut tidak bisa diunduh, hanya
 * keterangan dan alasannya yang tampil.
 */
class StudentCard extends Page
{
    use RequiresAcademicAccess;

    protected static ?string $navigationIcon = 'lucide-id-card';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'student-card';

    protected static string $view = 'filament.app.student-card';

    public static function getNavigationLabel(): string
    {
        return __('student.card.title');
    }

    public function getTitle(): string
    {
        return __('student.card.title');
    }

    public function getSubheading(): ?string
    {
        return __('student.card.description');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $card = static::currentApplication()?->currentCard();

        return [
            'card' => $card,
            'revoked' => $card?->status === StudentCardStatus::Revoked,
        ];
    }

    public static function fileUrl(StudentCardModel $card, bool $download = false): string
    {
        return route('files.student-card', ['card' => $card, 'download' => $download ? 1 : null]);
    }
}

<?php

namespace App\Filament\Support;

use App\Enums\DocumentType;
use App\Support\FileTemplates;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Unduh template dokumen (Fase 3, keputusan 26 Oktober 2026): Mahasiswa, Agen, dan Admin
 * Fakultas hanya bisa mengunduh; template dikelola Super Admin di Sistem → Unggahan berkas.
 * Tombol dirender di Blade hanya untuk jenis dokumen yang punya template (FileTemplates::uploaded()).
 */
final class TemplateDownload
{
    /**
     * Argumen: ['type' => nilai DocumentType]. Tautan biasa, tidak membuka modal.
     */
    public static function action(): Action
    {
        return Action::make('downloadTemplate')
            ->label(__('workflow.documents.template'))
            ->tooltip(fn (array $arguments): string => __('workflow.documents.template_tooltip', ['name' => self::type($arguments)->getLabel()]))
            ->icon('lucide-file-down')
            ->color('gray')
            ->size('sm')
            ->url(fn (array $arguments): string => FileTemplates::url(self::type($arguments)->value));
    }

    /**
     * Keterangan modal unggah: ketentuan format, ditambah tautan template bila ada.
     */
    public static function uploadHint(DocumentType $type): string|Htmlable
    {
        if (! FileTemplates::has($type->value)) {
            return $type->hint();
        }

        return new HtmlString(e($type->hint()).' <a href="'.e(FileTemplates::url($type->value)).'" class="sipma-link">'.e(__('workflow.documents.template_link')).'</a>');
    }

    private static function type(array $arguments): DocumentType
    {
        return DocumentType::from($arguments['type'] ?? '');
    }
}

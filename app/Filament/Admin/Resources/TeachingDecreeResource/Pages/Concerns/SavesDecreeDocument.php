<?php

namespace App\Filament\Admin\Resources\TeachingDecreeResource\Pages\Concerns;

use App\Filament\Admin\Resources\TeachingDecreeResource;
use App\Models\TeachingDecree;
use App\Support\PrivateFiles;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Form SK: PDF tidak ikut data model, disimpan ke disk privat setelah SK punya ID. Berkas lama dihapus
 * saat diganti.
 *
 * @property TeachingDecree $record
 */
trait SavesDecreeDocument
{
    private ?TemporaryUploadedFile $uploadedDocument = null;

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('admin.groups.academic'),
            TeachingDecreeResource::getUrl() => TeachingDecreeResource::getPluralModelLabel(),
            $this->getTitle(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TeachingDecreeResource::getUrl();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pullDocument(array $data): array
    {
        $file = $data['document_file'] ?? null;
        $this->uploadedDocument = $file instanceof TemporaryUploadedFile ? $file : null;
        unset($data['document_file']);

        return $data;
    }

    private function storeDocument(TeachingDecree $decree): void
    {
        if ($this->uploadedDocument === null) {
            return;
        }

        $old = $decree->document;

        $decree->update(['document' => PrivateFiles::store($this->uploadedDocument, "teaching-decrees/{$decree->getKey()}")['file_path']]);

        if ($old !== $decree->document) {
            PrivateFiles::delete($old);
        }
    }
}

<?php

namespace App\Filament\Support;

use Filament\Support\Enums\Alignment;

/**
 * Modal berisi form mengikuti pola template yang sama dengan master data: kepala bergaris
 * dengan tile ikon, kaki abu dengan Batal / Simpan rata kanan. Modal konfirmasi (tanpa form)
 * tidak memakai ini — tetap di tengah dengan tombol selebar modal.
 */
final class FormModal
{
    /**
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function apply($action)
    {
        return $action
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalCancelActionLabel(__('admin.master.cancel'));
    }
}

<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;

/**
 * UC-08 Upload / UC-03 Revisi dokumen: agen & mahasiswa pemilik data.
 * UC-16/17 Verifikasi dokumen + catatan revisi: hanya Super Admin (via Gate::before).
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Agent, UserRole::Student);
    }

    public function view(User $user, Document $document): bool
    {
        return $document->student->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Agent, UserRole::Student);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->canModify($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->canModify($user, $document);
    }

    public function review(User $user, Document $document): bool
    {
        return false;
    }

    private function canModify(User $user, Document $document): bool
    {
        return $document->student->isOwnedBy($user)
            && $document->student->status->isEditable()
            && $document->status !== DocumentStatus::Approved;
    }
}

<?php

namespace App\Workflow;

use App\Enums\MouStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Mou;
use App\Models\User;
use App\Notifications\MouReviewed;
use App\Notifications\MouSubmitted;
use App\Support\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * UC-13 / UC-25: agen mengunggah MOU, KUI menyetujui atau menolak (loop revisi A5 → A4).
 * Setiap unggahan disimpan sebagai baris baru agar riwayat MOU tetap ada.
 */
final class MouWorkflow
{
    public const MAX_FILE_SIZE_KB = 5120;

    public function submit(Agent $agent, UploadedFile $file): Mou
    {
        if ($agent->hasApprovedMou()) {
            throw WorkflowException::because('mou_already_approved');
        }

        if ($agent->mous()->where('status', MouStatus::Pending)->exists()) {
            throw WorkflowException::because('mou_already_pending');
        }

        Validator::validate(['file' => $file], ['file' => ['file', 'mimes:pdf', 'max:'.self::MAX_FILE_SIZE_KB]]);

        $mou = $agent->mous()->create([
            'file_path' => PrivateFiles::store($file, PrivateFiles::agentDirectory($agent->getKey(), 'mou'))['file_path'],
            'status' => MouStatus::Pending,
        ]);

        Notification::send(
            User::query()->where('role', UserRole::SuperAdmin)->where('is_active', true)->get(),
            new MouSubmitted($mou),
        );

        return $mou;
    }

    public function approve(Mou $mou): Mou
    {
        return $this->decide($mou, MouStatus::Approved);
    }

    public function reject(Mou $mou, string $note): Mou
    {
        if (blank($note)) {
            throw WorkflowException::because('note_required');
        }

        return $this->decide($mou, MouStatus::Rejected, $note);
    }

    private function decide(Mou $mou, MouStatus $status, ?string $note = null): Mou
    {
        if ($mou->status !== MouStatus::Pending) {
            throw WorkflowException::because('mou_not_pending');
        }

        $mou->update([
            'status' => $status,
            'revision_note' => $note,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        $mou->agent->user?->notify(new MouReviewed($mou));

        return $mou;
    }
}

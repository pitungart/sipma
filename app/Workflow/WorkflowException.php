<?php

namespace App\Workflow;

use App\Enums\StudentStatus;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Langkah alur yang tidak sah pada keadaan sekarang. Pesannya sudah diterjemahkan dan aman
 * ditampilkan apa adanya ke pengguna (notifikasi Filament / pesan form).
 */
final class WorkflowException extends RuntimeException
{
    /**
     * @param  Collection<int, array{key: string, kind: string, label: string, done: bool}>  $missing
     */
    private function __construct(string $message, public readonly Collection $missing = new Collection)
    {
        parent::__construct($message);
    }

    public static function transition(StudentStatus $from, string $action): self
    {
        return new self(__('workflow.errors.transition', [
            'action' => __("workflow.actions.{$action}"),
            'status' => $from->getLabel(),
        ]));
    }

    /**
     * @param  Collection<int, array{key: string, kind: string, label: string, done: bool}>  $missing
     */
    public static function incomplete(Collection $missing): self
    {
        return new self(__('workflow.errors.incomplete', ['count' => $missing->count()]), $missing);
    }

    public static function because(string $key, array $replace = []): self
    {
        return new self(__("workflow.errors.{$key}", $replace));
    }
}

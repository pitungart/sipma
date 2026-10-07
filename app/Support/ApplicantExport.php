<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV pendaftar. Query yang diberikan sudah dibatasi pemanggilnya (visibleTo, tab,
 * saringan), jadi ekspor selalu sama dengan yang boleh dan sedang dilihat pengguna.
 */
final class ApplicantExport
{
    /**
     * @param  Builder<Student>  $query
     */
    public static function download(Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                __('admin.dashboard.applicant_name'),
                'Email',
                __('admin.applicant.fields.passport_number'),
                __('admin.dashboard.nationality'),
                __('admin.program.label'),
                __('admin.dashboard.source'),
                __('admin.dashboard.status'),
                __('admin.applicant.fields.submitted_at'),
                __('admin.dashboard.registered_at'),
            ]);

            (clone $query)
                ->with(['program:id,name', 'nationality', 'agent:id,company_name'])
                ->reorder()
                ->latest()
                ->lazy()
                ->each(fn (Student $student) => fputcsv($out, [
                    $student->full_name,
                    $student->email,
                    $student->passport_number,
                    $student->nationality?->name,
                    $student->program?->name,
                    $student->agent?->company_name ?? __('admin.dashboard.source_self'),
                    $student->status->getLabel(),
                    $student->submitted_at?->toDateTimeString(),
                    $student->created_at?->toDateTimeString(),
                ]));

            fclose($out);
        }, 'sipma-pendaftar-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}

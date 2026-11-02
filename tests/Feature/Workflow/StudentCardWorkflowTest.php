<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\NumberType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Models\Program;
use App\Models\Student;
use App\Notifications\StudentCardIssued;
use App\Notifications\StudentCardRevoked;
use App\Support\Numbering;
use App\Workflow\StudentCardWorkflow;
use App\Workflow\WorkflowException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * TP-2 F1: aturan penerbitan KTM di StudentCardWorkflow.
 */
class StudentCardWorkflowTest extends WorkflowTestCase
{
    private function workflow(): StudentCardWorkflow
    {
        return app(StudentCardWorkflow::class);
    }

    private function enrolled(?Program $program = null, DocumentStatus $photo = DocumentStatus::Approved): Student
    {
        $student = $this->student($program, StudentStatus::Enrolled);

        $student->documents()->create([
            'type' => DocumentType::Photo,
            'file_path' => 'photo.png',
            'original_name' => 'photo.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
            'status' => $photo,
        ]);

        return $student;
    }

    public function test_issue_uses_program_duration_and_number_format(): void
    {
        $program = $this->program();
        $program->update(['duration_months' => 6]);
        $student = $this->enrolled($program);
        $student->update(['enrollment_month' => 11, 'enrollment_year' => now()->year + 1]);

        $card = $this->workflow()->issue($student);

        $this->assertSame((now()->year + 2).'-04-30', $card->valid_until->toDateString());
        $this->assertSame(StudentCardStatus::Active, $card->status);
        $this->assertSame('KTMA-'.now()->year.'-0001', $card->card_number);
        $this->assertTrue($card->issued_at->isToday());
        $this->assertTrue($student->activeCard->is($card));

        Notification::assertSentTo($student->user, StudentCardIssued::class);
    }

    public function test_default_validity_is_the_last_day_of_the_final_month(): void
    {
        $program = $this->program();
        $program->update(['duration_months' => 2]);
        $student = $this->enrolled($program);
        $student->update(['enrollment_month' => 12, 'enrollment_year' => 2027]);

        $this->assertSame('2028-01-31', StudentCardWorkflow::defaultValidUntil($student->fresh())->toDateString());

        $program->update(['duration_months' => null]);

        $this->assertNull(StudentCardWorkflow::defaultValidUntil($student->fresh()));
    }

    public function test_validity_must_be_entered_when_it_cannot_be_calculated(): void
    {
        $student = $this->enrolled();

        $this->assertWorkflowFails('workflow.errors.card_valid_until_required', fn () => $this->workflow()->issue($student));

        $this->assertWorkflowFails('workflow.errors.card_valid_until_past', fn () => $this->workflow()->issue($student, today()));

        $card = $this->workflow()->issue($student, now()->addYear());

        $this->assertSame(now()->addYear()->toDateString(), $card->valid_until->toDateString());
    }

    public function test_only_enrolled_students_can_receive_a_card(): void
    {
        foreach ([StudentStatus::LoaIssued, StudentStatus::Completed] as $status) {
            $student = $this->enrolled();
            $student->update(['status' => $status]);

            try {
                $this->workflow()->issue($student, now()->addYear());
                $this->fail("{$status->value} should be rejected");
            } catch (WorkflowException) {
                $this->assertSame(0, $student->studentCards()->count());
            }
        }
    }

    public function test_status_is_read_fresh_inside_the_lock(): void
    {
        $student = $this->enrolled();
        $stale = Student::find($student->getKey());

        $student->update(['status' => StudentStatus::Completed]);

        $this->expectException(WorkflowException::class);

        $this->workflow()->issue($stale, now()->addYear());
    }

    public function test_second_active_card_is_rejected(): void
    {
        $student = $this->enrolled();

        $this->workflow()->issue($student, now()->addYear());

        $this->assertWorkflowFails('workflow.errors.card_already_active', fn () => $this->workflow()->issue($student, now()->addYear()), ['name' => $student->full_name]);
        $this->assertSame(1, $student->studentCards()->count());
    }

    public function test_photo_must_be_approved(): void
    {
        $student = $this->enrolled(photo: DocumentStatus::Pending);

        $this->assertWorkflowFails('workflow.errors.card_photo_missing', fn () => $this->workflow()->issue($student, now()->addYear()));

        $student->documents()->update(['status' => DocumentStatus::Approved]);

        $this->assertNotNull($this->workflow()->issue($student, now()->addYear()));
    }

    public function test_manual_number_must_be_unique_and_sequence_stays_free_of_it(): void
    {
        $this->workflow()->issue($this->enrolled(), now()->addYear(), 'KTMA-MANUAL-1');

        $other = $this->enrolled();

        $this->assertWorkflowFails('workflow.errors.card_number_taken', fn () => $this->workflow()->issue($other, now()->addYear(), 'KTMA-MANUAL-1'), ['number' => 'KTMA-MANUAL-1']);
        $this->assertSame(0, $other->studentCards()->count());

        $this->assertSame('KTMA-'.now()->year.'-0001', Numbering::preview(NumberType::StudentCard));
    }

    public function test_reissue_revokes_the_old_card_and_links_the_new_one(): void
    {
        $student = $this->enrolled();
        $validUntil = now()->addYear()->startOfDay();
        $old = $this->workflow()->issue($student, $validUntil);

        Notification::fake();

        $new = $this->workflow()->reissue($old, 'Kartu hilang');

        $old->refresh();
        $this->assertSame(StudentCardStatus::Revoked, $old->status);
        $this->assertSame('Kartu hilang', $old->revoke_reason);
        $this->assertNotNull($old->revoked_at);

        $this->assertTrue($new->isActive());
        $this->assertNotSame($old->card_number, $new->card_number);
        $this->assertTrue($new->replaces->is($old));
        $this->assertSame($validUntil->toDateString(), $new->valid_until->toDateString());
        $this->assertSame(1, $student->studentCards()->where('status', StudentCardStatus::Active)->count());

        Notification::assertSentTo($student->user, StudentCardIssued::class, fn (StudentCardIssued $n): bool => $n->reason === 'Kartu hilang');
    }

    public function test_failed_reissue_keeps_the_old_card_active(): void
    {
        $old = $this->workflow()->issue($student = $this->enrolled(), now()->addYear());
        $taken = $this->workflow()->issue($this->enrolled(), now()->addYear());

        try {
            $this->workflow()->reissue($old, 'Rusak', number: $taken->card_number);
            $this->fail('Duplicate number should be rejected');
        } catch (WorkflowException) {
            $this->assertTrue($old->refresh()->isActive());
            $this->assertSame(1, $student->studentCards()->count());
        }
    }

    public function test_revoke_notifies_the_student_and_cannot_run_twice(): void
    {
        $student = $this->enrolled();
        $card = $this->workflow()->issue($student, now()->addYear());

        $this->workflow()->revoke($card, 'Data keliru');

        $this->assertSame(StudentCardStatus::Revoked, $card->fresh()->status);
        $this->assertNull($student->activeCard()->first());
        Notification::assertSentTo($student->user, StudentCardRevoked::class);

        $this->assertWorkflowFails('workflow.errors.card_not_active', fn () => $this->workflow()->revoke($card, 'Lagi'), ['number' => $card->card_number]);
        $this->assertWorkflowFails('workflow.errors.card_not_active', fn () => $this->workflow()->reissue($card, 'Lagi'), ['number' => $card->card_number]);

        // Setelah dicabut boleh terbit lagi
        $this->assertTrue($this->workflow()->issue($student, now()->addYear())->isActive());
    }

    public function test_student_without_account_can_still_receive_a_card(): void
    {
        $student = $this->enrolled();
        $student->update(['user_id' => null, 'agent_id' => $this->agent()->id]);

        $card = $this->workflow()->issue($student->fresh(), Carbon::now()->addYear());

        $this->assertTrue($card->isActive());
        Notification::assertNothingSent();
    }

    private function assertWorkflowFails(string $key, callable $step, array $replace = []): void
    {
        try {
            $step();
            $this->fail("Expected {$key}");
        } catch (WorkflowException $e) {
            $this->assertSame(__($key, $replace), $e->getMessage());
        }
    }
}

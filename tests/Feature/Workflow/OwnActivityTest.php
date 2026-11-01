<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Support\ActivityDescriber;
use App\Workflow\StudentWorkflow;

class OwnActivityTest extends WorkflowTestCase
{
    public function test_own_activity_lists_only_the_users_actions_as_verb_first_sentences(): void
    {
        $admin = $this->superAdmin();
        $student = $this->student($this->program());
        $student->update(['full_name' => 'Emma Walker']);
        $workflow = app(StudentWorkflow::class);

        $document = $workflow->uploadDocument($student, DocumentType::Passport, $this->file(DocumentType::Passport));
        $student->update(['status' => StudentStatus::InReview]);

        $this->actingAs($admin);
        $workflow->reviewDocument($document, DocumentStatus::Revision, 'Halaman identitas buram');

        $texts = ActivityDescriber::forCauser($admin)->pluck('text');

        $this->assertCount(1, $texts);
        $this->assertSame(
            __('profile.activity.document_revision', ['document' => DocumentType::Passport->getLabel(), 'name' => 'Emma Walker']),
            $texts->first(),
        );
    }
}

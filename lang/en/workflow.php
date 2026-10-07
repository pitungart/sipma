<?php

// Teks alur pendaftaran (StudentWorkflow, MouWorkflow, checklist, notifikasi, garis waktu).
// Pembacanya calon mahasiswa asing: kalimat sederhana, bukan istilah birokrasi (R-2.12, R-2.13).
return [

    'documents' => [
        'format' => ':types · max :max KB',
        'photo_rules' => 'Formal 3×4 photo with a blue background.',
    ],

    'fields' => [
        'full_name' => 'Full name (as in passport)',
        'gender' => 'Gender',
        'place_of_birth' => 'Place of birth',
        'date_of_birth' => 'Date of birth',
        'nationality_code' => 'Nationality',
        'email' => 'Email',
        'phone_number' => 'Phone number',
        'permanent_address' => 'Permanent address',
        'home_university' => 'Home university',
        'home_university_country_code' => 'Country of home university',
        'passport_number' => 'Passport number',
        'date_of_issued_passport' => 'Passport issue date',
        'date_of_passport_expiry' => 'Passport expiry date',
        'program_id' => 'Program',
    ],

    'actions' => [
        'upload_document' => 'upload a document',
        'submit' => 'submit the application',
        'pay' => 'upload a payment proof',
        'start_review' => 'start the review',
        'review_document' => 'review a document',
        'request_revision' => 'ask for a revision',
        'approve' => 'approve the application',
        'issue_loa' => 'issue the LOA',
    ],

    'errors' => [
        'transition' => 'You cannot :action while the application is “:status”.',
        'incomplete' => 'Complete :count more item(s) before submitting.',
        'document_locked' => 'The :document has already been approved and cannot be replaced.',
        'payment_not_required' => 'The :type is not charged for this program.',
        'payment_already_verified' => 'The :type has already been verified.',
        'invalid_review' => 'Choose approve, revision, or reject for this document.',
        'note_required' => 'Write the reason so the applicant knows what to fix.',
        'revision_reason_required' => 'Mark at least one document for revision or write a general note.',
        'documents_not_approved' => 'Approve these documents first: :documents.',
        'payment_not_pending' => 'This payment has already been processed.',
        'payments_outstanding' => 'The LOA can be issued after these payments are verified: :types.',
        'mou_already_approved' => 'Your MOU has already been approved.',
        'mou_already_pending' => 'Your MOU is still waiting for review.',
        'mou_not_pending' => 'This MOU has already been processed.',
    ],

    'notifications' => [
        'open' => 'Open SIPMA',
        'greeting' => 'Hello :name,',
        'salutation' => 'International Office, Universitas Udayana',
        'submitted' => [
            'title' => 'New application',
            'body' => ':name applied for :program:again. It is waiting for review.',
            'again' => ' again after a revision',
        ],
        'revision' => [
            'title' => 'Your application needs changes',
            'body' => 'The application of :name needs some changes before we can continue. :note',
            'see_documents' => 'Open the documents to see what to fix.',
        ],
        'approved' => [
            'title' => 'Application approved',
            'body' => 'The application of :name has been approved. Please pay the fees and upload the payment proof.',
        ],
        'payment_submitted' => [
            'title' => 'New payment proof',
            'body' => ':name uploaded a proof for the :type.',
        ],
        'payment_rejected' => [
            'title' => 'Payment proof not accepted',
            'body' => 'The :type proof for :name was not accepted: :note',
        ],
        'loa_issued' => [
            'title' => 'Your Letter of Acceptance is ready',
            'body' => 'The Letter of Acceptance for :name is ready to download.',
        ],
        'mou_submitted' => [
            'title' => 'New MOU to review',
            'body' => ':agency uploaded an MOU.',
        ],
        'mou_approved' => [
            'title' => 'Your MOU has been approved',
            'body' => 'You can now register students in SIPMA.',
        ],
        'mou_rejected' => [
            'title' => 'Your MOU needs changes',
            'body' => 'Please upload a revised MOU. Reason: :note',
        ],
    ],

    'timeline_label' => 'Application progress',

    'timeline' => [
        'draft' => ['label' => 'Fill in the application', 'description' => 'Personal data and documents'],
        'submitted' => ['label' => 'Submitted', 'description' => 'Waiting for the International Office'],
        'review' => ['label' => 'Review', 'description' => 'Documents are being checked'],
        'revision' => ['label' => 'Changes needed', 'description' => 'Fix the marked documents and submit again'],
        'payment' => ['label' => 'Payment', 'description' => 'Pay the fees and upload the proof'],
        'loa' => ['label' => 'Letter of Acceptance', 'description' => 'Ready to download'],
    ],

];

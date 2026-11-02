<?php

// Teks alur pendaftaran (StudentWorkflow, MouWorkflow, checklist, notifikasi, garis waktu).
// Pembacanya calon mahasiswa asing: kalimat sederhana, bukan istilah birokrasi (R-2.12, R-2.13).
return [

    'documents' => [
        'format' => ':types · max :max',
        'photo_rules' => 'Formal 3×4 photo with a blue background.',
        'template' => 'Template',
        'template_tooltip' => 'Download the :name template',
        'template_link' => 'Download this document\'s template.',
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
        'enroll' => 'mark the student active',
        'complete' => 'mark the program completed',
        'reopen' => 'reopen the active status',
        'issue_card' => 'issue a student card',
        'manage_courses' => 'change the courses',
        'grade' => 'enter grades',
    ],

    'rubric' => [
        'title' => 'Academic rubric',
        'empty' => 'The academic rubric for this program is not available yet.',
        'code' => 'Code',
        'course' => 'Course',
        'semester' => 'Semester',
        'credits' => 'Credits',
        'total' => 'Total credits',
        'grade' => 'Grade',
        'completed' => 'Courses completed',
        'gpa' => 'GPA',
        'not_graded' => 'Not graded',
        'close' => 'Close',
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
        'fees_not_set' => 'Cannot approve yet: the :types of :program is still Rp 0. Set the fees in Master data → Programs.',
        'va_not_set' => 'Cannot approve yet: no VA account is set up for :types. Add one in Master data → Payment accounts.',
        'payment_not_pending' => 'This payment has already been processed.',
        'payments_outstanding' => 'The LOA can be issued after these payments are verified: :types.',
        'mou_already_approved' => 'Your MOU has already been approved.',
        'mou_already_pending' => 'Your MOU is still waiting for review.',
        'mou_not_pending' => 'This MOU has already been processed.',
        'profile_incomplete' => 'Complete your company profile before uploading the MOU.',
        'mou_programs_required' => 'Choose at least one program covered by this MOU.',
        'card_already_active' => ':name still has an active student card. Use Reissue to replace it.',
        'card_not_active' => 'Student card :number is no longer active.',
        'card_photo_missing' => 'The student card cannot be issued yet: the passport photo is missing or not approved.',
        'card_valid_until_required' => 'Enter the card’s expiry date. It cannot be calculated because the program duration or enrollment period is empty.',
        'card_valid_until_past' => 'The card’s expiry date must be after today.',
        'card_number_taken' => 'Student card number :number is already in use.',
        'course_unavailable' => 'Course :course is no longer active.',
        'course_already_added' => 'This student already has :course. If it was dropped, restore it from the list.',
        'course_already_dropped' => 'This course has already been dropped.',
        'course_not_dropped' => 'This course is not dropped.',
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
        'student_enrolled' => [
            'title' => 'Registered as an active student',
            'body' => ':name is now active in :program. The student card and academic rubric will be available in the portal.',
        ],
        'card_issued' => [
            'title' => 'Your student card is ready',
            'body' => 'Student card :number has been issued and is valid until :date.',
        ],
        'card_reissued' => [
            'title' => 'Your student card was reissued',
            'body' => 'New student card :number is valid until :date and replaces your old card. Reason: :reason',
        ],
        'card_revoked' => [
            'title' => 'Your student card was revoked',
            'body' => 'Student card :number is no longer valid. Reason: :reason',
        ],
        'student_invitation' => [
            'title' => 'Your SIPMA portal account',
            'body' => 'Congratulations, :name is now an active student in :program. Create a password to sign in to the SIPMA portal and see your student card and academic rubric.',
            'action' => 'Create password',
            'expiry' => 'This link is valid for :days days and can be used once. After that, sign in with :email.',
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
        'and_more' => 'and :count more',
        'submission_reminder' => [
            'title' => '{1} Application not submitted yet|[2,*] Applications not submitted yet',
            'body_one' => 'The application for :names has not been submitted and has not changed for :days days. Complete and submit it so KUI can review it.',
            'body_many' => ':count students have not been submitted and have not changed for :days days: :names. Complete and submit them so KUI can review them.',
        ],
        'card_expiring' => [
            'title' => 'Your student card expires soon',
            'body' => 'Student card :number is valid until :date. Contact KUI if you still need it after that date.',
        ],
        'grades_published' => [
            'title' => 'Your grades were updated',
            'body' => ':count course grade was updated. See your Academic rubric.|:count course grades were updated. See your Academic rubric.',
        ],
        'cards_expiring' => [
            'title' => 'Student cards expiring soon',
            'body' => ':count active students’ cards expire soon: :names. Reissue them if the program is still running.',
        ],
        'payment_reminder' => [
            'title' => 'Fees not paid yet',
            'body_one' => 'The application for :name is approved, but :fees has not been paid. The VA number is valid for 24 hours — pay soon, then upload the proof.',
            'body_many' => ':count approved students have not paid yet: :names. The VA number is valid for 24 hours.',
        ],
    ],

    'timeline_label' => 'Application progress',

    'accounts' => [
        'existing' => 'The student already has a portal account.',
        'created' => 'A portal account was created and an invitation sent to the student’s email.',
        'linked' => 'Linked to the existing portal account registered with this email.',
        'missing_email' => 'No portal account yet: the student’s email is empty.',
        'email_taken' => 'No portal account yet: the student’s email belongs to another account.',
    ],

    'timeline' => [
        'draft' => ['label' => 'Fill in the application', 'description' => 'Personal data and documents'],
        'submitted' => ['label' => 'Submitted', 'description' => 'Waiting for the International Office'],
        'review' => ['label' => 'Review', 'description' => 'Documents are being checked'],
        'revision' => ['label' => 'Changes needed', 'description' => 'Fix the marked documents and submit again'],
        'payment' => ['label' => 'Payment', 'description' => 'Pay the fees and upload the proof'],
        'loa' => ['label' => 'Letter of Acceptance', 'description' => 'Ready to download'],
    ],

];

<?php

// Label nilai Enum yang tampil di formulir & tabel.
return [

    'gender' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ],

    'role' => [
        'super_admin' => 'International Office',
        'admin' => 'Faculty administrator',
        'agent' => 'Partner agency',
        'student' => 'Student',
    ],

    'payment_type' => [
        'admission_fee' => 'Admission fee',
        'tuition_fee' => 'Tuition fee',
    ],

    'religion' => [
        'islam' => 'Islam',
        'protestant' => 'Protestant Christianity',
        'catholic' => 'Catholicism',
        'hindu' => 'Hinduism',
        'buddhist' => 'Buddhism',
        'confucian' => 'Confucianism',
        'other' => 'Other',
    ],

    'student_status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'in_review' => 'Under review',
        'revision' => 'Revision required',
        'approved' => 'Approved, awaiting payment',
        'loa_issued' => 'LOA issued',
    ],

    'document_status' => [
        'pending' => 'Awaiting review',
        'approved' => 'Approved',
        'revision' => 'Revision required',
        'rejected' => 'Rejected',
    ],

    'document_type' => [
        'photo' => 'Photo (3×4)',
        'guarantor_financial' => 'Guarantor financial statement',
        'student_financial' => 'Student financial statement',
        'declaration' => 'Declaration',
        'medical_statement' => 'Medical statement',
        'passport' => 'Passport',
        'transcript' => 'Academic transcript',
        'recommendation' => 'Recommendation letter',
        'other' => 'Other document',
    ],

    'payment_status' => [
        'pending' => 'Awaiting verification',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ],

    'loa_status' => [
        'pending' => 'Not yet issued',
        'uploaded' => 'Available',
        'downloaded' => 'Downloaded',
    ],

    'mou_status' => [
        'pending' => 'Awaiting verification',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'visa_status' => [
        'not_started' => 'Not started',
        'in_process' => 'In process',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

];

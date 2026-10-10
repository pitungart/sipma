<?php

// /app panel (independent students). Plain language for international readers (R-2.13).
return [
    'home' => [
        'greeting' => 'Welcome, :name',
        'subheading_new' => 'Apply for a non-degree program at Universitas Udayana in a few steps.',
        'subheading' => 'Follow your application and take the next step here.',
        'your_application' => 'Your application',
        'view_application' => 'View application',
        'see_programs' => 'See programs',
        'period_until' => ':period, closes :date',
        'no_period' => 'No period is open yet',
        'prepare_title' => 'What to prepare',
        'prepare_desc' => 'Prepare these files before you start. Each file can be up to 300 KB.',
        'prepare_passport' => 'A valid passport (number, issue date, and expiry date).',
        'stages' => [
            'start' => ['title' => 'Start your application', 'body' => 'Choose a program, fill in your details, then upload your documents. You can save a draft and continue at any time.'],
            'complete' => ['title' => 'Complete your application', 'body' => ':count item(s) still need to be completed before you can submit.', 'cta' => 'Continue'],
            'submit' => ['title' => 'Ready to submit', 'body' => 'Every item is complete. Check it once more, then submit your application to KUI.', 'cta' => 'Submit application'],
            'waiting' => ['title' => 'KUI is reviewing your application', 'body' => 'KUI is checking your application. You will get a notification when there is a decision.'],
            'revision' => ['title' => 'Changes needed', 'body' => 'KUI asked for changes. Fix the marked documents, then submit again.', 'cta' => 'Fix now'],
            'pay' => ['title' => 'Approved — please pay', 'body' => 'Not paid yet: :fees. Transfer to the VA number, then upload your transfer proof.', 'cta' => 'Pay & upload proof'],
            'payment_review' => ['title' => 'Payment under review', 'body' => 'KUI is checking your payment proof. The LOA is issued once every fee is verified.'],
            'loa' => ['title' => 'Your LOA is ready', 'body' => 'Congratulations! Your Letter of Acceptance is ready to download.'],
        ],
    ],

    'application' => [
        'title' => 'My application',
        'start_title' => 'Start your application',
        'start_description' => 'Fields marked * are enough to save a draft. The rest can be completed later and is checked when you submit.',
        'program_step_hint' => 'Programs open now',
        'program_hint' => 'Only programs with an open registration period.',
        'consent' => 'I confirm these details are correct and agree to KUI Universitas Udayana processing my data for this application.',
        'saved' => 'Details saved',
        'actions' => [
            'start' => 'Start application',
        ],
    ],

    'programs' => [
        'title' => 'Programs',
        'description' => 'Non-degree programs with an open registration period.',
        'open_title' => 'Programs open now',
        'empty' => 'No programs are open yet',
        'empty_body' => 'Please check again later, or contact KUI Universitas Udayana.',
        'period' => ':period · closes :date',
        'choose' => 'Choose this program',
        'chosen' => 'Your program',
        'change_heading' => 'Switch to :program?',
        'change_description' => 'Your application program will change. Your details and uploaded documents stay saved.',
        'changed' => 'Program changed to :program',
    ],
];

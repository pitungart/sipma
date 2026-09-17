<?php

return [

    'meta' => [
        'title' => 'Create your account',
    ],

    'brand' => [
        'system_name' => 'Non‑Degree International Student Admission Information System',
        'office' => 'International Office · Universitas Udayana',
        'logo_alt' => 'Universitas Udayana logo',
    ],

    'skip_to_content' => 'Skip to main content',

    // Nama bahasa ditulis dalam bahasanya sendiri (endonim) di kedua file
    'language' => [
        'label' => 'Language',
        'en' => 'English',
        'id' => 'Bahasa Indonesia',
    ],

    'footer' => [
        'copyright' => '© :year Universitas Udayana',
    ],

    'register' => [
        'title' => 'Create your SIPMA account',
        'role_legend' => 'Who are you signing up as?',
        'roles' => [
            'student' => [
                'title' => 'Student',
            ],
            'agent' => [
                'title' => 'Partner agency',
            ],
        ],
        'fields' => [
            'name' => 'Full name',
            'name_hint' => 'As written in your passport.',
            'contact_name' => 'Contact person',
            'email' => 'Email address',
            'password' => 'Password',
            'password_hint' => 'At least 8 characters.',
            'password_confirmation' => 'Confirm password',
            'agency_name' => 'Agency name',
            'country' => 'Country',
        ],
        'agency' => [
            'title' => 'About your agency',
            'text' => 'Next, you’ll upload your MOU. The International Office reviews it before you can register students.',
        ],
        'consent' => 'I agree to Universitas Udayana processing the personal data I submit for applications.',
        'submit' => 'Create account',
        'submitting' => 'Creating your account…',
        'have_account' => 'Already have an account?',
        'login' => 'Log in',
        'error_summary' => 'There is a problem with your sign-up',
        'error_prefix' => 'Error:',
        'throttled' => 'Too many sign-up attempts. Please wait :seconds seconds and try again.',
    ],

    // Tombol ikon mata pada field password
    'password' => [
        'show' => 'Show password',
        'hide' => 'Hide password',
    ],

    // Login terpadu untuk semua role
    'login' => [
        'meta_title' => 'Log in',
        'title' => 'Log in to SIPMA',
        'subtitle' => 'For students, partner agencies, and university staff.',
        'email' => 'Email address',
        'password' => 'Password',
        'remember' => 'Keep me logged in',
        'forgot' => 'Forgot password?',
        'submit' => 'Log in',
        'submitting' => 'Logging in…',
        'no_account' => 'Don’t have an account?',
        'register' => 'Create one',
        'error_summary' => 'We couldn’t log you in',
        'failed' => 'The email address or password is incorrect.',
        'inactive' => 'This account has been deactivated. Contact the International Office for help.',
        'throttled' => 'Too many login attempts. Please wait :seconds seconds and try again.',
        'validation' => [
            'email_required' => 'Enter your email address.',
            'email_email' => 'Enter a valid email address, like name@example.com.',
            'password_required' => 'Enter your password.',
        ],
    ],

    // R-2.13: bahasa sederhana untuk pembaca warga negara asing
    'validation' => [
        'role' => 'Choose whether you are signing up as a student or a partner agency.',
        'name_required' => 'Enter your full name.',
        'email_required' => 'Enter your email address.',
        'email_email' => 'Enter a valid email address, like name@example.com.',
        'email_unique' => 'An account with this email address already exists. Log in instead.',
        'password_required' => 'Create a password.',
        'password_min' => 'Your password needs at least :min characters.',
        'password_confirmation_required' => 'Type your password again.',
        'password_mismatch' => 'The passwords do not match. Type the same password in both fields.',
        'agency_name_required' => 'Enter the name of your agency.',
        'country_required' => 'Enter the country where your agency is based.',
        'consent' => 'To create an account, you need to agree to how we process your personal data.',
        'max' => 'This must be :max characters or fewer.',
    ],

];

<?php
// HostelCare configuration. Every value can be overridden with an environment variable,
// so no secrets need to live in this file.
return [
    'app' => [
        'name'        => 'HostelCare',
        'institution' => getenv('HC_INSTITUTION') ?: 'Your Hostel',
        'timezone'    => getenv('HC_TZ') ?: 'Asia/Kolkata',
        // Set when the app lives in a sub-folder, e.g. "/hostelcare/public". No trailing slash.
        'base_path'   => rtrim(getenv('HC_BASE_PATH') ?: '', '/'),
        // Address people use to reach this site, used in emails and QR codes, e.g. "https://care.example.edu".
        // Scheme and host only. If empty, the installer stores the address of the admin who installs it.
        'public_url'  => rtrim(getenv('HC_PUBLIC_URL') ?: '', '/'),
    ],
    'db' => [
        // "sqlite" runs with zero setup. Use "mysql" for production.
        'driver'      => getenv('HC_DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => getenv('HC_SQLITE_PATH') ?: dirname(__DIR__) . '/storage/db/hostelcare.sqlite',
        'host'        => getenv('HC_DB_HOST') ?: '127.0.0.1',
        'port'        => getenv('HC_DB_PORT') ?: '3306',
        'name'        => getenv('HC_DB_NAME') ?: 'hostelcare',
        'user'        => getenv('HC_DB_USER') ?: 'root',
        'pass'        => getenv('HC_DB_PASS') ?: '',
    ],
    'rules' => [
        'auto_close_days'    => 3,   // resolved complaints close automatically after N days
        'reopen_days'        => 7,   // a student may reopen within N days of resolution
        'max_files'          => 3,
        'max_file_mb'        => 4,
        'login_max_fail'     => 5,
        'login_window_min'   => 15,
        'page_size'          => 20,
        'min_password'       => 8,
    ],
    // SMS goes out through the free open-source "SMS Gateway for Android" app running on your own phone
    // (your SIM, your plan's SMS charges). Off until you set HC_SMS_ENABLED=1 and the connection details.
    'sms' => [
        'enabled'      => getenv('HC_SMS_ENABLED') === '1',
        'base_url'     => getenv('HC_SMS_URL') ?: 'http://192.168.1.50:8080',   // shown inside the phone app
        'path'         => getenv('HC_SMS_PATH') ?: '/3rdparty/v1/messages',
        'username'     => getenv('HC_SMS_USER') ?: '',
        'password'     => getenv('HC_SMS_PASS') ?: '',
        'country_code' => getenv('HC_SMS_COUNTRY') ?: '+91',  // used for numbers typed without a country code
        'timeout'      => 4,     // seconds to wait for the phone
        'max_attempts' => 3,
        'daily_cap'    => 90,    // Indian SIMs are limited to about 100 normal SMS per day
        'batch'        => 10,    // messages sent per request
    ],
    // Email goes out through any SMTP server. For free sending use a Gmail account with an
    // "app password" (Google Account > Security > 2-Step Verification > App passwords).
    'mail' => [
        'enabled'      => getenv('HC_MAIL_ENABLED') === '1',
        'host'         => getenv('HC_MAIL_HOST') ?: 'smtp.gmail.com',
        'port'         => (int) (getenv('HC_MAIL_PORT') ?: 587),
        'secure'       => getenv('HC_MAIL_SECURE') ?: 'tls',     // tls (port 587), ssl (port 465) or none (tests only)
        'username'     => getenv('HC_MAIL_USER') ?: '',
        'password'     => getenv('HC_MAIL_PASS') ?: '',
        'from_email'   => getenv('HC_MAIL_FROM') ?: '',          // defaults to the username
        'from_name'    => getenv('HC_MAIL_FROM_NAME') ?: 'HostelCare',
        'timeout'      => 10,
        'max_attempts' => 3,
        'batch'        => 10,
        // Development only: show the text of queued emails on the admin Email page (links included) so the flow
        // can be tried without a mail server. Never switch this on for a live site.
        'preview'      => getenv('HC_MAIL_PREVIEW') === '1',
    ],
];

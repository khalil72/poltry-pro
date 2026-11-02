<?php
return [
    'name'     => env('APP_NAME', 'PoultryPro'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', 'false') === 'true',
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'currency' => env('CURRENCY', 'PKR'),
    'session_idle_minutes' => 120,
    'login_max_attempts'   => 5,
    'login_lock_minutes'   => 15,
    'upload_max_mb'        => 5,
    'url'                  => env('APP_URL', ''),
    'mail_driver'          => env('MAIL_DRIVER', env('APP_ENV', 'production') === 'production' ? 'mail' : 'log'),
    'mail_from'            => env('MAIL_FROM', 'no-reply@localhost'),
    'password_reset_minutes' => 60,
];

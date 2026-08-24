<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

/*
 * Bootstrap fallback only.
 *
 * These values are consulted ONLY while the `admin_credentials` table is empty,
 * so a fresh deploy can be signed into. As soon as the admin saves a credential
 * from Settings (or an operator runs `php artisan admin:password`), the database
 * row becomes authoritative for both username and password and nothing here is
 * read again. See App\Services\AdminCredentials.
 */
return [
    'username' => env('ADMIN_USERNAME', 'admin'),
    'password' => env('ADMIN_PASSWORD', 'change-me'),
];

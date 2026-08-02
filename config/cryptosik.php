<?php

declare(strict_types=1);

return [
    'otp' => [
        'ttl_minutes' => (int) env('CRYPTOSIK_OTP_TTL_MINUTES', 10),
        'dev_code' => (string) env('CRYPTOSIK_DEV_OTP_CODE', '111111'),
        'max_attempts' => (int) env('CRYPTOSIK_OTP_MAX_ATTEMPTS', 5),
        'lock_minutes' => (int) env('CRYPTOSIK_OTP_LOCK_MINUTES', 15),
        'request_max_per_window' => (int) env('CRYPTOSIK_OTP_REQUEST_MAX_PER_WINDOW', 5),
        'request_window_minutes' => (int) env('CRYPTOSIK_OTP_REQUEST_WINDOW_MINUTES', 15),
    ],
    'throttle' => [
        'admin_login' => [
            'max_attempts' => (int) env('CRYPTOSIK_ADMIN_LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => (int) env('CRYPTOSIK_ADMIN_LOGIN_DECAY_MINUTES', 15),
        ],
        'otp_verify' => [
            'max_attempts' => (int) env('CRYPTOSIK_OTP_VERIFY_MAX_ATTEMPTS', 10),
            'decay_minutes' => (int) env('CRYPTOSIK_OTP_VERIFY_DECAY_MINUTES', 15),
        ],
        'vault_unlock' => [
            'max_attempts' => (int) env('CRYPTOSIK_VAULT_UNLOCK_MAX_ATTEMPTS', 10),
            'decay_minutes' => (int) env('CRYPTOSIK_VAULT_UNLOCK_DECAY_MINUTES', 15),
        ],
    ],
    'limits' => [
        'attachments_per_entry' => (int) env('CRYPTOSIK_ATTACHMENTS_PER_ENTRY_LIMIT', 10),
        'attachment_size_bytes' => (int) env('CRYPTOSIK_ATTACHMENT_SIZE_LIMIT_BYTES', 10_485_760),
        'entry_content_chars' => (int) env('CRYPTOSIK_ENTRY_CONTENT_CHARS_LIMIT', 2_000_000),
        // Capped at the width of users.nickname (VARCHAR(80)).
        'user_nickname_chars' => min((int) env('CRYPTOSIK_USER_NICKNAME_CHARS_LIMIT', 80), 80),
    ],
    'crypto' => [
        'argon_opslimit' => (int) env('CRYPTOSIK_ARGON_OPSLIMIT', SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE),
        'argon_memlimit' => (int) env('CRYPTOSIK_ARGON_MEMLIMIT', SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE),
    ],
    'integrity' => [
        'verify_every_hours' => (int) env('CRYPTOSIK_CHAIN_VERIFY_EVERY_HOURS', 3),
    ],
    'notifications' => [
        'weekly_unread_cron' => (string) env('CRYPTOSIK_NOTIFICATIONS_WEEKLY_UNREAD_CRON', '0 9 * * 6'),
    ],
    'audit_logs' => [
        'retention_days' => (string) env('CRYPTOSIK_AUDIT_LOG_RETENTION_DAYS', 'never'),
        'prune_cron' => (string) env('CRYPTOSIK_AUDIT_LOG_PRUNE_CRON', '15 3 * * *'),
    ],
    'admin' => [
        'path' => trim((string) env('CRYPTOSIK_ADMIN_PATH', 'admin'), " \t\n\r\0\x0B/") ?: 'admin',
    ],
    'locales' => ['en', 'pl', 'de', 'es'],
    'allowed_mime_types' => [
        'text/plain',
        'text/markdown',
        'application/pdf',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg',
        'image/webp',
    ],
    'allowed_attachment_extensions' => [
        'txt',
        'md',
        'pdf',
        'odt',
        'ods',
        'odp',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'png',
        'jpg',
        'jpeg',
        'webp',
    ],
];

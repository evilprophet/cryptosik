<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Support;

final class SessionKeys
{
    public const USER_ID = 'auth.user_id';
    public const USER_EMAIL = 'auth.user_email';
    public const USER_NICKNAME = 'auth.user_nickname';
    public const USER_NOTIFICATIONS_ENABLED = 'auth.user_notifications_enabled';
    public const ADMIN_ID = 'auth.admin_id';
    public const ADMIN_LOGIN = 'auth.admin_login';
    public const UNLOCKED_VAULT_ID = 'vault.unlocked_id';
    public const UNLOCKED_VAULT_KEY = 'vault.unlocked_data_key';

    private function __construct()
    {
    }
}

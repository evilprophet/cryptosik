# Cryptosik Endpoint Map

This map documents application-level routes and runtime commands.

## User Authentication and Session

- `GET /` - Redirects to vault workspace when user session exists, otherwise to `/login`.
- `GET /login` - User login screen.
- `POST /login/request-code` - Request OTP code.
- `POST /login/verify-code` - Verify OTP code and start user session.
- `GET /vault/unlock` - Vault key input screen (requires user session).
- `POST /vault/unlock` - Unlock vault by key (requires user session).
- `POST /logout` - User logout.
- `POST /locale` - Update locale in session (and user profile when authenticated).
- `POST /settings` - Update user settings (`nickname`, `locale`, `notifications_enabled`).

## Vault Workspace

- `GET /vault` - Vault workspace (`overview`, `new`, or selected `entry` mode).
- `POST /vault/lock` - Lock currently unlocked vault (clear vault session keys).
- `POST /vault/description` - Update vault description and title (owner only, active vault only).
- `GET /vault/entries/{entry}/attachments/{attachment}` - Download decrypted finalized attachment.

### Draft Operations

- `POST /vault/draft/save` - Save encrypted draft.
- `POST /vault/draft/delete` - Delete current user draft with draft attachments.
- `POST /vault/draft/finalize` - Finalize draft to immutable entry and extend hash chain.
- `POST /vault/draft/attachments` - Upload encrypted draft attachment.
- `POST /vault/draft/attachments/{attachment}/delete` - Delete draft attachment.

## Admin Panel (`/{CRYPTOSIK_ADMIN_PATH}`)

Default admin path is `/admin`.

### Admin Authentication

- `GET /admin` - Redirect to admin login.
- `GET /admin/login` - Admin login screen.
- `POST /admin/login` - Admin login submit.
- `POST /admin/logout` - Admin logout.

### Admin Management

- `GET /admin/dashboard` - Admin dashboard.
- `GET /admin/logs` - Audit log browser with filters.
- `GET /admin/users` - User list.
- `POST /admin/users` - Create user.
- `POST /admin/users/{user}/nickname` - Update user settings (`nickname`, `locale`, `notifications_enabled`).
- `POST /admin/users/{user}/deactivate` - Deactivate user.
- `POST /admin/users/{user}/activate` - Activate user.
- `GET /admin/vaults` - Vault list with membership and integrity status.
- `POST /admin/vaults` - Create vault and assign owner.
- `POST /admin/vaults/{vault}/members` - Assign member.
- `POST /admin/vaults/{vault}/members/{user}/notify` - Send membership notification email manually.
- `POST /admin/vaults/{vault}/archive` - Archive vault.
- `POST /admin/vaults/{vault}/soft-delete` - Soft-delete vault.
- `POST /admin/vaults/{vaultId}/restore` - Restore soft-deleted vault.

## Console Commands

- `php artisan cryptosik:admin-create {login} {password?}`
- `php artisan cryptosik:export-vault {vault_id} {output_path}`
- `php artisan cryptosik:verify-chains {--vault=}`
- `php artisan cryptosik:otp-prune {--dry-run}`
- `php artisan cryptosik:notifications:weekly-unread {--per-vault=5}`

## Scheduler

Defined in `routes/console.php`:

- `cryptosik:verify-chains` - every 3 hours (`0 */3 * * *`)
- `cryptosik:otp-prune` - hourly (`0 * * * *`)
- `cryptosik:notifications:weekly-unread` - cron from `CRYPTOSIK_NOTIFICATIONS_WEEKLY_UNREAD_CRON` (default `0 9 * * 6`, Saturday 09:00 app timezone)

## Framework Utility Endpoints (Not Business API)

- `/up` - Laravel health check.
- `/storage/{path}` - local file serving/upload route.
- `/livewire-*/*` - Livewire asset/update routes.

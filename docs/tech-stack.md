# 🧰 Tech Stack

This document is the source of truth for the Cryptosik technology stack.

## 🧪 Language and Runtime

- PHP `8.3+`
- Laravel `13`
- Runtime mode selected by `APP_MODE` (`dev` or `prod`)
- Production image base: `php:8.3-apache`

## 🧱 Core Backend Stack

- `laravel/framework` - HTTP layer, routing, validation, scheduler, ORM
- Eloquent models for domain entities (`Vault`, `Entry`, `EntryDraft`, `VaultCrypto`, `EntryRead`, ...)
- Session-based auth with dedicated user/admin session keys
- Laravel Mail for OTP and notification delivery

## 🌐 Frontend Stack

- Blade templates (SSR)
- Livewire `4.2` (package installed for interactive UI integration)
- Tailwind CSS `4`
- DaisyUI `5` (custom `default-dark` theme)
- Vite `8` + `laravel-vite-plugin`

## 💾 Storage and Database

Supported databases:
- SQLite (`DB_CONNECTION=sqlite`) - default in local setup/tests
- MySQL (`DB_CONNECTION=mysql`) - primary production target

Primary tables:
- `users`, `admins`, `auth_login_codes`
- `vaults`, `vault_members`, `vault_crypto`
- `entry_drafts`, `draft_attachments`
- `entries`, `entry_attachments`, `entry_reads`
- `vault_chain_states`, `chain_verification_runs`, `audit_logs`

Notification-related columns:
- `users.notifications_enabled`
- `vault_members.membership_notified_at`

Persistence in container runtime:
- `./data` mounted to `/var/www/html/data`
- `storage`, `database`, and `bootstrap/cache` are symlinked to `data/*`

## 🔐 Cryptography and Integrity

- Payload encryption: `XChaCha20-Poly1305` (libsodium AEAD)
- KDF for vault wrapping key: `Argon2id` (`sodium_crypto_pwhash`)
- Vault lookup and fingerprint: HKDF + HMAC-SHA256 with app secrets
- Append-only chain integrity: SHA-256 hash chain (`prev_hash`, `entry_hash`)
- Attachment integrity contribution: deterministic attachment hash digest

## ✉️ OTP and Mail Delivery

- OTP length: 6 digits
- Dev mode (`APP_MODE=dev`): fixed code (`CRYPTOSIK_DEV_OTP_CODE`)
- Prod mode (`APP_MODE=prod`): random code sent via SMTP
- Membership notifications:
  - immediate send on assign/create when admin selects send-now
  - manual send-later action from admin vault members list
- Weekly unread digest:
  - one email per user
  - summary per vault (`vault - unread count`)
- Mailer default:
  - `log` in dev mode
  - `smtp` in prod mode

## ⏱️ Scheduling and Automation

Laravel scheduler definitions (`routes/console.php`):
- `cryptosik:verify-chains` every 3 hours
- `cryptosik:otp-prune` hourly
- `cryptosik:notifications:weekly-unread` by `CRYPTOSIK_NOTIFICATIONS_WEEKLY_UNREAD_CRON` (default `0 9 * * 6`, Saturday 09:00)

Container scheduler:
- Docker Compose starts a dedicated `scheduler` service.
- The scheduler service uses the same application image and runs `php artisan schedule:work`.
- Both `app` and `scheduler` mount the same `./data` directory.

## 🐳 Containerization and Deployment

- `Dockerfile` builds vendor dependencies and app runtime image
- `docker-compose.yml` runs GHCR image: `ghcr.io/evilprophet/cryptosik:latest`
- `docker-compose.yml` defines separate `app` and `scheduler` services
- Entrypoint responsibilities:
  - prepare persistent directories,
  - create symlinks,
  - run migrations for the web container.

## ✅ Development and Quality Tooling

- Composer scripts (`setup`, `dev`, `test`)
- PHPUnit `12.5` (`php artisan test`)
- Laravel Pint `1.x` available in dev dependencies
- GitLab CI (`.gitlab-ci.yml`) executes unit and integration tests
- GitHub workflow (`build-docker-image.yml`) manually builds and pushes GHCR image

## 🧪 Test Environment

- Tests use Laravel `testing` environment with SQLite
- Integration tests cover auth, vault workflow, admin flows, notifications, console commands, and localization
- Unit tests cover audit service, locator derivation, hash generation, unread logic, and chain verification

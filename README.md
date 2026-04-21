# 🔐 Cryptosik

## Introduction

Cryptosik is a self-hosted, append-only encrypted vault for homelab environments.
It is built as a Laravel monolith with a browser UI for users and admins.

The main goal is to keep vault history immutable while allowing secure draft editing before finalization.

For stack details, see [docs/tech-stack.md](docs/tech-stack.md).

## ✨ Key Features

- Passwordless user sign-in with email OTP.
- Vault unlock with a vault key (no vault list shown to users).
- Encrypted vault metadata, entries, draft content, and attachments in the database.
- Append-only finalized entries with hash-chain integrity (`sequence_no`, `prev_hash`, `entry_hash`).
- Per-user encrypted draft workflow with attachment upload and finalization.
- Admin panel for user and vault management.
- Scheduled integrity verification and OTP cleanup.
- Per-user unread tracking and weekly digest email notifications.
- Admin-controlled membership notifications (send now or send later).
- Built-in locales: `en`, `pl`, `de`, `es`.

## 📁 Project Structure

```text
.
├── app/                # Domain code (controllers, models, services, commands)
├── bootstrap/          # Laravel bootstrap and middleware wiring
├── config/             # Runtime configuration
├── data/               # Persistent runtime data (docker entrypoint target)
├── database/           # Migrations, factories, seeders
├── docker/             # Container entrypoint and scheduler cron
├── docs/               # Project documentation
├── public/             # Public web root and built frontend assets
├── resources/          # Blade views, frontend assets, translations
├── routes/             # Web routes and scheduler wiring
├── tests/              # Unit and integration tests
├── Dockerfile          # Production image definition
├── docker-compose.yml  # Runtime compose setup (GHCR image)
└── README.md           # Main project guide
```

## 🛠️ Requirements

- Docker and Docker Compose (for Docker-only flow)
- PHP `8.3+` and Composer `2+` (for local development)
- Node.js `20+` and npm (for local frontend assets)
- SQLite or MySQL (for local development)
- `ext-sodium` (libsodium)
- SMTP server for OTP and notification emails in `APP_MODE=prod`

## 🚀 Quick Start

You can run Cryptosik in two ways.

### Option 1: Docker only (without cloning the full repository)

#### 1. Download `docker-compose.yml` and `.env`

```bash
mkdir -p cryptosik
cd cryptosik
curl -L -o docker-compose.yml https://raw.githubusercontent.com/evilprophet/cryptosik/main/docker-compose.yml
curl -L -o .env https://raw.githubusercontent.com/evilprophet/cryptosik/main/.env.example
```

#### 2. Update `.env`

Set at minimum:
- `APP_KEY`
- `CRYPTOSIK_LOOKUP_PEPPER`
- `CRYPTOSIK_LOOKUP_SALT`
- `APP_MODE` (`dev` or `prod`)

If you run in `prod`, also configure `MAIL_*` variables.

#### 3. Start container

```bash
docker compose pull
docker compose up -d
```

#### 4. Open application

Open: `http://localhost:8080`

Create the first admin account:

```bash
docker compose exec app php artisan cryptosik:admin-create '{username}' '{password}'
```

Notes:
- Container entrypoint runs migrations automatically.
- Container cron runs Laravel scheduler every minute.

### Option 2: Clone full project (local development)

#### 1. Clone and install dependencies

```bash
git clone https://github.com/evilprophet/cryptosik.git
cd cryptosik
composer install
cp .env.example .env
```

#### 2. Update `.env` and generate app key

```bash
php artisan key:generate
```

Also set required secrets:
- `CRYPTOSIK_LOOKUP_PEPPER`
- `CRYPTOSIK_LOOKUP_SALT`

#### 3. Prepare local database and run migrations

```bash
mkdir -p database
touch database/database.sqlite
php artisan migrate --seed
```

#### 4. Build frontend assets and run app

```bash
npm install
npm run dev
php artisan serve
```

Open: `http://127.0.0.1:8000`

Seeded defaults after `--seed`:
- users: `owner@example.com`, `member@example.com`
- admin: `admin` / `admin-password`
- dev OTP code: value from `CRYPTOSIK_DEV_OTP_CODE` (default `111111`)

## ⚙️ Configuration

Required security variables (must not stay as placeholders):

- `CRYPTOSIK_LOOKUP_PEPPER`
- `CRYPTOSIK_LOOKUP_SALT`
- `APP_KEY`

Important runtime variables:

- `APP_MODE` (`dev` or `prod`)
- `TRUSTED_PROXIES` (set when running behind reverse proxy, for example `REMOTE_ADDR`)
- `CRYPTOSIK_DEV_OTP_CODE`
- `CRYPTOSIK_ADMIN_PATH`
- `CRYPTOSIK_NOTIFICATIONS_WEEKLY_UNREAD_CRON` (default `0 9 * * 6`)
- `DB_CONNECTION` (`sqlite` or `mysql`)
- `MAIL_*` (required for OTP and notification delivery in `prod`)
- `CRYPTOSIK_ATTACHMENTS_PER_ENTRY_LIMIT`
- `CRYPTOSIK_ATTACHMENT_SIZE_LIMIT_BYTES`
- `CRYPTOSIK_ENTRY_CONTENT_CHARS_LIMIT`
- `CRYPTOSIK_USER_NICKNAME_CHARS_LIMIT`

## 🧭 How It Works

1. User requests OTP by email.
2. User verifies OTP and starts an authenticated user session.
3. User unlocks a vault by providing the vault key.
4. User works in vault workspace:
   - reads finalized entries,
   - edits personal encrypted draft,
   - uploads encrypted draft attachments,
   - finalizes draft into immutable history.
5. Admin manages users, vault creation, membership, archive, soft-delete, and restore.
6. Integrity checks run manually or on schedule and save verification runs.
7. Weekly unread digest notifications are sent by scheduler based on `CRYPTOSIK_NOTIFICATIONS_WEEKLY_UNREAD_CRON` (default: Saturday 09:00).

## 💻 Commands Overview

### Custom Artisan commands

| Command                                                             | Description                                             |
|---------------------------------------------------------------------|---------------------------------------------------------|
| `php artisan cryptosik:admin-create {login} {password?}`            | Create an admin account.                                |
| `php artisan cryptosik:export-vault {vault_id} {output_path}`       | Export encrypted vault data to JSONL.                   |
| `php artisan cryptosik:verify-chains {--vault=}`                    | Verify hash-chain integrity for active/archived vaults. |
| `php artisan cryptosik:otp-prune {--dry-run}`                       | Delete obsolete OTP records (consumed/expired).         |
| `php artisan cryptosik:notifications:weekly-unread {--per-vault=5}` | Send weekly unread digest notifications.                |

### Common development commands

| Command                      | Description                        |
|------------------------------|------------------------------------|
| `php artisan serve`          | Run local HTTP server.             |
| `php artisan migrate --seed` | Apply schema and seed sample data. |
| `npm run dev`                | Run Vite in watch mode.            |
| `npm run build`              | Build production frontend assets.  |
| `php artisan route:list`     | Show registered routes.            |
| `php artisan schedule:list`  | Show scheduler definitions.        |
| `php artisan test`           | Run all test suites.               |

## 🧪 Testing & Quality

Latest local verification (2026-04-19):
- `php artisan test`
- Result: `59 passed` (`403 assertions`)

CI:
- `.gitlab-ci.yml` runs unit and integration tests.
- `.github/workflows/build-docker-image.yml` provides manual GHCR build/publish.

## 📚 Documentation

- `docs/tech-stack.md`
- `docs/user-stories.md`
- `docs/erd.md`
- `docs/endpoint-map.md`

## 🧭 Notes

- Admins manage access but do not decrypt vault content automatically.

# Cryptosik User Stories

This document defines user stories and acceptance criteria for the current Cryptosik scope.

## Epic A: Authentication and Vault Access

### US-001 - Request login code
As a user, I want to request a one-time login code by email so I can sign in without a password.
Acceptance criteria:
1. Only active users can receive valid login codes.
2. OTP code has 6 digits.
3. OTP expires after the configured TTL.
4. Request rate limit is enforced per `email + IP`.
5. In `APP_MODE=dev`, fixed OTP code from config is used.

### US-002 - Verify login code
As a user, I want to verify OTP so I can start an authenticated session.
Acceptance criteria:
1. Verification requires a pending login state from step 1.
2. Invalid, expired, consumed, or blocked OTP is rejected.
3. Max verification attempts are enforced.
4. Successful verification consumes the OTP.
5. Success and failure are written to audit logs.

### US-003 - Unlock vault with vault key
As a user, I want to unlock a vault using a vault key so I can access my workspace.
Acceptance criteria:
1. Vault is looked up by derived key locator.
2. Access is granted only when user is a vault member.
3. Soft-deleted vaults cannot be unlocked.
4. Invalid key and no-access cases return a generic denial.
5. Success and failure are written to audit logs.

### US-004 - Logout and vault lock
As a user, I want to lock a vault or logout so session secrets are cleared.
Acceptance criteria:
1. Vault lock clears unlocked vault session keys.
2. Logout clears user and vault session keys.
3. Vault lock action is written to audit logs.

## Epic B: Workspace and Entries

### US-005 - View workspace and history
As a member, I want to browse finalized entries in my vault.
Acceptance criteria:
1. Workspace supports `overview`, `new`, and selected `entry` view.
2. Entry list is ordered by newest sequence first.
3. Only finalized entries are shown in history.
4. Markdown content is rendered safely.
5. Read status is tracked per `user x entry`.

### US-006 - Maintain personal draft
As a member, I want a personal encrypted draft before publishing.
Acceptance criteria:
1. Draft is scoped to `vault_id + user_id`.
2. Draft title/content are encrypted at rest.
3. Draft can be saved and deleted.
4. Archived vault is read-only and blocks draft writes.

### US-007 - Finalize draft into immutable history
As a member, I want to finalize a draft so it becomes immutable history.
Acceptance criteria:
1. Finalization creates the next `sequence_no`.
2. Finalization stores `prev_hash`, `entry_hash`, and `attachment_hash`.
3. Draft and draft attachments are removed after finalization.
4. Finalized entries cannot be edited or deleted via user flow.
5. Finalization action is written to audit logs.

### US-008 - Read entry attachments and metadata
As a member, I want to inspect entry details and attachments.
Acceptance criteria:
1. Selected entry view includes decrypted title/content.
2. Selected entry view includes attachment list and file size.
3. Decryption failures are handled safely in UI.
4. Opening an entry marks it as read for the current user.

## Epic C: Attachments

### US-009 - Upload draft attachment
As a member, I want to attach files to my draft.
Acceptance criteria:
1. Per-entry attachment count limit is enforced.
2. Per-file size limit is enforced.
3. Allowed file types are enforced by extension/MIME allowlist.
4. Attachment metadata and blob are encrypted at rest.
5. Upload action is written to audit logs.

### US-010 - Remove draft attachment
As a member, I want to remove an attachment from my draft.
Acceptance criteria:
1. Member can remove only attachments from own draft.
2. Removing missing attachment returns a user-facing error.

### US-011 - Download finalized attachment
As a member, I want to download finalized entry attachment.
Acceptance criteria:
1. Download requires authenticated user and unlocked vault.
2. User must be a vault member.
3. Attachment must belong to selected entry and vault.
4. File is decrypted on-the-fly and streamed as download response.

## Epic D: Administration

### US-012 - Admin sign-in
As an admin, I want a separate login area to manage platform access.
Acceptance criteria:
1. Admin login is available under configurable admin path.
2. Admin auth uses `login + password`.
3. Success and failure are written to audit logs.

### US-013 - User management
As an admin, I want to manage users.
Acceptance criteria:
1. Admin can create user with unique email.
2. Admin can update user nickname, locale, and notification preference.
3. Admin can activate and deactivate users.
4. User creation is written to audit logs.

### US-014 - Vault and membership management
As an admin, I want to create vaults and assign members.
Acceptance criteria:
1. Admin can create vault with active owner and vault key.
2. Owner is auto-assigned as vault member with `owner` role.
3. Admin can assign active users as `member`.
4. Duplicate assignment is rejected.
5. Admin can choose whether to send membership notification now or later.
6. Admin can manually send membership notification later from vault member list.
7. Vault creation and member assignment are written to audit logs.

### US-015 - Vault lifecycle actions
As an admin, I want to archive, soft-delete, and restore vaults.
Acceptance criteria:
1. Archived vault stays readable but blocks write operations.
2. Soft-deleted vault is inaccessible for unlock/export.
3. Restore returns vault to active state.

### US-016 - Audit log browsing
As an admin, I want to inspect platform audit events.
Acceptance criteria:
1. Logs are visible in admin panel.
2. Logs can be filtered by actor type and action prefix.
3. Actor labels are resolved to user/admin display values when possible.

## Epic E: Notifications and Operations

### US-017 - Verify integrity chain
As an operator, I want to verify vault integrity.
Acceptance criteria:
1. `cryptosik:verify-chains` checks active and archived vaults.
2. Optional `--vault` limits verification scope.
3. Verification run is saved with status and details.
4. Failures include `broken_sequence_no` and audit event `integrity.chain.failed`.

### US-018 - Prune obsolete OTP records
As an operator, I want to clean obsolete OTP rows.
Acceptance criteria:
1. `cryptosik:otp-prune` removes consumed and expired OTP records.
2. `--dry-run` reports count without deletion.

### US-019 - Export vault as JSONL
As an admin/operator, I want to export encrypted vault data.
Acceptance criteria:
1. `cryptosik:export-vault` exports vault, entries, and attachments as JSONL.
2. Export keeps encrypted payloads (no plaintext content export).
3. Export is blocked for soft-deleted vaults.
4. Existing output file path is rejected.

### US-020 - Scheduler automation
As an operator, I want recurring maintenance tasks.
Acceptance criteria:
1. Scheduler runs integrity verification every 3 hours.
2. Scheduler runs OTP prune hourly.
3. Scheduler runs weekly unread digest by `CRYPTOSIK_NOTIFICATIONS_WEEKLY_UNREAD_CRON` (default `0 9 * * 6`, Saturday 09:00).
4. Docker Compose starts a dedicated `scheduler` service with `php artisan schedule:work`.

### US-021 - Weekly unread digest email
As a user, I want periodic email summaries about unread entries.
Acceptance criteria:
1. One digest email is sent per user (not per vault).
2. Digest includes only vaults with unread entries.
3. Digest shows `vault - unread count` summary (no entry content dump).
4. Digest respects user locale.
5. Digest send/failure is written to audit logs.

### US-022 - User notification preference
As a user, I want to enable or disable email notifications.
Acceptance criteria:
1. User can toggle notifications in settings modal.
2. Admin can set notification preference on user create and user edit.
3. Notification preference is persisted in `users.notifications_enabled` and managed in UI.

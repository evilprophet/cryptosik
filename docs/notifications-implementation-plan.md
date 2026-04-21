# Crypt(o-st)osik - Email Notifications Implementation Plan

## Overview

This document defines a decision-complete implementation plan for email notifications in Crypt(o-st)osik.
It covers unread tracking per user and entry, weekly unread digest emails, and admin-controlled membership notifications.

Goals:
- add unread status per `user x entry`
- send weekly digest emails about unread entries
- notify users when they are added to a vault, with admin control over immediate or manual send

Constraints:
- no sensitive data in audit logs or email bodies
- no queue/outbox table for membership notifications
- tests limited to Unit and Integration

## Functional Scope

### 1) Read Status (`user x entry`)

Behavior:
- entries are marked as read automatically when a user opens a finalized entry view
- read status is independent for each user
- drafts are excluded from unread logic
- when author finalizes a new entry, it is auto-marked as read for that author

Expected UX impact:
- entry list can display unread/read indicator
- unread counts are accurate per user and vault

### 2) Weekly Unread Digest

Behavior:
- one weekly digest email per user (not one per vault)
- include only vaults where unread entries exist
- include unread count per vault and a short list of latest unread entry titles
- skip inactive users
- skip deleted vaults

Schedule:
- weekly run via scheduler (recommended Monday 09:00 Europe/Warsaw)

### 3) Vault Membership Notification

Behavior when assigning member to vault:
- admin chooses:
  - send email now, or
  - do not send now
- if not sent now, admin can manually send later from vault members view
- no asynchronous queue for this feature

Important clarification:
- "manual send later" is an explicit admin action in UI, not a background retry mechanism

## Data Model Changes

### New table: `entry_reads`

Columns:
- `id`
- `entry_id` (foreign key -> `entries.id`, cascade delete)
- `user_id` (foreign key -> `users.id`, cascade delete)
- `read_at` (timestamp)
- `created_at`
- `updated_at`

Constraints and indexes:
- unique index: (`entry_id`, `user_id`)
- index on (`user_id`, `read_at`)
- index on (`entry_id`)

Purpose:
- store idempotent read markers for unread calculations and digest generation

### Existing table extension: `vault_members`

Add column:
- `membership_notified_at` (nullable timestamp)

Purpose:
- keep last notification timestamp for membership email status in admin UI
- full history remains in audit logs

No additional queue/outbox table is required.

## Application Layer Changes

### Domain Services

1. `UnreadEntryService`
- `markAsRead(int $userId, int $entryId): void`
- `getUnreadCountsByUser(int $userId): array`
- `getWeeklyDigestDataForUser(int $userId, int $perVaultLimit = 10): array`

Responsibilities:
- idempotent read upsert
- unread aggregation per vault
- digest payload generation

2. `MembershipNotificationService`
- `sendMemberAddedNotification(Vault $vault, User $user, ?int $adminId = null): void`

Responsibilities:
- send "added to vault" email
- update `vault_members.membership_notified_at`
- write audit success/failure event

3. `WeeklyUnreadDigestService`
- `sendAllDigests(): void`

Responsibilities:
- iterate eligible users
- send one digest per user when unread exists
- write audit success/failure event per user

### Controllers / Actions

1. Admin member assignment
- extend request payload with `send_notification_now` (boolean, default `true`)
- on assign:
  - if true: send membership email now
  - if false: skip sending

2. Manual send endpoint for member notification
- add admin action:
  - `POST /{adminPath}/vaults/{vault}/members/{user}/notify`
- this sends membership email on demand

3. Entry open flow
- when finalized entry is opened in user area, call `markAsRead(...)`

## UI Changes

### User Area

1. Entry list:
- show unread marker per entry
- keep existing sort/order rules already agreed in project

2. Entry details:
- on open, triggers read mark
- no new explicit "mark read" button for MVP

### Admin Area

1. Member assignment UI:
- add toggle/checkbox `send notification now`

2. Vault members list:
- show `last notification` value (from `membership_notified_at`, fallback "never")
- add action button `Send Notification` for manual send

## Email Templates

### `MemberAddedToVaultMail`

Content:
- generic info that user was added to a vault
- vault name
- safe CTA to open application

Must not include:
- vault key
- message contents
- decrypted or sensitive metadata

### `WeeklyUnreadDigestMail`

Content:
- one email per user
- grouped by vault:
  - vault name
  - unread count
  - short list of entry titles (no body text)

Localization:
- use `user.locale`

## Scheduler and Console

### New command

- `cryptosik:notifications:weekly-unread`

Responsibilities:
- run weekly digest sending process
- return concise command summary counts (sent, skipped, failed)

### Schedule registration

In `routes/console.php`:
- schedule command weekly at defined time (Monday 09:00 Europe/Warsaw recommended)
- keep existing `cryptosik:verify-chains` and `cryptosik:otp-prune` unchanged

## Audit Logging

Add standardized events:
- `admin.vault.member.notification.sent`
- `admin.vault.member.notification.skipped`
- `admin.vault.member.notification.failed`
- `system.user.weekly_unread_digest.sent`
- `system.user.weekly_unread_digest.failed`

Audit payload policy:
- include only actor/target IDs, action key, timestamp, and non-sensitive technical error code
- never include secret keys, OTP values, email body content, or entry content

## Validation Rules

1. Member assignment request:
- `send_notification_now`: `nullable|boolean`

2. Manual notify endpoint:
- ensure target user is an active member of target vault
- reject invalid combinations with safe generic error

3. Digest generation:
- include only finalized entries
- unread means "no `entry_reads` row for this user-entry pair"

## Testing Plan (Unit + Integration)

### Unit Tests

1. `UnreadEntryServiceTest`
- marks read idempotently
- excludes drafts from unread
- returns correct unread counts per vault

2. `MembershipNotificationServiceTest`
- sends mail and updates `membership_notified_at`
- writes success and failure audits
- handles mailer exception without sensitive leak

3. `WeeklyUnreadDigestServiceTest`
- generates grouped payload per user
- skips users with zero unread
- respects per-vault title cap

### Integration Tests

1. `AdminMemberNotificationFlowTest`
- assign member with `send_notification_now=true` sends email
- assign member with `send_notification_now=false` skips email
- manual notify endpoint sends email later

2. `EntryReadTrackingFlowTest`
- opening finalized entry creates read record
- reopening same entry does not duplicate row
- finalize by author marks own entry as read

3. `WeeklyDigestCommandTest`
- command sends one digest email per user
- command skips users without unread
- audit entries written for send/failure

4. `AuthorizationBoundariesTest`
- manual notify endpoint admin-only
- user area cannot trigger admin notification actions

## Acceptance Criteria

1. Read tracking exists and works per user per entry.
2. Weekly digest sends one email per user with unread entries.
3. Admin can choose send-now or skip-now during member assignment.
4. Admin can manually send membership notification later.
5. Admin UI shows last membership notification timestamp.
6. No sensitive data appears in new audit entries or emails.
7. New Unit and Integration tests pass in CI.

## Implementation Order

1. Database migration (`entry_reads`, `vault_members.membership_notified_at`)
2. Domain services (`UnreadEntryService`, notification services)
3. Controller and route updates (assign + manual notify + mark read)
4. Email templates and localization keys
5. Scheduler command and registration
6. Admin/User UI updates
7. Unit tests
8. Integration tests
9. Final regression run and docs cross-check

## Assumptions Locked for This Plan

1. Read status is set automatically when a finalized entry view is opened.
2. Weekly digest is one email per user, not per vault.
3. Membership notification has only two modes:
- send immediately
- skip now and allow manual send later
4. No queue/outbox mechanism is introduced for membership notifications.
5. Project is still in development mode; no legacy compatibility layer is required for this feature set.


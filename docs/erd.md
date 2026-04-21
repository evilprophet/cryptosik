# Cryptosik ERD

## Overview
This ERD reflects the current Laravel implementation for Cryptosik.
It focuses on append-only entries, encrypted payload storage, per-user unread tracking, vault key lookup, and admin-managed access.

## Mermaid
```mermaid
erDiagram
    users ||--o{ vaults : owns
    users ||--o{ vault_members : belongs_to
    users ||--o{ entry_drafts : creates
    users ||--o{ entries : finalizes
    users ||--o{ entry_reads : reads

    admins ||--o{ vault_members : assigns
    admins ||--o{ chain_verification_runs : initiates

    vaults ||--o{ vault_members : has_members
    vaults ||--|| vault_crypto : has_crypto
    vaults ||--o{ entry_drafts : has_user_drafts
    vaults ||--o{ entries : has_final_entries
    vaults ||--|| vault_chain_states : has_chain_head
    vaults ||--o{ chain_verification_runs : has_verifications

    entry_drafts ||--o{ draft_attachments : has_attachments
    entries ||--o{ entry_attachments : has_attachments
    entries ||--o{ entry_reads : read_markers

    users {
        bigint id PK
        string email UK
        string nickname
        string locale
        boolean notifications_enabled
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    admins {
        bigint id PK
        string login UK
        string password_hash
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    auth_login_codes {
        bigint id PK
        string email IDX
        string code_hash
        timestamp expires_at IDX
        timestamp blocked_until IDX
        timestamp consumed_at
        tinyint attempts
        string ip_address IDX
        timestamp created_at
        timestamp updated_at
    }

    vaults {
        uuid id PK
        bigint owner_user_id FK
        longtext name_enc
        longtext description_enc
        enum status
        timestamp archived_at
        timestamp deleted_at
        timestamp created_at
        timestamp updated_at
    }

    vault_members {
        bigint id PK
        uuid vault_id FK
        bigint user_id FK
        enum role
        bigint added_by_admin_id FK
        timestamp membership_notified_at
        timestamp created_at
        timestamp updated_at
    }

    vault_crypto {
        uuid vault_id PK,FK
        string vault_locator UK
        string kdf_salt
        json kdf_params
        text wrapped_data_key
        string wrap_nonce
        string key_fingerprint
        timestamp created_at
        timestamp updated_at
    }

    entry_drafts {
        bigint id PK
        uuid vault_id FK
        bigint user_id FK
        date entry_date
        longtext title_enc
        longtext content_enc
        enum content_format
        timestamp created_at
        timestamp updated_at
    }

    draft_attachments {
        bigint id PK
        bigint draft_id FK
        longtext filename_enc
        longtext mime_enc
        int size_bytes
        longtext blob_enc
        string blob_nonce
        timestamp created_at
        timestamp updated_at
    }

    entries {
        bigint id PK
        uuid vault_id FK
        bigint sequence_no
        date entry_date
        longtext title_enc
        longtext content_enc
        enum content_format
        char prev_hash
        char entry_hash
        char attachment_hash
        bigint created_by FK
        timestamp finalized_at
        timestamp created_at
        timestamp updated_at
    }

    entry_attachments {
        bigint id PK
        bigint entry_id FK
        longtext filename_enc
        longtext mime_enc
        int size_bytes
        longtext blob_enc
        string blob_nonce
        timestamp created_at
        timestamp updated_at
    }

    entry_reads {
        bigint id PK
        bigint entry_id FK
        bigint user_id FK
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }

    vault_chain_states {
        uuid vault_id PK,FK
        bigint last_sequence_no
        char last_entry_hash
        timestamp created_at
        timestamp updated_at
    }

    chain_verification_runs {
        bigint id PK
        uuid vault_id FK
        timestamp started_at
        timestamp finished_at
        enum result
        bigint broken_sequence_no
        json details_json
        bigint initiated_by_admin_id FK
        boolean initiated_by_system
        timestamp created_at
        timestamp updated_at
    }

    audit_logs {
        bigint id PK
        string actor_type
        bigint actor_id
        string action
        string target_type
        string target_id
        json metadata_json
        timestamp created_at
    }
```

## Important Constraints
- `vault_members (vault_id, user_id)` is unique.
- `entry_drafts (vault_id, user_id)` is unique.
- `entries (vault_id, sequence_no)` is unique.
- `entries (vault_id, entry_hash)` is unique.
- `entry_reads (entry_id, user_id)` is unique.
- `vault_crypto.vault_locator` is unique and used for vault lookup by key.

## Security Notes
- Encrypted text fields store ciphertext+nonce as JSON envelopes.
- Binary file payloads (`blob_enc`) are encrypted and stored as base64 text.
- Final entry history is integrity-protected by `prev_hash` and `entry_hash`.
- Notification and digest audit metadata stores only non-sensitive operational values.

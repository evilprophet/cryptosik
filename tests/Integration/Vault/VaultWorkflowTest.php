<?php

declare(strict_types=1);

namespace Tests\Integration\Vault;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\AuditLog;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryRead;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Models\VaultChainState;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VaultWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const ENTRY_DATE = '2026-04-13';

    private User $user;

    private Vault $vault;

    private string $vaultKey = 'super-safe-vault-key-123';

    private string $dataKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $service = app(VaultAccessService::class);
        $this->vault = $service->createVault($this->user, $this->vaultKey, 'Family Vault', 'Main family vault');

        $unlockResult = $service->unlockVaultForUser($this->user, $this->vaultKey);

        if ($unlockResult === null) {
            throw new RuntimeException('Unable to prepare unlocked vault test fixture.');
        }

        $this->dataKey = $unlockResult['data_key'];
    }

    public function test_user_can_unlock_vault_with_valid_key(): void
    {
        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $this->user->id,
                SessionKeys::USER_EMAIL => $this->user->email,
            ])
            ->post('/vault/unlock', [
                'vault_key' => $this->vaultKey,
            ]);

        $response->assertRedirect(route('vault.workspace'));
        $response->assertSessionHas(SessionKeys::UNLOCKED_VAULT_ID, $this->vault->id);
        $response->assertSessionHas(SessionKeys::UNLOCKED_VAULT_KEY);
    }

    public function test_workspace_defaults_to_overview_mode(): void
    {
        $response = $this
            ->withSession($this->unlockedSession())
            ->get('/vault');

        $response->assertOk();
        $response->assertSee((string) __('messages.vault.workspace.overview_title'));
        $response->assertSee('Main family vault');
        $response->assertDontSee((string) __('messages.vault.workspace.select_entry_hint'));
    }

    public function test_add_message_without_prior_draft_save_creates_append_only_entry_and_updates_chain_head(): void
    {
        $session = $this->unlockedSession();

        $finalizeResponse = $this
            ->withSession($session)
            ->from('/vault?mode=new')
            ->post('/vault/draft/finalize', [
                'entry_date' => self::ENTRY_DATE,
                'title' => 'Pierwszy wpis',
                'content' => 'To jest tresc wpisu.',
            ]);

        $finalizeResponse->assertStatus(302);
        $this->assertSame('/vault', parse_url((string) $finalizeResponse->headers->get('Location'), PHP_URL_PATH));
        $finalizeResponse->assertSessionHas('status');

        $entry = Entry::query()->where('vault_id', $this->vault->id)->first();

        $this->assertNotNull($entry);
        $this->assertSame(1, $entry?->sequence_no);
        $this->assertSame(self::ENTRY_DATE, $entry?->entry_date?->toDateString());
        $this->assertNull($entry?->prev_hash);
        $this->assertNotNull($entry?->entry_hash);

        $chainState = VaultChainState::query()->find($this->vault->id);

        $this->assertNotNull($chainState);
        $this->assertSame(1, $chainState?->last_sequence_no);
        $this->assertSame($entry?->entry_hash, $chainState?->last_entry_hash);

        $this->assertDatabaseCount('entry_drafts', 0);
        $this->assertDatabaseCount('entries', 1);

        $auditLog = AuditLog::query()->where('action', 'vault.entry.added')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('user', $auditLog?->actor_type);
        $this->assertSame($this->user->id, $auditLog?->actor_id);
        $this->assertSame('entry', $auditLog?->target_type);
        $this->assertSame((string) $entry?->id, $auditLog?->target_id);
    }

    public function test_workspace_can_switch_between_new_message_mode_and_entry_mode(): void
    {
        $session = $this->unlockedSession();

        $finalizeResponse = $this
            ->withSession($session)
            ->from('/vault?mode=new')
            ->post('/vault/draft/finalize', [
                'entry_date' => self::ENTRY_DATE,
                'title' => 'Tryb wpisu',
                'content' => 'Przelaczanie trybow.',
            ]);

        $entryId = (int) Entry::query()->where('vault_id', $this->vault->id)->value('id');

        $this->assertGreaterThan(0, $entryId);
        $finalizeResponse->assertStatus(302);

        $newModeResponse = $this
            ->withSession($session)
            ->get('/vault?mode=new');

        $newModeResponse->assertOk();
        $newModeResponse->assertSee('New Message');

        $entryModeResponse = $this
            ->withSession($session)
            ->get('/vault?mode=entry&entry='.$entryId);

        $entryModeResponse->assertOk();
        $entryModeResponse->assertSee('Tryb wpisu');
    }

    public function test_entry_finalize_and_open_marks_entry_as_read_for_author_and_member(): void
    {
        $session = $this->unlockedSession();

        $this
            ->withSession($session)
            ->from('/vault?mode=new')
            ->post('/vault/draft/finalize', [
                'entry_date' => self::ENTRY_DATE,
                'title' => 'Read marker',
                'content' => 'Should be marked as read.',
            ])
            ->assertStatus(302);

        $entry = Entry::query()->where('vault_id', $this->vault->id)->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertDatabaseHas('entry_reads', [
            'entry_id' => $entry?->id,
            'user_id' => $this->user->id,
        ]);

        $member = User::query()->create([
            'email' => 'reader-member@example.com',
            'nickname' => 'reader',
            'locale' => 'en',
            'is_active' => true,
        ]);

        VaultMember::query()->create([
            'vault_id' => $this->vault->id,
            'user_id' => $member->id,
            'role' => VaultMemberRole::Member,
        ]);

        $unlockResult = app(VaultAccessService::class)->unlockVaultForUser($member, $this->vaultKey);
        $this->assertNotNull($unlockResult);

        $this
            ->withSession([
                SessionKeys::USER_ID => $member->id,
                SessionKeys::USER_EMAIL => $member->email,
                SessionKeys::USER_NICKNAME => $member->nickname,
                SessionKeys::UNLOCKED_VAULT_ID => $this->vault->id,
                SessionKeys::UNLOCKED_VAULT_KEY => $unlockResult['data_key'],
            ])
            ->get('/vault?mode=entry&entry='.$entry?->id)
            ->assertOk();

        $this->assertDatabaseHas('entry_reads', [
            'entry_id' => $entry?->id,
            'user_id' => $member->id,
        ]);

        $this->assertSame(2, EntryRead::query()->where('entry_id', $entry?->id)->count());
    }

    public function test_archived_vault_is_read_only_for_new_drafts(): void
    {
        $this->vault->status = VaultStatus::Archived;
        $this->vault->save();

        $response = $this
            ->withSession($this->unlockedSession())
            ->from('/vault?mode=new')
            ->post('/vault/draft/save', [
                'entry_date' => self::ENTRY_DATE,
                'title' => 'Nie powinno sie zapisac',
                'content' => 'Vault archived',
            ]);

        $response->assertRedirect('/vault?mode=new');
        $response->assertSessionHasErrors('title');

        $this->assertDatabaseCount('entry_drafts', 0);
    }

    public function test_attachment_upload_validation_error_keeps_compose_input(): void
    {
        $response = $this
            ->withSession($this->unlockedSession())
            ->from('/vault?mode=new')
            ->post('/vault/draft/attachments', [
                'entry_date' => self::ENTRY_DATE,
                'title' => 'Roboczy tytul',
                'content' => "Linia 1\nLinia 2",
            ]);

        $response->assertRedirect('/vault?mode=new');
        $response->assertSessionHasErrors('attachment');
        $response->assertSessionHasInput('entry_date', self::ENTRY_DATE);
        $response->assertSessionHasInput('title', 'Roboczy tytul');
        $response->assertSessionHasInput('content', "Linia 1\nLinia 2");

        $this->assertDatabaseCount('entry_drafts', 1);
    }

    public function test_vault_owner_can_update_description_from_overview(): void
    {
        $response = $this
            ->withSession($this->unlockedSession())
            ->from('/vault')
            ->post('/vault/description', [
                'title' => 'Updated Family Vault',
                'description' => 'Updated owner description',
            ]);

        $response->assertRedirect('/vault');
        $response->assertSessionHas('status', (string) __('messages.vault.status.description_updated'));

        $overviewResponse = $this
            ->withSession($this->unlockedSession())
            ->get('/vault');

        $overviewResponse->assertOk();
        $overviewResponse->assertSee('Updated Family Vault');
        $overviewResponse->assertSee('Updated owner description');
    }

    public function test_member_cannot_update_vault_description(): void
    {
        $member = User::query()->create([
            'email' => 'member2@example.com',
            'nickname' => 'member2',
            'locale' => 'en',
            'is_active' => true,
        ]);

        VaultMember::query()->create([
            'vault_id' => $this->vault->id,
            'user_id' => $member->id,
            'role' => VaultMemberRole::Member,
        ]);

        $service = app(VaultAccessService::class);
        $unlockResult = $service->unlockVaultForUser($member, $this->vaultKey);

        if ($unlockResult === null) {
            throw new RuntimeException('Unable to unlock vault for member fixture.');
        }

        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $member->id,
                SessionKeys::USER_EMAIL => $member->email,
                SessionKeys::USER_NICKNAME => $member->nickname,
                SessionKeys::UNLOCKED_VAULT_ID => $this->vault->id,
                SessionKeys::UNLOCKED_VAULT_KEY => $unlockResult['data_key'],
            ])
            ->from('/vault')
            ->post('/vault/description', [
                'description' => 'Member update attempt',
            ]);

        $response->assertRedirect('/vault');
        $response->assertSessionHasErrors('description');

        $ownerOverviewResponse = $this
            ->withSession($this->unlockedSession())
            ->get('/vault');

        $ownerOverviewResponse->assertOk();
        $ownerOverviewResponse->assertSee('Main family vault');
        $ownerOverviewResponse->assertDontSee('Member update attempt');
    }

    public function test_unlock_with_invalid_key_creates_failed_audit_log(): void
    {
        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $this->user->id,
                SessionKeys::USER_EMAIL => $this->user->email,
                SessionKeys::USER_NICKNAME => $this->user->nickname,
            ])
            ->from('/vault/unlock')
            ->post('/vault/unlock', [
                'vault_key' => 'invalid-vault-key',
            ]);

        $response->assertRedirect('/vault/unlock');
        $response->assertSessionHasErrors('vault_key');
        $response->assertSessionMissing(SessionKeys::UNLOCKED_VAULT_ID);
        $response->assertSessionMissing(SessionKeys::UNLOCKED_VAULT_KEY);

        $auditLog = AuditLog::query()->where('action', 'vault.open.failed')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('user', $auditLog?->actor_type);
        $this->assertSame($this->user->id, $auditLog?->actor_id);
        $this->assertSame('vault', $auditLog?->target_type);
        $this->assertNull($auditLog?->target_id);
    }

    public function test_lock_vault_clears_unlocked_session_and_creates_audit_log(): void
    {
        $response = $this
            ->withSession($this->unlockedSession())
            ->post('/vault/lock');

        $response->assertRedirect('/vault/unlock');
        $response->assertSessionMissing(SessionKeys::UNLOCKED_VAULT_ID);
        $response->assertSessionMissing(SessionKeys::UNLOCKED_VAULT_KEY);

        $auditLog = AuditLog::query()->where('action', 'vault.locked')->latest('id')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('user', $auditLog?->actor_type);
        $this->assertSame($this->user->id, $auditLog?->actor_id);
        $this->assertSame('vault', $auditLog?->target_type);
        $this->assertSame((string) $this->vault->id, $auditLog?->target_id);
    }

    public function test_archived_vault_description_update_is_rejected_for_owner(): void
    {
        $this->vault->status = VaultStatus::Archived;
        $this->vault->save();

        $response = $this
            ->withSession($this->unlockedSession())
            ->from('/vault')
            ->post('/vault/description', [
                'description' => 'Attempted archived update',
            ]);

        $response->assertRedirect('/vault');
        $response->assertSessionHasErrors('description');

        $overviewResponse = $this
            ->withSession($this->unlockedSession())
            ->get('/vault');

        $overviewResponse->assertOk();
        $overviewResponse->assertSee('Main family vault');
        $overviewResponse->assertDontSee('Attempted archived update');
    }
    private function unlockedSession(): array
    {
        return [
            SessionKeys::USER_ID => $this->user->id,
            SessionKeys::USER_EMAIL => $this->user->email,
            SessionKeys::USER_NICKNAME => $this->user->nickname,
            SessionKeys::UNLOCKED_VAULT_ID => $this->vault->id,
            SessionKeys::UNLOCKED_VAULT_KEY => $this->dataKey,
        ];
    }
}

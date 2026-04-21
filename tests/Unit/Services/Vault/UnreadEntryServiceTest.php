<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vault;

use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Vault\UnreadEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadEntryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_read_is_idempotent(): void
    {
        $user = $this->createUser('reader@example.com', 'Reader');
        $vault = $this->createVault($user);
        $entry = $this->createEntry($vault, $user, 1);

        $service = app(UnreadEntryService::class);
        $service->markAsRead($user->id, $entry->id);
        $service->markAsRead($user->id, $entry->id);

        $this->assertDatabaseCount('entry_reads', 1);
        $this->assertDatabaseHas('entry_reads', [
            'user_id' => $user->id,
            'entry_id' => $entry->id,
        ]);
    }

    public function test_digest_contains_only_unread_entries_from_accessible_non_deleted_vaults(): void
    {
        $owner = $this->createUser('owner@example.com', 'Owner');
        $member = $this->createUser('member@example.com', 'Member');

        $activeVault = $this->createVault($owner, VaultStatus::Active);
        $softDeletedVault = $this->createVault($owner, VaultStatus::SoftDeleted);

        VaultMember::query()->create([
            'vault_id' => $activeVault->id,
            'user_id' => $member->id,
            'role' => VaultMemberRole::Member,
        ]);

        VaultMember::query()->create([
            'vault_id' => $softDeletedVault->id,
            'user_id' => $member->id,
            'role' => VaultMemberRole::Member,
        ]);

        $activeEntryA = $this->createEntry($activeVault, $owner, 1);
        $activeEntryB = $this->createEntry($activeVault, $owner, 2);
        $this->createEntry($softDeletedVault, $owner, 1);

        $service = app(UnreadEntryService::class);
        $service->markAsRead($member->id, $activeEntryA->id);

        $payload = $service->getUnreadDigestForUser($member, 10);

        $this->assertSame(1, $payload['total_unread']);
        $this->assertCount(1, $payload['vaults']);
        $this->assertSame($activeVault->id, $payload['vaults'][0]['vault_id']);
        $this->assertSame(1, $payload['vaults'][0]['unread_count']);
        $this->assertSame($activeEntryB->id, $payload['vaults'][0]['entries'][0]['entry_id']);
    }

    private function createUser(string $email, string $nickname): User
    {
        return User::query()->create([
            'email' => $email,
            'nickname' => $nickname,
            'locale' => 'en',
            'is_active' => true,
        ]);
    }

    private function createVault(User $owner, VaultStatus $status = VaultStatus::Active): Vault
    {
        $vault = Vault::query()->create([
            'owner_user_id' => $owner->id,
            'name_enc' => 'enc-name',
            'description_enc' => null,
            'status' => $status,
        ]);

        VaultMember::query()->create([
            'vault_id' => $vault->id,
            'user_id' => $owner->id,
            'role' => VaultMemberRole::Owner,
        ]);

        return $vault;
    }

    private function createEntry(Vault $vault, User $author, int $sequenceNo): Entry
    {
        return Entry::query()->create([
            'vault_id' => $vault->id,
            'sequence_no' => $sequenceNo,
            'entry_date' => now()->toDateString(),
            'title_enc' => sprintf('enc-title-%d', $sequenceNo),
            'content_enc' => sprintf('enc-content-%d', $sequenceNo),
            'content_format' => EntryContentFormat::Markdown,
            'prev_hash' => $sequenceNo === 1 ? null : hash('sha256', sprintf('prev-%d', $sequenceNo)),
            'entry_hash' => hash('sha256', sprintf('entry-%d', $sequenceNo)),
            'attachment_hash' => null,
            'created_by' => $author->id,
            'finalized_at' => now()->addSeconds($sequenceNo),
        ]);
    }
}


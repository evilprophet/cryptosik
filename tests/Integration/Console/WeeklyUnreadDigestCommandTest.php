<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Mail\WeeklyUnreadDigestMail;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Vault\EntryService;
use EvilStudio\Cryptosik\Services\Vault\UnreadEntryService;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WeeklyUnreadDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_digest_sends_single_mail_to_user_with_unread_entries(): void
    {
        Mail::fake();

        $owner = $this->createUser('owner@example.com', 'Owner');
        $member = $this->createUser('member@example.com', 'Member');

        $vaultAccessService = app(VaultAccessService::class);
        $entryService = app(EntryService::class);
        $unreadEntryService = app(UnreadEntryService::class);

        $vault = $vaultAccessService->createVault(
            owner: $owner,
            vaultKey: 'weekly-digest-vault-key',
            name: 'Digest Vault',
            description: 'Digest fixture',
        );

        VaultMember::query()->create([
            'vault_id' => $vault->id,
            'user_id' => $member->id,
            'role' => VaultMemberRole::Member,
        ]);

        $unlockResult = $vaultAccessService->unlockVaultForUser($owner, 'weekly-digest-vault-key');
        $this->assertNotNull($unlockResult);

        $dataKey = (string) ($unlockResult['data_key'] ?? '');
        $this->assertNotSame('', $dataKey);

        $entryService->upsertDraft(
            user: $owner,
            vault: $vault,
            dataKey: $dataKey,
            entryDate: now()->toDateString(),
            title: 'Digest entry',
            content: 'Digest content',
        );

        $entry = $entryService->finalizeDraft($owner, $vault);
        $unreadEntryService->markAsRead($owner->id, $entry->id);

        $this->artisan('cryptosik:notifications:weekly-unread', ['--per-vault' => 5])
            ->expectsOutput('Weekly digest summary: sent=1 skipped=1 failed=0')
            ->assertSuccessful();

        Mail::assertSent(WeeklyUnreadDigestMail::class, 1);
        Mail::assertSent(WeeklyUnreadDigestMail::class, static fn (WeeklyUnreadDigestMail $mail): bool => $mail->hasTo('member@example.com'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.user.weekly_unread_digest.sent',
            'target_type' => 'user',
            'target_id' => (string) $member->id,
        ]);
    }

    public function test_weekly_digest_skips_when_no_unread_entries_exist(): void
    {
        Mail::fake();

        $user = $this->createUser('user@example.com', 'User');

        $this->artisan('cryptosik:notifications:weekly-unread', ['--per-vault' => 5])
            ->expectsOutput('Weekly digest summary: sent=0 skipped=0 failed=0')
            ->assertSuccessful();

        Mail::assertNothingSent();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'system.user.weekly_unread_digest.sent',
            'target_id' => (string) $user->id,
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
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
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vault;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Mail\VaultMemberAddedMail;
use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Models\VaultMember;
use EvilStudio\Cryptosik\Services\Vault\MembershipNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MembershipNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_member_added_notification_updates_timestamp_and_writes_audit(): void
    {
        Mail::fake();

        $admin = Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $owner = User::query()->create([
            'email' => 'owner@example.com',
            'nickname' => 'Owner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $member = User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'Member',
            'locale' => 'pl',
            'is_active' => true,
        ]);

        $vault = Vault::query()->create([
            'owner_user_id' => $owner->id,
            'name_enc' => 'enc-name',
            'description_enc' => null,
            'status' => VaultStatus::Active,
        ]);

        VaultMember::query()->create([
            'vault_id' => $vault->id,
            'user_id' => $member->id,
            'role' => VaultMemberRole::Member,
        ]);

        app(MembershipNotificationService::class)->sendMemberAddedNotification($admin, $vault, $member, 'manual');

        Mail::assertSent(VaultMemberAddedMail::class, static fn (VaultMemberAddedMail $mail): bool => $mail->vaultId === $vault->id);

        $membership = VaultMember::query()
            ->where('vault_id', $vault->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership?->membership_notified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.vault.member.notification.sent',
            'target_type' => 'vault',
            'target_id' => (string) $vault->id,
        ]);
    }

    public function test_skip_notification_writes_audit_event(): void
    {
        $admin = Admin::query()->create([
            'login' => 'root',
            'password_hash' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $owner = User::query()->create([
            'email' => 'owner@example.com',
            'nickname' => 'Owner',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $member = User::query()->create([
            'email' => 'member@example.com',
            'nickname' => 'Member',
            'locale' => 'en',
            'is_active' => true,
        ]);

        $vault = Vault::query()->create([
            'owner_user_id' => $owner->id,
            'name_enc' => 'enc-name',
            'description_enc' => null,
            'status' => VaultStatus::Active,
        ]);

        app(MembershipNotificationService::class)->skipNotification($admin, $vault, $member);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.vault.member.notification.skipped',
            'target_type' => 'vault',
            'target_id' => (string) $vault->id,
        ]);
    }
}


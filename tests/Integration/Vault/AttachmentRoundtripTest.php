<?php

declare(strict_types=1);

namespace Tests\Integration\Vault;

use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryAttachment;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use EvilStudio\Cryptosik\Services\Vault\VaultAccessService;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class AttachmentRoundtripTest extends TestCase
{
    use RefreshDatabase;

    private const VAULT_KEY = 'attachment-roundtrip-key-001';

    private const PAYLOAD = "linia jedna\nlinia druga\x00\x01\x02 binarny ogon";

    private User $user;

    private Vault $vault;

    private string $dataKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();

        $service = app(VaultAccessService::class);
        $this->vault = $service->createVault($this->user, self::VAULT_KEY, 'Roundtrip Vault', null);

        $unlock = $service->unlockVaultForUser($this->user, self::VAULT_KEY);

        if ($unlock === null) {
            throw new RuntimeException('Unable to prepare unlocked vault fixture.');
        }

        $this->dataKey = $unlock->dataKey;
    }

    public function test_uploaded_attachment_is_stored_encrypted_and_downloads_byte_identical(): void
    {
        $upload = $this
            ->withSession($this->unlockedSession())
            ->from('/vault?mode=new')
            ->post('/vault/draft/attachments', [
                'title' => 'Wpis z zalacznikiem',
                'content' => 'Tresc',
                'attachment' => UploadedFile::fake()->createWithContent('notes.txt', self::PAYLOAD),
            ]);

        $upload->assertSessionHasNoErrors();

        $this->withSession($this->unlockedSession())
            ->from('/vault?mode=new')
            ->post('/vault/draft/finalize', ['entry_date' => '2026-04-13', 'title' => 'Wpis z zalacznikiem', 'content' => 'Tresc'])
            ->assertStatus(302);

        $entry = Entry::query()->where('vault_id', $this->vault->id)->latest('id')->firstOrFail();
        $attachment = EntryAttachment::query()->where('entry_id', $entry->id)->firstOrFail();

        // Nothing readable may sit in the row itself.
        $this->assertStringNotContainsString('linia jedna', $attachment->blob_enc);
        $this->assertStringNotContainsString('notes.txt', $attachment->filename_enc);

        $response = $this
            ->withSession($this->unlockedSession())
            ->get(route('vault.entries.attachments.download', ['entry' => $entry->id, 'attachment' => $attachment->id]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::PAYLOAD, $response->streamedContent());
    }

    public function test_a_non_member_cannot_download_an_attachment(): void
    {
        $this->withSession($this->unlockedSession())
            ->post('/vault/draft/attachments', [
                'attachment' => UploadedFile::fake()->createWithContent('notes.txt', self::PAYLOAD),
            ])->assertSessionHasNoErrors();

        $this->withSession($this->unlockedSession())
            ->from('/vault?mode=new')
            ->post('/vault/draft/finalize', ['entry_date' => '2026-04-13', 'title' => 'Wpis', 'content' => 'Tresc'])
            ->assertStatus(302);

        $entry = Entry::query()->where('vault_id', $this->vault->id)->latest('id')->firstOrFail();
        $attachment = EntryAttachment::query()->where('entry_id', $entry->id)->firstOrFail();

        $stranger = User::factory()->create();

        // A stranger holding a valid-looking session for this vault still gets nothing: the
        // gate resolves membership from the database, not from the session.
        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $stranger->id,
                SessionKeys::USER_EMAIL => $stranger->email,
                SessionKeys::UNLOCKED_VAULT_ID => $this->vault->id,
                SessionKeys::UNLOCKED_VAULT_KEY => $this->dataKey,
            ])
            ->get(route('vault.entries.attachments.download', ['entry' => $entry->id, 'attachment' => $attachment->id]));

        $response->assertRedirect(route('auth.vault.unlock.show'));
    }

    public function test_a_soft_deleted_vault_cannot_be_unlocked(): void
    {
        $this->vault->status = VaultStatus::SoftDeleted;
        $this->vault->save();
        $this->vault->delete();

        $result = app(VaultAccessService::class)->unlockVaultForUser($this->user, self::VAULT_KEY);

        $this->assertNull($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function unlockedSession(): array
    {
        return [
            SessionKeys::USER_ID => $this->user->id,
            SessionKeys::USER_EMAIL => $this->user->email,
            SessionKeys::UNLOCKED_VAULT_ID => $this->vault->id,
            SessionKeys::UNLOCKED_VAULT_KEY => $this->dataKey,
        ];
    }
}

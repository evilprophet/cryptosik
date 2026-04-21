<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Services\Vault;

use EvilStudio\Cryptosik\Enums\VaultStatus;
use EvilStudio\Cryptosik\Models\Entry;
use EvilStudio\Cryptosik\Models\EntryRead;
use EvilStudio\Cryptosik\Models\User;

class UnreadEntryService
{
    public function markAsRead(int $userId, int $entryId): void
    {
        EntryRead::query()->updateOrCreate(
            [
                'entry_id' => $entryId,
                'user_id' => $userId,
            ],
            [
                'read_at' => now(),
            ],
        );
    }

    /**
     * @return int[]
     */
    public function getReadEntryIdsForVault(int $userId, string $vaultId): array
    {
        return EntryRead::query()
            ->select('entry_reads.entry_id')
            ->join('entries', 'entries.id', '=', 'entry_reads.entry_id')
            ->where('entry_reads.user_id', $userId)
            ->where('entries.vault_id', $vaultId)
            ->pluck('entry_reads.entry_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array{total_unread:int, vaults:list<array{vault_id:string, unread_count:int, entries:list<array{entry_id:int, sequence_no:int, entry_date:string, finalized_at:string}>}>}
     */
    public function getUnreadDigestForUser(User $user, int $entriesPerVault): array
    {
        $unreadEntries = Entry::query()
            ->select([
                'entries.id',
                'entries.vault_id',
                'entries.sequence_no',
                'entries.entry_date',
                'entries.finalized_at',
            ])
            ->whereExists(function ($query) use ($user): void {
                $query
                    ->selectRaw('1')
                    ->from('vault_members')
                    ->whereColumn('vault_members.vault_id', 'entries.vault_id')
                    ->where('vault_members.user_id', $user->id);
            })
            ->whereDoesntHave('reads', static function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->whereHas('vault', static function ($query): void {
                $query->whereIn('status', [VaultStatus::Active, VaultStatus::Archived])
                    ->whereNull('deleted_at');
            })
            ->orderByDesc('entries.finalized_at')
            ->get();

        $vaultGroups = [];

        foreach ($unreadEntries->groupBy('vault_id') as $vaultId => $entries) {
            $items = $entries
                ->take($entriesPerVault)
                ->map(static function (Entry $entry): array {
                    return [
                        'entry_id' => $entry->id,
                        'sequence_no' => (int) $entry->sequence_no,
                        'entry_date' => $entry->entry_date?->toDateString() ?? $entry->finalized_at->toDateString(),
                        'finalized_at' => $entry->finalized_at->toDateTimeString(),
                    ];
                })
                ->values()
                ->all();

            $vaultGroups[] = [
                'vault_id' => (string) $vaultId,
                'unread_count' => $entries->count(),
                'entries' => $items,
            ];
        }

        return [
            'total_unread' => $unreadEntries->count(),
            'vaults' => $vaultGroups,
        ];
    }
}

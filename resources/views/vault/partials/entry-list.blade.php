@php($isMobile = (bool) ($isMobile ?? false))

<div class="mb-3 flex items-center justify-between gap-2">
    <h3 @class([
        'text-sm font-semibold uppercase tracking-wide text-base-content/70',
        'text-xs' => $isMobile,
    ])>{{ __('messages.vault.workspace.entries_chronological') }}</h3>
    <a href="{{ route('vault.workspace', ['mode' => 'new']) }}" class="rounded-md border border-base-300 bg-base-300 px-3 py-2 text-xs font-medium text-base-content hover:bg-base-300 cursor-pointer select-text">{{ __('messages.vault.workspace.new_message') }}</a>
</div>

<div @class([
    'min-h-0 flex-1 space-y-1 overflow-y-auto pr-1',
    'max-h-[55dvh]' => $isMobile,
])>
    @forelse ($entries as $item)
        <a href="{{ route('vault.workspace', ['mode' => 'entry', 'entry' => $item['id']]) }}"
           @class([
               'block rounded-lg border px-3 py-2 transition cursor-pointer select-text',
               'border-success bg-success/10' => $selectedEntryId === $item['id'],
               'border-base-300 hover:bg-base-300' => $selectedEntryId !== $item['id'],
               'ring-1 ring-warning/70' => !$item['is_read'],
           ])>
            <p class="flex items-center gap-1 text-xs text-base-content/60">
                <span>{{ $item['entry_date'] }} - {{ $item['author_nickname'] }}</span>
                @if (!$item['is_read'])
                    <span class="inline-flex rounded bg-warning/20 px-1.5 py-0.5 text-[10px] font-semibold text-warning">{{ __('messages.vault.workspace.unread') }}</span>
                @endif
            </p>
            <p class="flex items-center gap-1 text-sm font-medium text-base-content">#{{ $item['sequence_no'] }} {{ $item['title'] }}
                @if ((int) ($item['attachments_count'] ?? 0) > 0)
                    <span class="inline-flex items-center text-[10px] font-semibold text-secondary" title="attachments">📎{{ (int) $item['attachments_count'] }}</span>
                @endif
            </p>
        </a>
        @unless ($loop->last)
            <div class="flex items-center justify-center py-0.5">
                <span class="block h-1.5 w-1.5 rotate-45 border-t border-l border-base-content/40"></span>
            </div>
        @endunless
    @empty
        <p class="text-sm text-base-content/60">{{ __('messages.vault.workspace.no_entries') }}</p>
    @endforelse
</div>

<p>{{ __('messages.mail.common.greeting') }}</p>
<p>{{ __('messages.mail.weekly_unread.intro', ['count' => $totalUnread]) }}</p>

@foreach ($vaults as $vault)
    <p>{{ __('messages.mail.weekly_unread.vault_line', ['vault' => $vault['vault_id'], 'count' => $vault['unread_count']]) }}</p>
@endforeach

<p>{!! __('messages.mail.weekly_unread.action_text', ['link' => '<a href="'.e(url('/')).'">Cryptosik</a>']) !!}</p>
<p>{{ __('messages.mail.footer.regards') }}<br>{{ __('messages.mail.footer.signature') }}</p>

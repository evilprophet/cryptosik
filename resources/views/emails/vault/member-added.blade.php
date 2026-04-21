<p>{{ __('messages.mail.common.greeting') }}</p>
<p>{{ __('messages.mail.member_added.intro') }}</p>
<p>{{ __('messages.mail.member_added.vault_id', ['vault' => $vaultId]) }}</p>
<p>{!! __('messages.mail.member_added.action_text', ['link' => '<a href="'.e(url('/')).'">Cryptosik</a>']) !!}</p>
<p>{{ __('messages.mail.footer.regards') }}<br>{{ __('messages.mail.footer.signature') }}</p>

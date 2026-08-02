{{ __('messages.mail.common.greeting') }}

{{ __('messages.mail.login_otp.intro') }}

{{ $code }}

{{ __('messages.mail.login_otp.expiry', ['minutes' => $ttlMinutes]) }}

{{ __('messages.mail.login_otp.ignore') }}

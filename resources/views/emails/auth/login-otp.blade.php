<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.auth.user_login.mail.subject') }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827;">
<p>{{ __('messages.auth.user_login.mail.greeting') }}</p>
<p>{{ __('messages.auth.user_login.mail.intro') }}</p>
<p style="font-size: 24px; font-weight: 700; letter-spacing: 2px; margin: 16px 0;">{{ $code }}</p>
<p>{{ __('messages.auth.user_login.mail.expiry', ['minutes' => $ttlMinutes]) }}</p>
<p>{{ __('messages.auth.user_login.mail.ignore') }}</p>
</body>
</html>

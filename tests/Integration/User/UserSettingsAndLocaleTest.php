<?php

declare(strict_types=1);

namespace Tests\Integration\User;

use EvilStudio\Cryptosik\Http\Middleware\EnsureUserOtpAuthenticated;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Support\SessionKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_set_locale_prefers_user_locale_when_available(): void
    {
        $user = $this->createUser('locale-user@example.com', 'LocaleUser', 'de');

        $this
            ->withSession([
                SessionKeys::USER_ID => $user->id,
                'app.locale' => 'pl',
            ])
            ->get(route('auth.user.login.show'))
            ->assertOk();

        $this->assertSame('de', app()->getLocale());
    }

    public function test_set_locale_uses_session_locale_when_no_user_locale_is_available(): void
    {
        $this
            ->withSession([
                'app.locale' => 'es',
            ])
            ->get(route('auth.user.login.show'))
            ->assertOk();

        $this->assertSame('es', app()->getLocale());
    }

    public function test_set_locale_falls_back_to_default_locale_when_supported(): void
    {
        config([
            'app.locale' => 'pl',
            'cryptosik.locales' => ['en', 'pl', 'de', 'es'],
        ]);

        $this->get(route('auth.user.login.show'))->assertOk();

        $this->assertSame('pl', app()->getLocale());
    }

    public function test_set_locale_falls_back_to_en_when_default_locale_is_not_supported(): void
    {
        config([
            'app.locale' => 'fr',
            'cryptosik.locales' => ['de'],
        ]);

        $this->get(route('auth.user.login.show'))->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_locale_update_persists_to_authenticated_user_and_session(): void
    {
        $user = $this->createUser('member@example.com', 'Member', 'en');

        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $user->id,
                SessionKeys::USER_NICKNAME => $user->nickname,
            ])
            ->from(route('auth.user.login.show'))
            ->post(route('locale.update'), [
                'locale' => 'de',
            ]);

        $response->assertRedirect(route('auth.user.login.show'));
        $response->assertSessionHas('app.locale', 'de');

        $user->refresh();
        $this->assertSame('de', $user->locale);
    }

    public function test_locale_update_sets_session_for_guest_without_user_persistence(): void
    {
        $user = $this->createUser('guest-locale-check@example.com', 'GuestCheck', 'en');

        $response = $this
            ->from(route('auth.user.login.show'))
            ->post(route('locale.update'), [
                'locale' => 'pl',
            ]);

        $response->assertRedirect(route('auth.user.login.show'));
        $response->assertSessionHas('app.locale', 'pl');

        $user->refresh();
        $this->assertSame('en', $user->locale);
    }

    public function test_locale_update_rejects_invalid_locale_value(): void
    {
        $response = $this
            ->from(route('auth.user.login.show'))
            ->post(route('locale.update'), [
                'locale' => 'xx',
            ]);

        $response->assertRedirect(route('auth.user.login.show'));
        $response->assertSessionHasErrors('locale');
    }

    public function test_user_settings_update_changes_nickname_locale_and_session_values(): void
    {
        $user = $this->createUser('settings-user@example.com', 'OldNick', 'en');

        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $user->id,
                SessionKeys::USER_EMAIL => $user->email,
                SessionKeys::USER_NICKNAME => $user->nickname,
            ])
            ->from('/vault')
            ->post(route('user.settings.update'), [
                'nickname' => '  Night Owl  ',
                'locale' => 'es',
                'notifications_enabled' => '0',
            ]);

        $response->assertRedirect('/vault');
        $response->assertSessionHas('app.locale', 'es');
        $response->assertSessionHas(SessionKeys::USER_NICKNAME, 'Night Owl');
        $response->assertSessionHas(SessionKeys::USER_NOTIFICATIONS_ENABLED, false);

        $user->refresh();
        $this->assertSame('Night Owl', $user->nickname);
        $this->assertSame('es', $user->locale);
        $this->assertFalse((bool) $user->notifications_enabled);
    }

    public function test_user_settings_redirects_to_login_when_session_is_missing_user_id(): void
    {
        $this->withoutMiddleware(EnsureUserOtpAuthenticated::class);

        $response = $this->post(route('user.settings.update'), [
            'nickname' => 'Mystery',
            'locale' => 'en',
        ]);

        $response->assertRedirect(route('auth.user.login.show'));
    }

    public function test_user_settings_redirects_to_login_when_user_cannot_be_resolved(): void
    {
        $this->withoutMiddleware(EnsureUserOtpAuthenticated::class);

        $response = $this
            ->withSession([
                SessionKeys::USER_ID => 999999,
            ])
            ->post(route('user.settings.update'), [
                'nickname' => 'Mystery',
                'locale' => 'en',
            ]);

        $response->assertRedirect(route('auth.user.login.show'));
    }

    public function test_user_settings_rejects_trimmed_empty_nickname(): void
    {
        $user = $this->createUser('blank-nick@example.com', 'Initial', 'en');

        $response = $this
            ->withSession([
                SessionKeys::USER_ID => $user->id,
                SessionKeys::USER_NICKNAME => $user->nickname,
            ])
            ->from('/vault')
            ->post(route('user.settings.update'), [
                'nickname' => '   ',
                'locale' => 'en',
            ]);

        $response->assertRedirect('/vault');
        $response->assertSessionHasErrors('nickname');
    }

    private function createUser(string $email, string $nickname, string $locale): User
    {
        return User::query()->create([
            'email' => $email,
            'nickname' => $nickname,
            'locale' => $locale,
            'notifications_enabled' => true,
            'is_active' => true,
        ]);
    }
}

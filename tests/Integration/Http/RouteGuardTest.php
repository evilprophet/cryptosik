<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inverted deliberately: a route that loses its guard stays in the checked set and fails.
     *
     * @var list<string>
     */
    private const PUBLIC_ROUTE_NAMES = [
        'home',
        'admin.root',
        'admin.login.show',
        'admin.login.submit',
        'locale.update',
        'auth.user.login.show',
        'auth.user.login.request-code',
        'auth.user.login.verify-code',
        'storage.local',
        'storage.local.upload',
    ];

    /**
     * @var list<string>
     */
    private const PUBLIC_ROUTE_URIS = [
        'up',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_every_non_public_route_rejects_a_guest(): void
    {
        $unguarded = [];

        foreach ($this->nonPublicRoutes() as [$method, $uri, $expectedRedirect]) {
            $response = $this->call($method, $this->fillParameters($uri));
            $status = $response->getStatusCode();

            // Routes carrying model parameters are resolved by SubstituteBindings before the
            // gate runs, so a guest gets 404 rather than the redirect. Both keep him out.
            $rejected = match (true) {
                str_contains($uri, '{') => $status >= 300,
                $expectedRedirect !== null => $status === 302 && $response->headers->get('Location') === $expectedRedirect,
                default => $status >= 300,
            };

            if (! $rejected) {
                $unguarded[] = sprintf('%s %s -> %d %s', $method, $uri, $status, (string) $response->headers->get('Location'));
            }
        }

        $this->assertSame([], $unguarded, 'Every route outside PUBLIC_ROUTE_NAMES must send a guest away.');
    }

    public function test_the_checked_route_set_is_not_empty(): void
    {
        $this->assertGreaterThan(10, count($this->nonPublicRoutes()));
    }

    public function test_the_public_allowlist_has_no_stale_entries(): void
    {
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name !== null) {
                $registered[] = $name;
            }
        }

        $this->assertSame([], array_values(array_diff(self::PUBLIC_ROUTE_NAMES, $registered)));
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    private function nonPublicRoutes(): array
    {
        $checked = [];

        foreach (Route::getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['HEAD']);

            if ($methods === []) {
                continue;
            }

            $name = (string) $route->getName();

            if (in_array($name, self::PUBLIC_ROUTE_NAMES, true) || in_array($route->uri(), self::PUBLIC_ROUTE_URIS, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // cryptosik.vault is nested inside cryptosik.user, so for a guest the outer gate
            // fires first and sends him to the login page, not to the unlock page.
            $expected = match (true) {
                in_array('cryptosik.admin', $middleware, true) => route('admin.login.show'),
                in_array('cryptosik.user', $middleware, true) => route('auth.user.login.show'),
                in_array('cryptosik.vault', $middleware, true) => route('auth.vault.unlock.show'),
                default => null,
            };

            $checked[] = [(string) array_values($methods)[0], $route->uri(), $expected];
        }

        return $checked;
    }

    private function fillParameters(string $uri): string
    {
        return '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $uri), '/');
    }
}

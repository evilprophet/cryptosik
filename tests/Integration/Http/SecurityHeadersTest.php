<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_a_web_response(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_content_security_policy_locks_the_page_to_local_resources(): void
    {
        $policy = (string) $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);

        // Emoji glyphs come from the local spritesheet plus a transparent data: pixel, so no
        // external image host may be allowed here.
        $this->assertStringContainsString("img-src 'self' data:", $policy);
        $this->assertStringNotContainsString('jsdelivr', $policy);
        $this->assertStringNotContainsString('http://', $policy);
        $this->assertStringNotContainsString('https://', $policy);
    }

    public function test_no_view_references_an_external_host(): void
    {
        $offenders = [];

        foreach ($this->bladeTemplates() as $path) {
            $contents = (string) file_get_contents($path);

            if (preg_match('#https?://(?!www\.w3\.org)#i', $contents) === 1) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'Self-hosted deployments must not fetch resources from the internet.');
    }

    /**
     * @return list<string>
     */
    private function bladeTemplates(): array
    {
        $paths = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }
}

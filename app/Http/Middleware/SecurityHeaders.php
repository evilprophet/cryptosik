<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * 'unsafe-inline' stays until the inline <script> blocks in the layout and workspace move
     * out of the templates; style-src needs it for the inline emoji glyph positioning.
     */
    private const POLICY = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('Content-Security-Policy', implode('; ', self::POLICY));
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        return $response;
    }
}

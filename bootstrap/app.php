<?php

declare(strict_types=1);

use EvilStudio\Cryptosik\Http\Middleware\EnsureAdminAuthenticated;
use EvilStudio\Cryptosik\Http\Middleware\EnsureUserOtpAuthenticated;
use EvilStudio\Cryptosik\Http\Middleware\EnsureVaultUnlocked;
use EvilStudio\Cryptosik\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'cryptosik.user' => EnsureUserOtpAuthenticated::class,
            'cryptosik.vault' => EnsureVaultUnlocked::class,
            'cryptosik.admin' => EnsureAdminAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();

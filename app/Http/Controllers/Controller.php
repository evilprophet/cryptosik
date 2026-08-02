<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Http\Controllers;

use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\User;
use EvilStudio\Cryptosik\Models\Vault;
use Illuminate\Http\Request;

abstract class Controller
{
    // Loaded and revalidated by the gate middleware; controllers must not query again.
    protected function currentUser(Request $request): ?User
    {
        $user = $request->attributes->get('cryptosik.user');

        return $user instanceof User ? $user : null;
    }

    protected function currentAdmin(Request $request): ?Admin
    {
        $admin = $request->attributes->get('cryptosik.admin');

        return $admin instanceof Admin ? $admin : null;
    }

    protected function currentVault(Request $request): ?Vault
    {
        $vault = $request->attributes->get('cryptosik.vault');

        return $vault instanceof Vault ? $vault : null;
    }
}

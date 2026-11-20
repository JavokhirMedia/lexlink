<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

/**
 * LexLink authenticates exclusively via OneID (see App\Http\Controllers\Auth\OneIdController).
 * Fortify stays installed (User model traits) but registers no routes or views:
 * no password login, registration, password reset, e-mail verification, 2FA or passkeys.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        //
    }
}

<?php

use Illuminate\Support\Facades\Route;

/*
| Route files are split per module so parallel work never collides:
|   auth.php      — OneID login/callback, onboarding (role + licence), team/invites   [Agent: auth-team]
|   cases.php     — cases, legal bases, case members/permissions                       [Agent: cases]
|   requests.php  — data requests, signing, responses, PDF, AI actions on responses    [Agent: requests]
|   billing.php   — plans, wallet, Click/Payme mock, transactions                      [Agent: billing]
|   ai.php        — AI: law assistant, claim drafts                                    [Agent: ai]
|   app.php       — dashboard, audit journal                                           [Agent: billing (dashboard), auth-team (audit)]
|   mock-gov.php  — /mock-gov/v1/* fake government APIs (loaded in bootstrap/app.php)  [Agent: mock-gov]
*/

Route::inertia('/', 'welcome')->name('home');

foreach (['auth', 'cases', 'requests', 'billing', 'ai', 'app'] as $file) {
    if (file_exists($path = __DIR__."/{$file}.php")) {
        require $path;
    }
}

require __DIR__.'/settings.php';

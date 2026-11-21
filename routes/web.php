<?php

use App\Support\LegalMatrix;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Route files are split per module so parallel work never collides:
|   auth.php      — OneID login/callback, onboarding (role + licence), team/invites   [Agent: auth-team]
|   cases.php     — cases, legal bases, case members/permissions                       [Agent: cases]
|   requests.php  — data requests, signing, responses, PDF, AI actions on responses    [Agent: requests]
|   billing.php   — plans, wallet, Click/Payme mock, transactions                      [Agent: billing]
|   ai.php        — AI: law assistant, claim drafts                                    [Agent: ai]
|   app.php       — dashboard, audit journal                                           [Agent: billing (dashboard), auth-team (audit)]
|   legal-map.php — public "Huquqiy xarita" (LegalMatrix overview)                [Agent: legal-map]
|   client.php    — client (ishonch bildiruvchi) read-only access via advocate order  [Agent: order-client]
|   mock-gov.php  — /mock-gov/v1/* fake government APIs (loaded in bootstrap/app.php)  [Agent: mock-gov]
*/

Route::get('/', fn () => Inertia::render('welcome', [
    'matrix' => LegalMatrix::toArray(),
]))->name('home');

foreach (['auth', 'cases', 'requests', 'billing', 'ai', 'app', 'legal-map', 'client'] as $file) {
    if (file_exists($path = __DIR__."/{$file}.php")) {
        require $path;
    }
}

require __DIR__.'/settings.php';

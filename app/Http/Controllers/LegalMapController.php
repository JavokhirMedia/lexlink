<?php

namespace App\Http\Controllers;

use App\Enums\DataRequestType;
use App\Enums\UserRole;
use App\Support\LegalMatrix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Huquqiy xarita" — public overview of what an advocate may obtain, by domain (reads LegalMatrix only). */
class LegalMapController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $role = $request->user()?->role;

        return Inertia::render('legal-map', [
            'matrix' => LegalMatrix::toArray(),
            // Secrecy regime hint per type (DataRequestType::secrecyRegime) for the "Sir rejimi" column.
            'secrecy' => collect(DataRequestType::cases())->mapWithKeys(fn (DataRequestType $t) => [$t->value => $t->secrecyRegime()]),
            'can_create_request' => in_array($role, [UserRole::Advocate, UserRole::Lawyer], true),
        ]);
    }
}

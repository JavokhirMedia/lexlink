<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\Pinfl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'profile' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'pinfl_masked' => Pinfl::mask($user->pinfl),
                'role_label' => $user->role?->label(),
                'license_number' => $user->advocateProfile?->license_number,
                'license_status' => $user->advocateProfile?->license_status,
                'license_checked_at' => $user->advocateProfile?->last_checked_at?->toIso8601String(),
            ],
        ]);
    }

    /** Only the optional contact e-mail can be changed; identity data is owned by OneID. */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil saqlandi.']);

        return to_route('profile.edit');
    }
}

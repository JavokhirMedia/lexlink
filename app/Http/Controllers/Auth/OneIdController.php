<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Services\LicenseChecker;
use App\Support\DemoPersonas;
use App\Support\Pinfl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** OneID (mock) OAuth2 authorization-code client. The only way to sign in. */
class OneIdController extends Controller
{
    public function login(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'personas' => DemoPersonas::all(),
            'error' => $request->session()->get('oneid_error'),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $hint = $request->query('login_hint');
        $hint = is_string($hint) && Pinfl::isValid($hint) ? $hint : null;

        $state = Str::random(40);
        $request->session()->put('oneid_state', $state);

        $query = http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => config('lexlink.mock_gov.oneid_client_id'),
            'redirect_uri' => route('oneid.callback'),
            'state' => $state,
            'scope' => 'openid profile',
            'login_hint' => $hint,
        ]));

        $authorizeUrl = config('lexlink.mock_gov.oneid_authorize_url') ?: url('/mock-gov/v1/oneid/authorize');

        return redirect()->away($authorizeUrl.'?'.$query);
    }

    public function callback(Request $request, GovDataProvider $gov, AuditLogger $audit, LicenseChecker $licenses): RedirectResponse
    {
        $expected = $request->session()->pull('oneid_state');

        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->fail('OneID sessiyasi eskirgan yoki noto\'g\'ri. Qaytadan urinib ko\'ring.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->fail('OneID orqali kirish bekor qilindi.');
        }

        try {
            $info = $gov->oneIdUser((string) $request->query('code'), route('oneid.callback'));
        } catch (GovApiException) {
            return $this->fail('OneID xizmati bilan bog\'lanib bo\'lmadi. Birozdan so\'ng urinib ko\'ring.');
        }

        $pinfl = (string) ($info['pinfl'] ?? '');
        $sub = (string) ($info['sub'] ?? '');

        if (! Pinfl::isValid($pinfl) || $sub === '') {
            return $this->fail('OneID javobida JShShIR topilmadi.');
        }

        $user = User::where('oneid_sub', $sub)->first()
            ?? User::where('pinfl_hash', Pinfl::hash($pinfl))->first()
            ?? new User;

        $user->fill([
            'oneid_sub' => $sub,
            'pinfl' => $pinfl,
            'name' => $info['full_name'] ?? trim(($info['last_name'] ?? '').' '.($info['first_name'] ?? '').' '.($info['middle_name'] ?? '')),
            'phone' => $info['phone'] ?? $user->phone,
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $audit->log(AuditAction::Login, $user, ['method' => 'oneid'], user: $user);

        // Revocations take effect at the next login at the latest (plus the daily re-check).
        if ($user->isAdvocate()) {
            $licenses->refresh($user);
        }

        return redirect()->intended($user->role === null ? route('onboarding.show') : route('dashboard'));
    }

    public function logout(Request $request, AuditLogger $audit): RedirectResponse
    {
        if ($user = $request->user()) {
            $audit->log(AuditAction::Logout, $user, user: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('oneid_error', $message);
    }
}

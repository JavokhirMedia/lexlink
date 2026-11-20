<?php

namespace App\Http\Controllers\MockGov;

use App\Http\Controllers\Controller;
use App\Models\Mock\MockOneidCode;
use App\Models\Mock\MockPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Mock OneID (OAuth2 authorization-code flow).
 * Product decision: "just one button" — authorize auto-approves when login_hint is given.
 */
class OneIdController extends Controller
{
    /** Demo persons shown first on the picker page. */
    public const DEMO_PINFLS = [
        '41505880261034', // Rustamova Dilnoza Akmalovna — advokat (faol litsenziya)
        '32208950261047', // Toshpulatov Jasur Baxtiyorovich — yurist
        '30311790261058', // Xolmatov Sherzod Ravshanovich — advokat (litsenziya to'xtatilgan)
        '42709920261069', // Abdullayeva Malika Farhodovna — administrator
    ];

    public function authorizeRequest(Request $request): RedirectResponse|View
    {
        $clientId = (string) $request->query('client_id', '');
        $redirectUri = (string) $request->query('redirect_uri', '');
        $state = (string) $request->query('state', '');

        if ($clientId !== (string) config('lexlink.mock_gov.oneid_client_id')) {
            abort(400, "Noma'lum client_id");
        }
        if (! filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            abort(400, "redirect_uri noto'g'ri");
        }

        $hint = (string) $request->query('login_hint', '');
        $person = $hint !== '' ? MockPerson::where('pinfl', $hint)->first() : null;

        if (! $person) {
            $demo = MockPerson::whereIn('pinfl', self::DEMO_PINFLS)->get()
                ->sortBy(fn (MockPerson $p) => array_search($p->pinfl, self::DEMO_PINFLS, true))->values();
            $others = MockPerson::whereNotIn('pinfl', self::DEMO_PINFLS)->orderBy('last_name')->limit(12)->get();

            return view('mock-gov.oneid', [
                'demo' => $demo,
                'others' => $others,
                'query' => ['client_id' => $clientId, 'redirect_uri' => $redirectUri, 'state' => $state],
                'error' => $hint !== '' ? 'Ushbu JShShIR bo\'yicha foydalanuvchi topilmadi.' : null,
            ]);
        }

        $code = MockOneidCode::create([
            'code' => Str::random(40),
            'pinfl' => $person->pinfl,
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->addMinutes(5),
        ]);

        $sep = str_contains($redirectUri, '?') ? '&' : '?';

        return redirect()->away($redirectUri.$sep.http_build_query(array_filter([
            'code' => $code->code,
            'state' => $state,
        ], fn ($v) => $v !== '')));
    }

    public function token(Request $request): JsonResponse
    {
        $clientId = (string) ($request->input('client_id') ?? $request->getUser() ?? '');
        $secret = (string) ($request->input('client_secret') ?? $request->getPassword() ?? '');

        if (! hash_equals((string) config('lexlink.mock_gov.oneid_client_id'), $clientId)
            || ! hash_equals((string) config('lexlink.mock_gov.oneid_client_secret'), $secret)) {
            return response()->json(['error' => 'invalid_client', 'error_description' => "Mijoz ma'lumotlari noto'g'ri"], 401);
        }

        if ($request->input('grant_type', 'authorization_code') !== 'authorization_code') {
            return response()->json(['error' => 'unsupported_grant_type'], 400);
        }

        $code = MockOneidCode::where('code', (string) $request->input('code'))->first();

        if (! $code || $code->access_token !== null || $code->expires_at->isPast()) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => "Avtorizatsiya kodi yaroqsiz yoki muddati o'tgan"], 400);
        }

        if ($request->filled('redirect_uri') && $request->input('redirect_uri') !== $code->redirect_uri) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'redirect_uri mos emas'], 400);
        }

        $code->update([
            'access_token' => Str::random(48),
            'expires_at' => now()->addHour(),
        ]);

        return response()->json([
            'access_token' => $code->access_token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'myportal',
        ]);
    }

    public function userinfo(Request $request): JsonResponse
    {
        $token = (string) $request->bearerToken();
        $code = $token !== '' ? MockOneidCode::where('access_token', $token)->first() : null;

        if (! $code || $code->expires_at->isPast()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $person = MockPerson::where('pinfl', $code->pinfl)->first();

        if (! $person) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return response()->json([
            'sub' => MockPerson::oneIdSubFor($person->pinfl),
            'pinfl' => $person->pinfl,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'middle_name' => $person->middle_name,
            'full_name' => $person->fullName(),
            'birth_date' => $person->birth_date->toDateString(),
            'phone' => $person->phone,
        ]);
    }
}

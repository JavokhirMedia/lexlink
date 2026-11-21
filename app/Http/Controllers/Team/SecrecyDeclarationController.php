<?php

namespace App\Http\Controllers\Team;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Per-case "advokat sirini saqlash majburiyati": a lawyer must accept it before seeing any
 * response of that case (in addition to the advocate's can_view_responses permission).
 */
class SecrecyDeclarationController extends Controller
{
    public function store(Request $request, LegalCase $case, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $request->validate(['accepted' => ['accepted']], ['accepted.accepted' => 'Majburiyatni qabul qilishingiz kerak.']);

        $membership = $case->membershipOf($request->user());

        abort_if($membership === null, 403, 'Siz ushbu ish a\'zosi emassiz.');

        if ($membership->secrecy_declared_at === null) {
            $membership->forceFill(['secrecy_declared_at' => now()])->save();

            $audit->log(AuditAction::SecrecyDeclared, $membership, [
                'role' => $membership->role,
            ], caseId: $case->id);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['secrecy_declared_at' => $membership->secrecy_declared_at->toIso8601String()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Advokat sirini saqlash majburiyati qabul qilindi']);

        return back();
    }
}

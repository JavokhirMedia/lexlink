<?php

namespace App\Http\Controllers\Client;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CaseClientLink;
use App\Services\AuditLogger;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * /client/access/{token}: the client signs in with OneID (intended URL is preserved by the `auth`
 * middleware) and gets read-only access ONLY if their OneID PINFL equals the order's client PINFL.
 * A brand-new OneID user (no role yet) becomes role `client` only through this path.
 */
class ClientAccessController extends Controller
{
    public function __invoke(Request $request, string $token, GovDataProvider $gov, AuditLogger $audit): Response
    {
        $user = $request->user();
        $link = CaseClientLink::with('legalCase')->where('token', $token)->first();

        if (! $link || ! $link->isActive() || ! $link->legalCase) {
            return self::denied($request, 'link_invalid', 'Havola yaroqsiz', "Bu havola mavjud emas yoki advokat tomonidan bekor qilingan. Advokatingizdan yangi havola so'rang.");
        }

        $deny = function (string $reason, string $title, string $message) use ($request, $link, $audit, $user) {
            $audit->log(AuditAction::ClientAccessDenied, $link, ['reason' => $reason, 'order_number' => $link->order_number], $link->case_id, $user);

            return self::denied($request, $reason, $title, $message);
        };

        if ($user->role !== null && $user->role !== UserRole::Client) {
            return $deny('role', 'Bu havola mijoz uchun', "Siz tizimda {$user->role->label()} sifatida ro'yxatdan o'tgansiz. Mijoz havolasi faqat advokat orderida ko'rsatilgan ishonch bildiruvchi uchun.");
        }

        if (! $user->pinfl_hash || ! hash_equals($link->client_pinfl_hash, $user->pinfl_hash)) {
            return $deny('pinfl_mismatch', 'Kirish rad etildi', "OneID orqali tasdiqlangan JShShIR'ingiz advokat orderida ko'rsatilgan ishonch bildiruvchi JShShIR'iga mos kelmadi. Ish ma'lumotlari faqat order bo'yicha mijozga ko'rsatiladi.");
        }

        // The order must still be active in the Advokatlar palatasi registry.
        try {
            $order = $gov->advocateOrder($link->order_number);
        } catch (GovApiException) {
            return self::denied($request, 'registry_unavailable', "Reyestr bilan aloqa yo'q", "Elektron orderlar reyestri vaqtincha javob bermayapti. Birozdan so'ng qayta urinib ko'ring.", 503);
        }
        if ($order === null || ($order['status'] ?? null) !== 'active') {
            return $deny('order_inactive', 'Order amal qilmaydi', "Advokat orderi {$link->order_number} reyestrda topilmadi yoki bekor qilingan — ish holatini ko'rish huquqi to'xtatilgan.");
        }

        if ($user->role === null) {
            $user->forceFill(['role' => UserRole::Client])->save();
        }

        $first = $link->granted_at === null;
        $link->forceFill([
            'client_user_id' => $user->id,
            'granted_at' => $link->granted_at ?? now(),
        ])->save();

        if ($first) {
            $audit->log(AuditAction::ClientAccessGranted, $link, ['order_number' => $link->order_number], $link->case_id, $user);
        }

        return redirect()->route('client.cases.show', $link->case_id);
    }

    public static function denied(Request $request, string $reason, string $title, string $message, int $status = 403): Response
    {
        return Inertia::render('client/denied', [
            'reason' => $reason,
            'title' => $title,
            'message' => $message,
        ])->toResponse($request)->setStatusCode($status);
    }
}

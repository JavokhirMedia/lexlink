import { Head, Link, router, usePoll } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";
import {
    Landmark,
    Lock,
    PenLine,
    ShieldCheck,
    TriangleAlert,
    XCircle,
} from "lucide-react";
import { useEffect, useState } from "react";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { formatMoney } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { StatusBadge } from "@/components/brand/status-badge";
import { SecrecyDeclarationDialog } from "@/components/legal/secrecy-declaration-dialog";
import { AiPanelSlot } from "@/components/requests/ai-panel-slot";
import { BasisCheckPanel } from "@/components/requests/basis-check-panel";
import { ResponseFullView } from "@/components/requests/response-full-view";
import { ResponseSummaryCard } from "@/components/requests/response-summary-card";
import { SignDialog } from "@/components/requests/sign-dialog";
import { StatusTimeline } from "@/components/requests/status-timeline";
import type {
    BasisCheck,
    Quote,
    RequestDetail,
    ResponseMeta,
    SignerKey,
    TimelineStep,
} from "@/components/requests/types";
import {
    formatDate,
    formatDateTime,
    formatMonth,
} from "@/components/requests/types";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { index as casesIndex, show as caseShow } from "@/routes/cases";
import { index as requestsIndex, requestSignature } from "@/routes/requests";
import type { AiAnalysis, RequestStatusUpdatedEvent } from "@/types/lexlink";

type Props = {
    request: RequestDetail;
    case: { id: number; title: string; court_case_number: string | null };
    timeline: TimelineStep[];
    basis_check: BasisCheck | null;
    permissions: {
        can_edit: boolean;
        can_request_signature: boolean;
        can_sign: boolean;
        can_view_response: boolean;
        needs_secrecy_declaration: boolean;
        is_admin: boolean;
        is_lawyer: boolean;
    };
    quote: Quote | null;
    signer_key: SignerKey | null;
    open_sign: boolean;
    response: ResponseMeta | null;
    ai_analysis: AiAnalysis | null;
};

const LIVE_PROPS = [
    "request",
    "timeline",
    "basis_check",
    "response",
    "ai_analysis",
    "permissions",
];

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm break-words">{children}</dd>
        </div>
    );
}

export default function RequestShow(props: Props) {
    const {
        request: r,
        case: legalCase,
        timeline,
        basis_check,
        permissions,
        quote,
        signer_key,
        open_sign,
        response,
        ai_analysis,
    } = props;
    const [signOpen, setSignOpen] = useState(open_sign);
    const [secrecyOpen, setSecrecyOpen] = useState(false);
    const pending = r.status === "submitted" || r.status === "processing";

    useEcho<RequestStatusUpdatedEvent>(
        `case.${r.case_id}`,
        ".RequestStatusUpdated",
        (e) => {
            if (e.id === r.id) {
                router.reload({ only: LIVE_PROPS });
            }
        },
        [r.id],
    );

    // Fallback if websockets are unavailable: poll while the agency is working.
    const { start, stop } = usePoll(
        3000,
        { only: LIVE_PROPS },
        { autoStart: false },
    );
    useEffect(() => {
        if (pending) {
            start();
        } else {
            stop();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pending]);

    const params = r.params ?? {};
    const blocked = basis_check && !basis_check.ok;

    return (
        <>
            <Head title={`So'rov #${r.id}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${r.type_label} — so'rov #${r.id}`}
                    description={
                        <>
                            Ish:{" "}
                            <Link
                                href={caseShow.url(legalCase.id)}
                                className="font-medium text-foreground hover:underline"
                            >
                                {legalCase.title}
                            </Link>
                            {legalCase.court_case_number
                                ? ` · ${legalCase.court_case_number}`
                                : ""}
                        </>
                    }
                    meta={
                        <>
                            <StatusBadge
                                status={r.status}
                                label={r.status_label}
                            />
                            <span className="inline-flex items-center gap-1">
                                <Landmark className="size-3.5" aria-hidden />
                                {r.agency}
                            </span>
                            {r.gov_request_id && (
                                <span className="font-mono">
                                    {r.gov_request_id}
                                </span>
                            )}
                        </>
                    }
                    actions={
                        <>
                            {permissions.can_request_signature && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            requestSignature.url(r.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <PenLine />
                                    Advokat imzosiga yuborish
                                </Button>
                            )}
                            {permissions.can_sign && signer_key && (
                                <Button
                                    onClick={() => setSignOpen(true)}
                                    disabled={!!blocked}
                                    title={
                                        blocked
                                            ? (basis_check?.reason ?? "")
                                            : undefined
                                    }
                                >
                                    <ShieldCheck />
                                    Imzolash va yuborish
                                </Button>
                            )}
                        </>
                    }
                />

                <Card>
                    <CardContent className="pt-6">
                        <StatusTimeline steps={timeline} />
                    </CardContent>
                </Card>

                {r.status === "rejected" && (
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-md border border-danger-border bg-danger-soft p-4 text-sm"
                    >
                        <XCircle
                            className="mt-0.5 size-5 shrink-0 text-danger"
                            aria-hidden
                        />
                        <div>
                            <p className="font-medium text-danger">
                                Idora so'rovni rad etdi
                            </p>
                            <p>{r.rejection_reason}</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                To'langan mablag' (yoki obuna limiti)
                                qaytarildi.
                            </p>
                        </div>
                    </div>
                )}
                {r.status === "failed" && (
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-md border border-danger-border bg-danger-soft p-4 text-sm"
                    >
                        <TriangleAlert
                            className="mt-0.5 size-5 shrink-0 text-danger"
                            aria-hidden
                        />
                        <div>
                            <p className="font-medium text-danger">
                                Texnik xato
                            </p>
                            <p>{r.failure_reason}</p>
                        </div>
                    </div>
                )}

                {(r.status === "draft" || r.status === "awaiting_signature") &&
                    basis_check && (
                        <div className="space-y-2">
                            <BasisCheckPanel check={basis_check} />
                            {r.status === "awaiting_signature" &&
                                !permissions.can_sign && (
                                    <p className="text-sm text-muted-foreground">
                                        So'rov advokat imzosini kutmoqda.
                                        Imzolangach idoraga avtomatik
                                        yuboriladi.
                                    </p>
                                )}
                        </div>
                    )}

                {response && (
                    <div className="space-y-4">
                        <ResponseSummaryCard response={response} />

                        {permissions.is_admin ? (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Lock className="size-4" aria-hidden />
                                Administrator faqat metama'lumotlarni ko'radi —
                                javob mazmuni advokat siri.
                            </p>
                        ) : permissions.needs_secrecy_declaration ? (
                            <Card>
                                <CardContent className="flex flex-col items-start gap-3 pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
                                    <p className="flex items-start gap-2">
                                        <Lock
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden
                                        />
                                        Javobni ko'rish uchun ushbu ish bo'yicha
                                        advokat sirini saqlash majburiyatini
                                        qabul qiling.
                                    </p>
                                    <Button
                                        onClick={() => setSecrecyOpen(true)}
                                    >
                                        Majburiyatni qabul qilish
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : !permissions.can_view_response ? (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Lock className="size-4" aria-hidden />
                                Advokat sizga ushbu ish bo'yicha javoblarni
                                ko'rish ruxsatini bermagan.
                            </p>
                        ) : !response.purged ? (
                            <ResponseFullView requestId={r.id} type={r.type} />
                        ) : null}

                        {permissions.can_view_response && (
                            <AiPanelSlot
                                requestId={r.id}
                                analysis={ai_analysis}
                            />
                        )}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>So'rov matni</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {r.purpose && (
                                <p className="text-sm">
                                    <span className="text-muted-foreground">
                                        Maqsad:{" "}
                                    </span>
                                    {r.purpose}
                                </p>
                            )}
                            <div className="rounded-md border bg-muted/30 p-4 font-serif text-sm leading-relaxed whitespace-pre-wrap">
                                {r.request_text || (
                                    <span className="text-muted-foreground">
                                        Matn kiritilmagan
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rekvizitlar</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3">
                                <Field label="Shaxs">
                                    {r.subject_name ?? "—"}{" "}
                                    <MaskedPinfl
                                        value={r.subject_pinfl_masked}
                                    />
                                </Field>
                                {r.type === "salary" && (
                                    <Field label="Davr">
                                        {formatMonth(
                                            params.period_from as string,
                                        )}{" "}
                                        —{" "}
                                        {formatMonth(
                                            params.period_to as string,
                                        )}
                                    </Field>
                                )}
                                {typeof params.cadastral_number ===
                                    "string" && (
                                    <Field label="Kadastr raqami">
                                        {params.cadastral_number}
                                    </Field>
                                )}
                                {typeof params.court_case_number ===
                                    "string" && (
                                    <Field label="Sud ishi raqami">
                                        {params.court_case_number}
                                    </Field>
                                )}
                                <Field label="Huquqiy asos">
                                    {r.legal_basis ? (
                                        <>
                                            {r.legal_basis.type_label} №{" "}
                                            {r.legal_basis.number}
                                            {r.legal_basis.expires_at && (
                                                <span className="text-muted-foreground">
                                                    {" "}
                                                    ·{" "}
                                                    {formatDate(
                                                        r.legal_basis
                                                            .expires_at,
                                                    )}{" "}
                                                    gacha
                                                </span>
                                            )}
                                        </>
                                    ) : (
                                        "—"
                                    )}
                                </Field>
                                <Field label="Maxfiylik rejimi">
                                    {r.secrecy_regime}
                                </Field>
                                <Field label="Tayyorladi">
                                    {r.creator?.name ?? "—"}
                                </Field>
                                <Field label="Imzoladi (ERI, demo)">
                                    {r.signer ? (
                                        <>
                                            {r.signer.name}
                                            {r.signer.license_number && (
                                                <span className="text-muted-foreground">
                                                    {" "}
                                                    · {r.signer.license_number}
                                                </span>
                                            )}
                                            <br />
                                            <span className="text-xs text-muted-foreground">
                                                {formatDateTime(r.signed_at)}
                                            </span>
                                            {r.signature && (
                                                <span className="block font-mono text-[11px] break-all text-muted-foreground">
                                                    {r.signature.slice(0, 32)}…
                                                </span>
                                            )}
                                        </>
                                    ) : (
                                        "—"
                                    )}
                                </Field>
                                {r.signed_at && (
                                    <Field label="To'lov">
                                        {r.charged_from_subscription
                                            ? "Obuna doirasida"
                                            : formatMoney(r.cost)}
                                    </Field>
                                )}
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {permissions.can_sign && signer_key && (
                <SignDialog
                    requestId={r.id}
                    signerKey={signer_key}
                    quote={quote}
                    open={signOpen}
                    onOpenChange={setSignOpen}
                />
            )}
            {permissions.needs_secrecy_declaration && (
                <SecrecyDeclarationDialog
                    caseId={legalCase.id}
                    open={secrecyOpen}
                    onOpenChange={setSecrecyOpen}
                    onAccepted={() => {
                        setSecrecyOpen(false);
                        router.reload({ only: LIVE_PROPS });
                    }}
                />
            )}
        </>
    );
}

RequestShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: props.case.title, href: caseShow(props.case.id) },
        { title: `So'rov #${props.request.id}`, href: requestsIndex() },
    ],
});

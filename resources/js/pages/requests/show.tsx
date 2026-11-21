import { normalizeApostrophes } from '@/lib/utils';
import { Head, Link, router, usePoll } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";
import {
    Archive,
    FileLock,
    Landmark,
    Lock,
    PenLine,
    ShieldCheck,
    TriangleAlert,
    XCircle,
} from "lucide-react";
import type { ReactNode } from "react";
import { useEffect, useState } from "react";
import { formatMoney } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import { StatusBadge } from "@/components/brand/status-badge";
import { SecrecyDeclarationDialog } from "@/components/legal/secrecy-declaration-dialog";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { AiPanelSlot } from "@/components/requests/ai-panel-slot";
import { BasisCheckPanel } from "@/components/requests/basis-check-panel";
import { ResponseFullView } from "@/components/requests/response-full-view";
import { ResponseLocked } from "@/components/requests/response-locked";
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
import { index as auditIndex } from "@/routes/audit";
import { index as casesIndex, show as caseShow } from "@/routes/cases";
import { index as requestsIndex, requestSignature } from "@/routes/requests";
import type {
    AiAnalysis,
    RequestDeadlineFields,
    RequestStatusUpdatedEvent,
} from "@/types/lexlink";

type Props = {
    request: RequestDetail & Partial<RequestDeadlineFields>;
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

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1 py-3 first:pt-0 last:pb-0">
            <dt className="text-meta text-ink-secondary">{label}</dt>
            <dd className="text-sm break-words text-ink">{children}</dd>
        </div>
    );
}

function Alert({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof XCircle;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div
            role="alert"
            className="flex items-start gap-3 rounded-lg border border-danger-border bg-danger-soft px-4 py-3 text-sm"
        >
            <Icon className="mt-0.5 size-[18px] shrink-0 text-danger" aria-hidden />
            <div className="flex flex-col gap-1">
                <p className="font-medium text-danger">{title}</p>
                {children}
            </div>
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
    const basisKind =
        r.legal_basis?.type === "advocate_order" ? "order" : "decision";

    const caseLink = (
        <Button variant="outline" size="sm" asChild>
            <Link href={caseShow.url(legalCase.id)}>Ishga qaytish</Link>
        </Button>
    );

    let responseBody: ReactNode = null;

    if (response) {
        if (permissions.is_admin) {
            responseBody = (
                <ResponseLocked
                    icon={Lock}
                    title="Administrator faqat metama'lumotlarni ko'radi"
                    description="Javob mazmuni advokat siri hisoblanadi"
                    action={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={auditIndex.url()}>Audit jurnali</Link>
                        </Button>
                    }
                />
            );
        } else if (permissions.needs_secrecy_declaration) {
            responseBody = (
                <ResponseLocked
                    icon={FileLock}
                    title="Javobni ko'rish uchun majburiyat kerak"
                    description="Ushbu ish bo'yicha advokat sirini saqlash majburiyatini qabul qiling"
                    action={
                        <Button onClick={() => setSecrecyOpen(true)}>
                            Majburiyatni qabul qilish
                        </Button>
                    }
                />
            );
        } else if (!permissions.can_view_response) {
            responseBody = (
                <ResponseLocked
                    icon={Lock}
                    title="Javoblarni ko'rish ruxsati berilmagan"
                    description="Advokat sizga ushbu ish bo'yicha javoblarni ko'rish ruxsatini bermagan"
                    action={caseLink}
                />
            );
        } else if (response.purged) {
            responseBody = (
                <ResponseLocked
                    icon={Archive}
                    title="To'liq javob saqlash muddati tugagani uchun o'chirilgan"
                    description={
                        response.purged_at
                            ? `O'chirildi ${formatDateTime(response.purged_at)}. Qisqacha ma'lumot saqlanadi`
                            : "Qisqacha ma'lumot saqlanadi"
                    }
                    action={caseLink}
                />
            );
        } else {
            responseBody = (
                <ResponseFullView requestId={r.id} type={r.type} />
            );
        }
    }

    const showFullHeading =
        response && !(permissions.can_view_response && !response.purged);

    return (
        <>
            <Head title={`So'rov #${r.id}`} />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title={`${r.type_label} — so'rov #${r.id}`}
                    description={
                        <>
                            Ish:{" "}
                            <Link
                                href={caseShow.url(legalCase.id)}
                                className="focus-ring rounded-xs text-ink underline decoration-hairline underline-offset-4 transition-colors hover:decoration-ink"
                            >
                                {legalCase.title}
                            </Link>
                        </>
                    }
                    meta={
                        <>
                            <StatusBadge
                                status={r.status}
                                label={r.status_label}
                            />
                            {r.gov_request_id && (
                                <RegistryChip
                                    value={r.gov_request_id}
                                    kind="gov"
                                    copyable
                                />
                            )}
                            <span className="inline-flex items-center gap-1.5">
                                <Landmark
                                    className="size-4 text-ink-muted"
                                    strokeWidth={1.5}
                                    aria-hidden
                                />
                                {r.agency}
                            </span>
                            {r.due_at && (
                                <DeadlineChip
                                    dueAt={r.due_at}
                                    status={r.status}
                                    deadlineLabel={r.deadline_label}
                                    answeredOnTime={r.answered_on_time}
                                    compact
                                />
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

                <section
                    aria-labelledby="timeline-title"
                    className="rounded-xl border bg-card p-6 shadow-xs"
                >
                    <div className="mb-6 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2
                            id="timeline-title"
                            className="text-base font-medium text-ink"
                        >
                            So'rov bosqichlari
                        </h2>
                        {r.deadline_label && (
                            <p className="text-meta text-ink-secondary">
                                {r.deadline_label}
                            </p>
                        )}
                    </div>
                    <StatusTimeline
                        steps={timeline}
                        signer={r.signer}
                        govRequestId={r.gov_request_id}
                    />
                </section>

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                    <div className="flex min-w-0 flex-col gap-10">
                        {r.status === "rejected" && (
                            <Alert icon={XCircle} title="Idora so'rovni rad etdi">
                                <p className="text-ink">{r.rejection_reason}</p>
                                <p className="text-meta text-ink-secondary">
                                    To'langan mablag' (yoki obuna limiti)
                                    qaytarildi
                                </p>
                            </Alert>
                        )}
                        {r.status === "failed" && (
                            <Alert icon={TriangleAlert} title="Texnik xato">
                                <p className="text-ink">{r.failure_reason}</p>
                            </Alert>
                        )}

                        {(r.status === "draft" ||
                            r.status === "awaiting_signature") &&
                            basis_check && (
                                <div className="flex flex-col gap-3">
                                    <BasisCheckPanel check={basis_check} />
                                    {r.status === "awaiting_signature" &&
                                        !permissions.can_sign && (
                                            <p className="text-sm text-ink-secondary">
                                                So'rov advokat imzosini
                                                kutmoqda. Imzolangach idoraga
                                                avtomatik yuboriladi.
                                            </p>
                                        )}
                                </div>
                            )}

                        {response && <ResponseSummaryCard response={response} />}

                        {response &&
                            (showFullHeading ? (
                                <Section title="To'liq javob">
                                    {responseBody}
                                </Section>
                            ) : (
                                responseBody
                            ))}

                        {response && permissions.can_view_response && (
                            <AiPanelSlot
                                requestId={r.id}
                                analysis={ai_analysis}
                            />
                        )}

                        <Section title="So'rov matni">
                            <article className="rounded-lg border bg-card shadow-xs">
                                <header className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b px-6 py-3 text-meta text-ink-secondary sm:px-10">
                                    <span>
                                        Advokat so'rovi #{r.id} · {r.agency}
                                    </span>
                                    {r.signed_at && (
                                        <span className="tabular-nums">
                                            {formatDate(r.signed_at)}
                                        </span>
                                    )}
                                </header>
                                <div className="flex flex-col gap-4 px-6 py-6 sm:px-10 sm:py-8">
                                    {r.purpose && (
                                        <p className="max-w-[68ch] text-sm text-ink-secondary">
                                            Maqsad:{" "}
                                            <span className="text-ink">
                                                {r.purpose}
                                            </span>
                                        </p>
                                    )}
                                    {r.request_text ? (
                                        <p className="max-w-[68ch] text-base leading-[26px] whitespace-pre-wrap text-ink">
                                            {normalizeApostrophes(r.request_text)}
                                        </p>
                                    ) : (
                                        <p className="text-sm text-ink-muted">
                                            Matn kiritilmagan
                                        </p>
                                    )}
                                </div>
                            </article>
                        </Section>
                    </div>

                    <aside
                        aria-labelledby="requisites-title"
                        className="rounded-lg border bg-card p-6 shadow-xs lg:sticky lg:top-[96px]"
                    >
                        <h2
                            id="requisites-title"
                            className="mb-4 text-base font-medium text-ink"
                        >
                            Rekvizitlar
                        </h2>
                        <dl className="flex flex-col divide-y divide-hairline">
                            <Field label="Shaxs">
                                <span className="flex flex-col items-start gap-1.5">
                                    {r.subject_name ?? "—"}
                                    {r.subject_pinfl_masked && (
                                        <RegistryChip
                                            value={r.subject_pinfl_masked}
                                            kind="pinfl"
                                            size="sm"
                                        />
                                    )}
                                </span>
                            </Field>
                            {typeof params.period_from === "string" && (
                                <Field label="Davr">
                                    {formatMonth(params.period_from as string)}{" "}
                                    — {formatMonth(params.period_to as string)}
                                </Field>
                            )}
                            {typeof params.cadastral_number === "string" && (
                                <Field label="Kadastr raqami">
                                    <span className="font-mono text-meta">
                                        {params.cadastral_number}
                                    </span>
                                </Field>
                            )}
                            {typeof params.court_case_number === "string" && (
                                <Field label="Sud ishi raqami">
                                    <RegistryChip
                                        value={params.court_case_number}
                                        kind="case"
                                        size="sm"
                                    />
                                </Field>
                            )}
                            <Field label="Huquqiy asos">
                                {r.legal_basis ? (
                                    <span className="flex flex-col items-start gap-1.5">
                                        {r.legal_basis.type_label}
                                        <RegistryChip
                                            value={r.legal_basis.number}
                                            kind={basisKind}
                                            size="sm"
                                        />
                                        {r.legal_basis.expires_at && (
                                            <span className="text-meta text-ink-secondary">
                                                {formatDate(
                                                    r.legal_basis.expires_at,
                                                )}{" "}
                                                gacha amal qiladi
                                            </span>
                                        )}
                                    </span>
                                ) : (
                                    "—"
                                )}
                            </Field>
                            {legalCase.court_case_number && (
                                <Field label="Ish raqami">
                                    <RegistryChip
                                        value={legalCase.court_case_number}
                                        kind="case"
                                        size="sm"
                                    />
                                </Field>
                            )}
                            <Field label="Maxfiylik rejimi">
                                {r.secrecy_regime}
                            </Field>
                            <Field label="Tayyorladi">
                                {r.creator?.name ?? "—"}
                            </Field>
                            <Field label="Imzoladi (ERI, demo)">
                                {r.signer ? (
                                    <span className="flex flex-col items-start gap-1.5">
                                        {r.signer.name}
                                        {r.signer.license_number && (
                                            <RegistryChip
                                                value={r.signer.license_number}
                                                kind="license"
                                                size="sm"
                                            />
                                        )}
                                        <span className="text-meta text-ink-secondary tabular-nums">
                                            {formatDateTime(r.signed_at)}
                                        </span>
                                        {r.signature && (
                                            <span
                                                className="max-w-full truncate font-mono text-meta text-ink-muted"
                                                title={r.signature}
                                            >
                                                {r.signature.slice(0, 32)}…
                                            </span>
                                        )}
                                    </span>
                                ) : (
                                    "—"
                                )}
                            </Field>
                            {r.signed_at && (
                                <Field label="To'lov">
                                    <span className="tabular-nums">
                                        {r.charged_from_subscription
                                            ? "Obuna doirasida"
                                            : formatMoney(r.cost)}
                                    </span>
                                </Field>
                            )}
                        </dl>
                    </aside>
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

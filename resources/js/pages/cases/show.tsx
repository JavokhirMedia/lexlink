import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    Check,
    Copy,
    FilePlus2,
    FileText,
    Gavel,
    History,
    Link2,
    Plus,
    RefreshCw,
    ScrollText,
    ShieldCheck,
    Trash2,
    UserPlus,
} from "lucide-react";
import { useState } from "react";
import type { FormEvent, ReactNode } from "react";
import { EmptyState } from "@/components/brand/empty-state";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { formatDate } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import InputError from "@/components/input-error";
import { BasisSeal } from "@/components/requests/basis-check-panel";
import type { SealState } from "@/components/requests/basis-check-panel";
import { CaseRequestsPanel } from "@/components/requests/case-requests-panel";
import { RequestTypeIcon } from "@/components/requests/request-type-icon";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { index as casesIndex, show as casesShow } from "@/routes/cases";
import advocateOrdersRoutes from "@/routes/cases/advocate-orders";
import basesRoutes from "@/routes/cases/bases";
import clientLinksRoutes from "@/routes/cases/client-links";
import membersRoutes from "@/routes/cases/members";
import secrecyDeclaration from "@/routes/cases/secrecy-declaration";
import { create as requestCreate } from "@/routes/requests";
import type {
    DataRequest,
    DataRequestType,
    LegalBasis,
    LegalBasisType,
    LegalCase,
} from "@/types/lexlink";

type CaseDetail = LegalCase & {
    category_label: string | null;
    parties: { role: string; name: string }[];
};

type BasisDetail = LegalBasis & {
    allowed_type_labels: string[];
    not_covered_types: { value: DataRequestType; label: string }[];
    possible_types: DataRequestType[];
    is_expired: boolean;
    verification_error: string | null;
    has_file: boolean;
    created_by: { id: number; name: string } | null;
    purpose: string | null;
    order: {
        client_name: string | null;
        client_pinfl_masked: string | null;
        matter: string | null;
        license_number: string | null;
        advocate_name: string | null;
        status: string | null;
        client_known: boolean;
    } | null;
};

type OrderTypeInfo = {
    value: DataRequestType;
    label: string;
    why: string | null;
};

type OrderMatrix = {
    order: OrderTypeInfo[];
    court_only: OrderTypeInfo[];
    deadlines: {
        default_days: number;
        no_info_notice_days: number;
        proceedings_work_days: number;
    };
    law_ref: string;
};

type IssuedOrder = {
    order_number: string;
    client_name: string;
    matter: string;
    issued_at: string | null;
};

type ClientLink = {
    id: number;
    order_number: string;
    client_name: string | null;
    url: string | null;
    created_at: string | null;
    granted_at: string | null;
    last_viewed_at: string | null;
    revoked_at: string | null;
};

type Member = {
    id: number;
    user: {
        id: number;
        name: string;
        role: string | null;
        role_label: string | null;
    };
    role: "advocate" | "lawyer";
    can_view_responses: boolean;
    secrecy_declared_at: string | null;
};

type CoverageEntry = {
    basis_id: number;
    basis_type: LegalBasisType;
    number: string;
    subject_pinfl_masked: string | null;
    subject_name: string | null;
    expires_at: string | null;
};

type RequestTypeInfo = {
    value: DataRequestType;
    label: string;
    accepted_bases: LegalBasisType[];
    secrecy_regime: string;
};

type Props = {
    case: CaseDetail;
    members: Member[];
    membership: {
        role: "advocate" | "lawyer";
        can_view_responses: boolean;
        secrecy_declared_at: string | null;
    };
    can: {
        create_request: boolean;
        sign: boolean;
        manage_members: boolean;
        add_basis: boolean;
        view_responses: boolean;
    };
    requests: DataRequest[];
    bases: BasisDetail[];
    coverage: Record<DataRequestType, CoverageEntry[]>;
    available_lawyers: { id: number; name: string; email: string | null }[];
    request_types: RequestTypeInfo[];
    advocate_order_types: DataRequestType[];
    order_matrix: OrderMatrix;
    client_links: ClientLink[];
};

type Tab = "bases" | "requests" | "team" | "client";

const fmtDate = (v: string | null) => formatDate(v);

/** Seal state + label for each verification status of a basis. */
const BASIS_STATE: Record<
    LegalBasis["verification_status"],
    { seal: SealState; label: string; tone: string }
> = {
    verified: { seal: "verified", label: "Tasdiqlangan", tone: "text-success" },
    pending: { seal: "pending", label: "Tekshirilmoqda", tone: "text-warning" },
    not_found: {
        seal: "blocked",
        label: "Reyestrda topilmadi",
        tone: "text-danger",
    },
    expired: { seal: "blocked", label: "Muddati tugagan", tone: "text-danger" },
    mismatch: { seal: "blocked", label: "Mos emas", tone: "text-danger" },
};

export default function CaseShow(props: Props) {
    const {
        case: legalCase,
        bases,
        requests,
        membership,
        can,
        members,
        client_links,
    } = props;
    const [tab, setTab] = useState<Tab>(
        bases.length === 0 && can.add_basis ? "bases" : "requests",
    );
    const activeLinks = client_links.filter((l) => !l.revoked_at).length;

    return (
        <>
            <Head title={legalCase.title} />
            <div className="flex w-full flex-col gap-8">
                <CaseHeader
                    legalCase={legalCase}
                    canViewResponses={can.view_responses}
                    canCreateRequest={can.create_request}
                />

                {membership.role === "lawyer" &&
                    !membership.secrecy_declared_at && (
                        <SecrecyDeclaration caseId={legalCase.id} />
                    )}

                <Tabs
                    value={tab}
                    onValueChange={(v) => setTab(v as Tab)}
                    className="gap-8"
                >
                    <TabsList aria-label="Ish bo'limlari">
                        <TabsTrigger value="bases" count={bases.length}>
                            Huquqiy asoslar
                        </TabsTrigger>
                        <TabsTrigger value="requests" count={requests.length}>
                            So'rovlar
                        </TabsTrigger>
                        <TabsTrigger value="team" count={members.length}>
                            Jamoa
                        </TabsTrigger>
                        {can.add_basis && (
                            <TabsTrigger value="client" count={activeLinks}>
                                Mijoz kirishi
                            </TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="bases">
                        <BasesSection {...props} />
                    </TabsContent>
                    <TabsContent value="requests">
                        <CaseRequestsPanel
                            caseId={legalCase.id}
                            requests={requests}
                            canCreate={can.create_request}
                        />
                    </TabsContent>
                    <TabsContent value="team">
                        <TeamSection {...props} />
                    </TabsContent>
                    {can.add_basis && (
                        <TabsContent value="client">
                            <ClientAccessSection
                                caseId={legalCase.id}
                                bases={bases}
                                links={client_links}
                            />
                        </TabsContent>
                    )}
                </Tabs>
            </div>
        </>
    );
}

CaseShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: props.case.title, href: casesShow(props.case.id) },
    ],
});

function CaseHeader({
    legalCase,
    canViewResponses,
    canCreateRequest,
}: {
    legalCase: CaseDetail;
    canViewResponses: boolean;
    canCreateRequest: boolean;
}) {
    const closed = legalCase.status === "closed";

    return (
        <PageHeader
            title={legalCase.title}
            description={legalCase.description ?? undefined}
            meta={
                <>
                    <span className="inline-flex items-center gap-1.5 text-ink">
                        <span
                            className={cn(
                                "size-1.5 rounded-full",
                                closed ? "bg-ink-muted" : "bg-success",
                            )}
                            aria-hidden
                        />
                        {closed ? "Yopilgan" : "Faol"}
                    </span>
                    <MetaDivider />
                    <span>
                        Mijoz{" "}
                        <span className="text-ink">
                            {legalCase.client_name}
                        </span>
                    </span>
                    {legalCase.court_case_number && (
                        <>
                            <MetaDivider />
                            <RegistryChip
                                value={legalCase.court_case_number}
                                kind="case"
                                copyable
                            />
                        </>
                    )}
                    {legalCase.category_label && (
                        <>
                            <MetaDivider />
                            <span>{legalCase.category_label}</span>
                        </>
                    )}
                    {legalCase.parties.length > 0 && (
                        <span className="flex basis-full flex-wrap gap-x-4 gap-y-1 pt-1">
                            {legalCase.parties.map((p, i) => (
                                <span key={i}>
                                    {p.role}{" "}
                                    <span className="text-ink">{p.name}</span>
                                </span>
                            ))}
                        </span>
                    )}
                </>
            }
            actions={
                <>
                    {canViewResponses && (
                        <Button asChild variant="outline">
                            <a href={`/cases/${legalCase.id}/claim`}>
                                <FileText /> Da'vo arizasi (AI)
                            </a>
                        </Button>
                    )}
                    <Button asChild variant="outline">
                        <a href={`/audit?case_id=${legalCase.id}`}>
                            <History /> Audit jurnali
                        </a>
                    </Button>
                    {canCreateRequest && (
                        <Button asChild>
                            <Link
                                href={requestCreate.url({
                                    query: { case: legalCase.id },
                                })}
                            >
                                <FilePlus2 /> Yangi so'rov
                            </Link>
                        </Button>
                    )}
                </>
            }
        />
    );
}

function MetaDivider() {
    return (
        <span aria-hidden className="h-4 w-px bg-hairline" />
    );
}

function SecrecyDeclaration({ caseId }: { caseId: number }) {
    const [checked, setChecked] = useState(false);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(
            secrecyDeclaration.store.url(caseId),
            { accepted: true },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <section
            role="alert"
            className="flex flex-col gap-4 rounded-xl border border-warning-border bg-warning-soft p-5 sm:flex-row sm:items-start"
        >
            <ShieldCheck
                className="size-5 shrink-0 text-warning"
                strokeWidth={1.5}
                aria-hidden
            />
            <div className="flex flex-1 flex-col gap-3">
                <div className="flex flex-col gap-1">
                    <h2 className="text-base font-medium text-ink">
                        Advokat sirini saqlash majburiyati
                    </h2>
                    <p className="text-sm text-ink-secondary">
                        Ushbu ish bo'yicha idoralardan olingan javoblarni
                        ko'rish uchun advokat sirini saqlash majburiyatini
                        qabul qilishingiz kerak. Javoblarni ko'rish ruxsatini
                        advokat alohida beradi.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-4">
                    <label className="flex items-center gap-3 text-sm text-ink">
                        <Checkbox
                            checked={checked}
                            onCheckedChange={(v) => setChecked(v === true)}
                        />
                        Advokat sirini oshkor qilmaslik majburiyatini olaman
                    </label>
                    <Button
                        size="sm"
                        disabled={!checked || processing}
                        onClick={submit}
                    >
                        Qabul qilish
                    </Button>
                </div>
            </div>
        </section>
    );
}

/* ---------------------------------------------------------------- Bases */

function BasesSection({
    case: legalCase,
    bases,
    can,
    coverage,
    request_types,
    order_matrix,
}: Props) {
    const [adding, setAdding] = useState(false);

    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <Section
                title="Huquqiy asoslar"
                description="Asossiz so'rov yuborilmaydi: har bir so'rov tasdiqlangan, amaldagi asos bilan qoplanishi shart"
                actions={
                    can.add_basis &&
                    !adding &&
                    bases.length > 0 && (
                        <Button variant="outline" onClick={() => setAdding(true)}>
                            <Plus /> Asos qo'shish
                        </Button>
                    )
                }
                contentClassName="flex flex-col gap-6"
            >
                {!can.add_basis && (
                    <p className="text-sm text-ink-secondary">
                        Huquqiy asosni faqat faol litsenziyaga ega advokat
                        qo'shadi
                    </p>
                )}

                {adding && (
                    <AddBasisForm
                        caseId={legalCase.id}
                        legalCase={legalCase}
                        orderMatrix={order_matrix}
                        onDone={() => setAdding(false)}
                    />
                )}

                {bases.length === 0 && !adding ? (
                    <EmptyState
                        icon={Gavel}
                        title="Huquqiy asos hali qo'shilmagan"
                        description="Advokat orderi ko'chmas mulk, avtotransport, sud ishi, soliq (ochiq qism) va ulushlar uchun yetarli. Ish haqi, bank siri va chegaradan o'tish uchun sud qarori kerak"
                        action={
                            can.add_basis ? (
                                <Button onClick={() => setAdding(true)}>
                                    <Plus /> Asos qo'shish
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    bases.map((b) => (
                        <BasisCard
                            key={b.id}
                            caseId={legalCase.id}
                            basis={b}
                            canVerify={can.add_basis}
                            courtOnly={order_matrix.court_only}
                        />
                    ))
                )}
            </Section>

            <CoveragePanel coverage={coverage} requestTypes={request_types} />
        </div>
    );
}

/**
 * A legal basis as an official document: type and registry number on top,
 * a seal with the verification verdict on the right, the facts in a grid,
 * then what it covers and what it does not.
 */
function BasisCard({
    caseId,
    basis,
    canVerify,
    courtOnly,
}: {
    caseId: number;
    basis: BasisDetail;
    canVerify: boolean;
    courtOnly: OrderTypeInfo[];
}) {
    const [verifying, setVerifying] = useState(false);
    const isCourt = basis.type === "court_decision";
    const state = basis.is_expired
        ? BASIS_STATE.expired
        : BASIS_STATE[basis.verification_status];
    const verified = basis.verification_status === "verified" && !basis.is_expired;

    const reverify = () => {
        setVerifying(true);
        router.post(
            basesRoutes.verify.url({ legalCase: caseId, basis: basis.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerifying(false),
            },
        );
    };

    return (
        <article
            data-basis={basis.verification_status}
            className="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <header className="flex items-start justify-between gap-4 border-b px-5 py-5 sm:px-6">
                <div className="flex min-w-0 flex-col gap-2">
                    <span className="flex items-center gap-2 text-sm text-ink-secondary">
                        {isCourt ? (
                            <Gavel
                                className="size-4 shrink-0"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                        ) : (
                            <ScrollText
                                className="size-4 shrink-0"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                        )}
                        {basis.type_label}
                    </span>
                    <RegistryChip
                        value={basis.number}
                        kind={isCourt ? "decision" : "order"}
                        copyable
                        className="h-8 px-2.5 text-base"
                    />
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    <div className="hidden flex-col items-end text-right sm:flex">
                        <span
                            className={cn(
                                "text-sm font-medium",
                                state.tone,
                            )}
                        >
                            {state.label}
                        </span>
                        {verified && basis.verified_at && (
                            <span className="text-xs text-ink-muted tabular-nums">
                                {fmtDate(basis.verified_at)}
                            </span>
                        )}
                    </div>
                    <BasisSeal state={state.seal} className="size-14" />
                </div>
            </header>

            <div className="flex flex-col gap-6 px-5 py-5 sm:px-6">
                <span
                    className={cn(
                        "-mt-1 text-sm font-medium sm:hidden",
                        state.tone,
                    )}
                >
                    {state.label}
                </span>

                {basis.verification_error && !verified && (
                    <p className="rounded-lg border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger">
                        {basis.verification_error}
                    </p>
                )}

                <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                    <Fact label={isCourt ? "Sud" : "Bergan advokat"}>
                        {isCourt
                            ? (basis.court_name ?? "—")
                            : (basis.order?.advocate_name ??
                              basis.created_by?.name ??
                              "—")}
                    </Fact>
                    <Fact label="Subyekt">
                        <span className="flex flex-col gap-1">
                            <span>{basis.subject_name ?? "—"}</span>
                            {basis.subject_pinfl_masked && (
                                <RegistryChip
                                    value={basis.subject_pinfl_masked}
                                    kind="pinfl"
                                    size="sm"
                                />
                            )}
                        </span>
                    </Fact>
                    <Fact label="Amal qilish muddati">
                        <span
                            className={cn(
                                "tabular-nums",
                                basis.is_expired && "text-danger",
                            )}
                        >
                            {fmtDate(basis.issued_at)} —{" "}
                            {fmtDate(basis.expires_at)}
                        </span>
                    </Fact>

                    {!isCourt && basis.order && (
                        <>
                            <Fact label="Ishonch bildiruvchi (mijoz)">
                                <span className="flex flex-col gap-1">
                                    <span>
                                        {basis.order.client_name ?? "—"}
                                    </span>
                                    {basis.order.client_pinfl_masked && (
                                        <RegistryChip
                                            value={
                                                basis.order.client_pinfl_masked
                                            }
                                            kind="pinfl"
                                            size="sm"
                                        />
                                    )}
                                </span>
                            </Fact>
                            <Fact label="Order bo'yicha ish mazmuni">
                                {basis.order.matter ?? "—"}
                            </Fact>
                            {basis.order.license_number && (
                                <Fact label="Litsenziya">
                                    <RegistryChip
                                        value={basis.order.license_number}
                                        kind="license"
                                        size="sm"
                                    />
                                </Fact>
                            )}
                        </>
                    )}
                    {!isCourt && basis.purpose && (
                        <Fact label="So'rov maqsadi" className="sm:col-span-3">
                            {basis.purpose}
                        </Fact>
                    )}
                </dl>

                <div className="grid gap-6 border-t pt-5 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <h4 className="text-xs font-medium text-ink-secondary">
                            Qamraydi
                        </h4>
                        {basis.allowed_types.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                Hech qaysi tur qamrab olinmagan
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-1.5">
                                {basis.allowed_type_labels.map((label) => (
                                    <li
                                        key={label}
                                        className="flex items-center gap-2 text-sm text-ink"
                                    >
                                        <Check
                                            className={cn(
                                                "size-4 shrink-0",
                                                verified
                                                    ? "text-success"
                                                    : "text-ink-muted",
                                            )}
                                            strokeWidth={2}
                                            aria-hidden
                                        />
                                        {label}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    {basis.not_covered_types.length > 0 && (
                        <div className="flex flex-col gap-2">
                            <h4 className="text-xs font-medium text-ink-secondary">
                                Qamramaydi
                            </h4>
                            <ul className="flex flex-col gap-1.5">
                                {basis.not_covered_types.map((t) => (
                                    <li
                                        key={t.value}
                                        className="flex items-center gap-2 text-sm text-ink-muted"
                                    >
                                        <span
                                            className="flex size-4 shrink-0 items-center justify-center"
                                            aria-hidden
                                        >
                                            <span className="h-px w-2.5 bg-current" />
                                        </span>
                                        {t.label}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
                {!isCourt && courtOnly.length > 0 && (
                    <p className="-mt-2 text-xs text-ink-muted">
                        Advokat orderi{" "}
                        {courtOnly.map((t) => t.label.toLowerCase()).join(", ")}{" "}
                        ma'lumotini ochmaydi, buning uchun sud qarori yoki
                        ajrimi kerak
                    </p>
                )}
            </div>

            {(basis.has_file || canVerify) && (
                <footer className="flex flex-wrap items-center justify-end gap-2 border-t bg-page px-5 py-3 sm:px-6">
                    {basis.has_file && (
                        <Button asChild variant="ghost" size="sm">
                            <a
                                href={basesRoutes.file.url({
                                    legalCase: caseId,
                                    basis: basis.id,
                                })}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText /> PDF nusxa
                            </a>
                        </Button>
                    )}
                    {canVerify && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={reverify}
                            disabled={verifying}
                        >
                            {verifying ? <Spinner /> : <RefreshCw />}
                            Qayta tekshirish
                        </Button>
                    )}
                </footer>
            )}
        </article>
    );
}

function Fact({
    label,
    className,
    children,
}: {
    label: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn("flex min-w-0 flex-col gap-1", className)}>
            <dt className="text-xs text-ink-secondary">{label}</dt>
            <dd className="text-ink">{children}</dd>
        </div>
    );
}

function AddBasisForm({
    caseId,
    legalCase,
    orderMatrix,
    onDone,
}: {
    caseId: number;
    legalCase: CaseDetail;
    orderMatrix: OrderMatrix;
    onDone: () => void;
}) {
    const form = useForm<{
        type: LegalBasisType;
        number: string;
        file: File | null;
        subject_pinfl: string;
        subject_name: string;
        allowed_types: DataRequestType[];
        expires_at: string;
        purpose: string;
    }>({
        type: "advocate_order",
        number: "",
        file: null,
        subject_pinfl: "",
        subject_name: "",
        allowed_types: [],
        expires_at: "",
        purpose: "",
    });
    const { data, setData, errors, processing } = form;
    const isOrder = data.type === "advocate_order";
    const errorFor = (key: string) =>
        (errors as Record<string, string | undefined>)[key];
    const [issuing, setIssuing] = useState(false);
    const [issued, setIssued] = useState<IssuedOrder | null>(null);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) =>
            d.type === "advocate_order"
                ? d
                : { type: d.type, number: d.number, file: d.file },
        );
        form.submit(basesRoutes.store(caseId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-6 rounded-xl border bg-card p-5 shadow-xs sm:p-6"
            aria-label="Yangi huquqiy asos"
        >
            <div className="flex flex-col gap-1">
                <h3 className="text-base font-medium text-ink">
                    Yangi huquqiy asos
                </h3>
                <p className="text-sm text-ink-secondary">
                    {isOrder
                        ? `Advokatura to'g'risidagi Qonunning 6- va 7¹-moddalari: idora ${orderMatrix.deadlines.default_days} kun ichida, sud ishi boshlangan bo'lsa ${orderMatrix.deadlines.proceedings_work_days} ish kuni ichida javob beradi. Sud qarori talab qilinmaydi`
                        : "Raqam sud reyestridan tekshiriladi: subyekt, sud, muddat va ruxsat etilgan ma'lumot turlari avtomatik to'ldiriladi"}
                </p>
            </div>

            <Tabs
                value={data.type}
                onValueChange={(v) => setData("type", v as LegalBasisType)}
                className="gap-6"
            >
                <TabsList aria-label="Asos turi">
                    <TabsTrigger value="advocate_order">
                        Advokat orderi
                    </TabsTrigger>
                    <TabsTrigger value="court_decision">
                        Sud qarori yoki ajrimi
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    label={isOrder ? "Order raqami" : "Qaror yoki ajrim raqami"}
                    htmlFor="basis-number"
                    error={errors.number}
                    hint={
                        isOrder
                            ? "Advokatlar palatasining elektron orderlar reyestridan tekshiriladi: order faol va sizning litsenziyangiz bilan berilgan bo'lishi kerak"
                            : undefined
                    }
                >
                    <Input
                        id="basis-number"
                        value={data.number}
                        onChange={(e) => setData("number", e.target.value)}
                        className="font-mono"
                        placeholder={
                            isOrder ? "ORD-2026-000457" : "2-1001-2611/0457"
                        }
                        required
                    />
                </FormField>
                <FormField
                    label="PDF nusxa"
                    optional
                    htmlFor="basis-file"
                    error={errors.file}
                >
                    <Input
                        id="basis-file"
                        type="file"
                        accept="application/pdf"
                        className="py-2"
                        onChange={(e) =>
                            setData("file", e.target.files?.[0] ?? null)
                        }
                    />
                </FormField>
            </div>

            {isOrder && (
                <>
                    {issued ? (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-success-border bg-success-soft px-4 py-3 text-sm text-ink">
                            <span className="font-medium text-success">
                                Elektron order berildi
                            </span>
                            <RegistryChip
                                value={issued.order_number}
                                kind="order"
                                size="sm"
                            />
                            <span className="text-ink-secondary">
                                {issued.client_name} · {issued.matter}
                            </span>
                        </div>
                    ) : issuing ? (
                        <IssueOrderForm
                            caseId={caseId}
                            legalCase={legalCase}
                            onCancel={() => setIssuing(false)}
                            onIssued={(o) => {
                                setIssued(o);
                                setIssuing(false);
                                setData("number", o.order_number);
                            }}
                        />
                    ) : (
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setIssuing(true)}
                            >
                                <ScrollText /> Elektron order berish (demo)
                            </Button>
                            <span className="text-xs text-ink-muted">
                                Order raqami yo'q bo'lsa
                            </span>
                        </div>
                    )}

                    <div className="flex flex-col gap-4">
                        <div className="grid gap-6 sm:grid-cols-3">
                            <FormField
                                label="So'raladigan shaxs JShShIR"
                                htmlFor="subject-pinfl"
                                error={errors.subject_pinfl}
                            >
                                <Input
                                    id="subject-pinfl"
                                    inputMode="numeric"
                                    maxLength={14}
                                    value={data.subject_pinfl}
                                    onChange={(e) =>
                                        setData(
                                            "subject_pinfl",
                                            e.target.value.replace(/\D/g, ""),
                                        )
                                    }
                                    className="font-mono"
                                    placeholder="14 ta raqam"
                                />
                            </FormField>
                            <FormField
                                label="So'raladigan shaxs F.I.Sh."
                                htmlFor="subject-name"
                                error={errors.subject_name}
                            >
                                <Input
                                    id="subject-name"
                                    value={data.subject_name}
                                    onChange={(e) =>
                                        setData("subject_name", e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Amal qilish muddati"
                                htmlFor="expires-at"
                                error={errors.expires_at}
                            >
                                <Input
                                    id="expires-at"
                                    type="date"
                                    value={data.expires_at}
                                    onChange={(e) =>
                                        setData("expires_at", e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <p className="text-xs text-ink-secondary">
                            Order mijozni vakillik qilish vakolatini beradi.
                            Ma'lumoti so'raladigan shaxsni (masalan, javobgarni)
                            advokat o'zi ko'rsatadi va so'rov maqsadini ish
                            mazmuniga bog'laydi.
                        </p>
                    </div>

                    <FormField
                        label="So'rov maqsadi"
                        htmlFor="order-purpose"
                        error={errors.purpose}
                    >
                        <Textarea
                            id="order-purpose"
                            rows={2}
                            maxLength={500}
                            className="min-h-20"
                            value={data.purpose}
                            onChange={(e) => setData("purpose", e.target.value)}
                            placeholder="Masalan: aliment undirish ishi bo'yicha javobgarning undiruv qaratiladigan mol-mulkini aniqlash"
                        />
                    </FormField>

                    <fieldset className="flex flex-col gap-3">
                        <legend className="mb-3 text-sm font-medium text-ink">
                            Order qamraydigan ma'lumotlar
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {orderMatrix.order.map((t) => {
                                const checked = data.allowed_types.includes(
                                    t.value,
                                );

                                return (
                                    <label
                                        key={t.value}
                                        className={cn(
                                            "flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-surface px-3 py-2 text-sm text-ink transition-colors hover:border-ink-muted/50",
                                            checked &&
                                                "border-brand bg-brand-soft hover:border-brand",
                                        )}
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={(v) =>
                                                setData(
                                                    "allowed_types",
                                                    v === true
                                                        ? [
                                                              ...data.allowed_types,
                                                              t.value,
                                                          ]
                                                        : data.allowed_types.filter(
                                                              (x) =>
                                                                  x !== t.value,
                                                          ),
                                                )
                                            }
                                        />
                                        <RequestTypeIcon
                                            type={t.value}
                                            className="size-4 shrink-0 text-ink-secondary"
                                        />
                                        {t.label}
                                    </label>
                                );
                            })}
                        </div>
                        <div className="flex flex-col gap-2 pt-2">
                            <p className="text-xs text-ink-secondary">
                                Faqat sud qarori bilan, orderga kiritilmaydi
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {orderMatrix.court_only.map((t) => (
                                    <div
                                        key={t.value}
                                        className="flex items-start gap-3 rounded-lg border border-dashed bg-page px-3 py-2.5 text-sm text-ink-muted"
                                        title={t.why ?? undefined}
                                    >
                                        <Checkbox
                                            checked={false}
                                            disabled
                                            aria-label={`${t.label}: faqat sud qarori bilan`}
                                            className="mt-0.5"
                                        />
                                        <span className="flex flex-col gap-0.5">
                                            <span className="flex items-center gap-2">
                                                <Gavel
                                                    className="size-4 shrink-0"
                                                    strokeWidth={1.5}
                                                    aria-hidden
                                                />
                                                {t.label}
                                            </span>
                                            {t.why && (
                                                <span className="text-xs">
                                                    {t.why}
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <InputError
                            message={
                                errors.allowed_types ??
                                errorFor("allowed_types.0")
                            }
                        />
                    </fieldset>
                </>
            )}

            <div className="flex flex-col-reverse gap-2 border-t pt-5 sm:flex-row sm:justify-end">
                <Button type="button" variant="ghost" onClick={onDone}>
                    Bekor qilish
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {isOrder
                        ? "Orderni qo'shish va tekshirish"
                        : "Qo'shish va tekshirish"}
                </Button>
            </div>
        </form>
    );
}

function FormField({
    label,
    htmlFor,
    optional = false,
    hint,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    optional?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2">
            <Label htmlFor={htmlFor}>
                {label}
                {optional && (
                    <span className="ml-1 font-normal text-ink-muted">
                        ixtiyoriy
                    </span>
                )}
            </Label>
            {children}
            {hint && <p className="text-xs text-ink-secondary">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function IssueOrderForm({
    caseId,
    legalCase,
    onIssued,
    onCancel,
}: {
    caseId: number;
    legalCase: CaseDetail;
    onIssued: (o: IssuedOrder) => void;
    onCancel: () => void;
}) {
    const [clientPinfl, setClientPinfl] = useState("");
    const [clientName, setClientName] = useState(legalCase.client_name);
    const [matter, setMatter] = useState(
        legalCase.category_label ?? legalCase.title,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const issue = () => {
        setBusy(true);
        router.post(
            advocateOrdersRoutes.store.url(caseId),
            {
                client_pinfl: clientPinfl,
                client_name: clientName,
                matter,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (e) => setErrors(e as Record<string, string>),
                onSuccess: (page) => {
                    const o = (page.flash as Record<string, unknown>)
                        ?.issued_order as IssuedOrder | undefined;
                    if (o?.order_number) {
                        onIssued(o);
                    }
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-4 rounded-lg border bg-raised p-4 sm:p-5">
            <div className="flex flex-col gap-1">
                <p className="text-sm font-medium text-ink">
                    Elektron order berish (demo)
                </p>
                <p className="text-xs text-ink-secondary">
                    Advokatlar palatasining elektron orderlar reyestrida ushbu
                    ish mijozi uchun order rasmiylashtiriladi va raqami formaga
                    qo'yiladi
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <FormField
                    label="Mijoz JShShIR"
                    htmlFor="order-client-pinfl"
                    error={errors.client_pinfl}
                >
                    <Input
                        id="order-client-pinfl"
                        inputMode="numeric"
                        maxLength={14}
                        value={clientPinfl}
                        onChange={(e) =>
                            setClientPinfl(e.target.value.replace(/\D/g, ""))
                        }
                        className="font-mono"
                        placeholder="14 ta raqam"
                    />
                </FormField>
                <FormField
                    label="Mijoz F.I.Sh."
                    htmlFor="order-client-name"
                    error={errors.client_name}
                >
                    <Input
                        id="order-client-name"
                        value={clientName}
                        onChange={(e) => setClientName(e.target.value)}
                    />
                </FormField>
                <FormField
                    label="Ish mazmuni"
                    htmlFor="order-matter"
                    error={errors.matter}
                >
                    <Input
                        id="order-matter"
                        value={matter}
                        onChange={(e) => setMatter(e.target.value)}
                    />
                </FormField>
            </div>
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onCancel}
                >
                    Bekor qilish
                </Button>
                <Button type="button" size="sm" onClick={issue} disabled={busy}>
                    {busy && <Spinner />}
                    Order berish
                </Button>
            </div>
        </div>
    );
}

function ClientAccessSection({
    caseId,
    bases,
    links,
}: {
    caseId: number;
    bases: BasisDetail[];
    links: ClientLink[];
}) {
    const { flash } = usePage();
    const newUrl =
        typeof flash?.client_link_url === "string"
            ? flash.client_link_url
            : null;
    const candidates = bases.filter(
        (b) =>
            b.type === "advocate_order" &&
            b.verification_status === "verified" &&
            b.order?.client_known,
    );
    const form = useForm<{ order_number: string }>({
        order_number: candidates[0]?.number ?? "",
    });
    const active = links.filter((l) => !l.revoked_at);

    const create = (e: FormEvent) => {
        e.preventDefault();
        form.submit(clientLinksRoutes.store(caseId), { preserveScroll: true });
    };

    return (
        <Section
            title="Mijoz uchun kirish"
            description="Ishonch bildiruvchi OneID orqali kirib, ish va so'rovlar holatini ko'radi. Kirish faqat uning JShShIR'i advokat orderidagi mijoz JShShIR'iga mos kelsa beriladi. To'liq javoblar advokatda qoladi"
            className="max-w-4xl"
            contentClassName="flex flex-col gap-6"
        >
            <form
                onSubmit={create}
                className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-end sm:p-6"
            >
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <Label htmlFor="client-link-order">Advokat orderi</Label>
                    {candidates.length > 0 ? (
                        <Select
                            value={form.data.order_number}
                            onValueChange={(v) =>
                                form.setData("order_number", v)
                            }
                        >
                            <SelectTrigger
                                id="client-link-order"
                                className="w-full"
                            >
                                <SelectValue placeholder="Orderni tanlang" />
                            </SelectTrigger>
                            <SelectContent>
                                {candidates.map((b) => (
                                    <SelectItem key={b.id} value={b.number}>
                                        <span className="font-mono">
                                            {b.number}
                                        </span>{" "}
                                        · {b.order?.client_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            id="client-link-order"
                            className="font-mono"
                            placeholder="ORD-2026-000457"
                            value={form.data.order_number}
                            onChange={(e) =>
                                form.setData("order_number", e.target.value)
                            }
                        />
                    )}
                    <InputError message={form.errors.order_number} />
                </div>
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="h-11"
                >
                    <Link2 /> Havola yaratish
                </Button>
            </form>

            {newUrl && <CopyLink url={newUrl} highlight />}

            {active.length === 0 ? (
                <EmptyState
                    icon={Link2}
                    title="Faol mijoz havolasi yo'q"
                    bordered
                />
            ) : (
                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                    {active.map((l) => (
                        <li
                            key={l.id}
                            className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div className="flex min-w-0 flex-col gap-1">
                                <span className="flex flex-wrap items-center gap-2 text-ink">
                                    <RegistryChip
                                        value={l.order_number}
                                        kind="order"
                                        size="sm"
                                    />
                                    {l.client_name ?? "Mijoz"}
                                </span>
                                <span className="text-xs text-ink-secondary tabular-nums">
                                    {l.granted_at
                                        ? `Mijoz kirgan ${fmtDate(l.granted_at)}`
                                        : "Mijoz hali kirmagan"}
                                    {l.last_viewed_at &&
                                        ` · oxirgi ko'rish ${fmtDate(l.last_viewed_at)}`}
                                </span>
                            </div>
                            <div className="flex items-center gap-1">
                                {l.url && <CopyLink url={l.url} />}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            clientLinksRoutes.destroy.url({
                                                legalCase: caseId,
                                                link: l.id,
                                            }),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 /> Bekor qilish
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Section>
    );
}

function CopyLink({
    url,
    highlight = false,
}: {
    url: string;
    highlight?: boolean;
}) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        try {
            void navigator.clipboard?.writeText(url);
        } catch {
            // clipboard unavailable
        }
        setCopied(true);
    };

    return highlight ? (
        <div className="flex flex-col gap-3 rounded-lg border border-brand-border bg-brand-soft px-4 py-3 sm:flex-row sm:items-center">
            <span className="min-w-0 flex-1 truncate font-mono text-sm text-ink">
                {url}
            </span>
            <Button type="button" size="sm" variant="outline" onClick={copy}>
                {copied ? <Check /> : <Copy />}{" "}
                {copied ? "Nusxalandi" : "Nusxalash"}
            </Button>
        </div>
    ) : (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            title={url}
            onClick={copy}
        >
            {copied ? <Check /> : <Copy />} {copied ? "Nusxalandi" : "Havola"}
        </Button>
    );
}

/** Which request type is opened by which basis. */
function CoveragePanel({
    coverage,
    requestTypes,
}: {
    coverage: Record<DataRequestType, CoverageEntry[]>;
    requestTypes: RequestTypeInfo[];
}) {
    const covered = requestTypes.filter(
        (t) => (coverage[t.value] ?? []).length > 0,
    ).length;

    return (
        <aside
            aria-labelledby="coverage-title"
            className="flex h-fit flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-[calc(var(--spacing-header)+24px)]"
        >
            <div className="flex items-baseline justify-between gap-2">
                <h3
                    id="coverage-title"
                    className="text-base font-medium text-ink"
                >
                    Qamrov
                </h3>
                <span className="text-xs text-ink-muted tabular-nums">
                    {covered} / {requestTypes.length} tur
                </span>
            </div>
            <ul className="-mx-1 flex flex-col">
                {requestTypes.map((t) => {
                    const entries = coverage[t.value] ?? [];
                    const ok = entries.length > 0;

                    return (
                        <li
                            key={t.value}
                            className="flex flex-col gap-1.5 border-b border-hairline px-1 py-3 last:border-0"
                        >
                            <div className="flex items-center gap-2.5">
                                <RequestTypeIcon
                                    type={t.value}
                                    className={cn(
                                        "size-4 shrink-0",
                                        ok ? "text-ink" : "text-ink-muted",
                                    )}
                                />
                                <span
                                    className={cn(
                                        "min-w-0 flex-1 text-sm",
                                        ok
                                            ? "font-medium text-ink"
                                            : "text-ink-secondary",
                                    )}
                                >
                                    {t.label}
                                </span>
                                {ok ? (
                                    <Check
                                        className="size-4 shrink-0 text-success"
                                        strokeWidth={2}
                                        aria-label="Qamralgan"
                                    />
                                ) : (
                                    <span className="text-xs text-ink-muted">
                                        Asos yo'q
                                    </span>
                                )}
                            </div>
                            {ok ? (
                                <div className="flex flex-wrap gap-1.5 pl-6.5">
                                    {entries.map((e) => (
                                        <RegistryChip
                                            key={e.basis_id}
                                            value={e.number}
                                            kind={
                                                e.basis_type ===
                                                "court_decision"
                                                    ? "decision"
                                                    : "order"
                                            }
                                            size="sm"
                                        />
                                    ))}
                                </div>
                            ) : (
                                <p className="pl-6.5 text-xs text-ink-muted">
                                    {t.secrecy_regime}
                                </p>
                            )}
                        </li>
                    );
                })}
            </ul>
        </aside>
    );
}

/* ---------------------------------------------------------------- Team */

function TeamSection({
    case: legalCase,
    members,
    can,
    available_lawyers,
}: Props) {
    const [lawyerId, setLawyerId] = useState("");
    const addForm = useForm<{ user_id: string }>({ user_id: "" });

    const toggle = (m: Member, value: boolean) =>
        router.patch(
            membersRoutes.update.url({ legalCase: legalCase.id, member: m.id }),
            { can_view_responses: value },
            { preserveScroll: true },
        );

    const remove = (m: Member) => {
        if (confirm(`${m.user.name} ishdan chiqarilsinmi?`)) {
            router.delete(
                membersRoutes.destroy.url({
                    legalCase: legalCase.id,
                    member: m.id,
                }),
                { preserveScroll: true },
            );
        }
    };

    const add = (e: FormEvent) => {
        e.preventDefault();
        addForm.transform(() => ({ user_id: lawyerId }));
        addForm.submit(membersRoutes.store(legalCase.id), {
            preserveScroll: true,
            onSuccess: () => setLawyerId(""),
        });
    };

    return (
        <Section
            title="Jamoa"
            description="Yurist javoblarni faqat advokat ruxsat bergandan va advokat siri majburiyatini qabul qilgandan so'ng ko'radi"
            contentClassName="flex flex-col gap-6"
        >
            <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>A'zo</TableHead>
                            <TableHead className="hidden sm:table-cell">
                                Advokat siri majburiyati
                            </TableHead>
                            <TableHead>Javoblarni ko'rish</TableHead>
                            {can.manage_members && (
                                <TableHead className="w-12">
                                    <span className="sr-only">Amallar</span>
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {members.map((m) => {
                            const secrecy =
                                m.role === "advocate"
                                    ? "Ish egasi"
                                    : m.secrecy_declared_at
                                      ? `Qabul qilingan ${fmtDate(m.secrecy_declared_at)}`
                                      : "Qabul qilinmagan";

                            return (
                                <TableRow key={m.id}>
                                    <TableCell className="py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-ink">
                                                {m.user.name}
                                            </span>
                                            <span className="text-xs text-ink-secondary">
                                                {m.role === "advocate"
                                                    ? "Advokat"
                                                    : "Yurist"}
                                                <span className="sm:hidden">
                                                    {" "}
                                                    · {secrecy}
                                                </span>
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            "hidden text-sm tabular-nums sm:table-cell",
                                            m.role === "lawyer" &&
                                                !m.secrecy_declared_at
                                                ? "text-warning"
                                                : "text-ink-secondary",
                                        )}
                                    >
                                        {secrecy}
                                    </TableCell>
                                    <TableCell>
                                        {m.role === "advocate" ? (
                                            <span className="text-sm text-ink-secondary">
                                                Barcha javoblar
                                            </span>
                                        ) : (
                                            <label
                                                className={cn(
                                                    "inline-flex items-center gap-2 text-sm",
                                                    can.manage_members
                                                        ? "cursor-pointer text-ink"
                                                        : "text-ink-secondary",
                                                )}
                                            >
                                                <Checkbox
                                                    checked={
                                                        m.can_view_responses
                                                    }
                                                    disabled={
                                                        !can.manage_members
                                                    }
                                                    onCheckedChange={(v) =>
                                                        toggle(m, v === true)
                                                    }
                                                />
                                                {m.can_view_responses
                                                    ? "Ruxsat berilgan"
                                                    : "Ruxsat yo'q"}
                                            </label>
                                        )}
                                    </TableCell>
                                    {can.manage_members && (
                                        <TableCell className="px-2 text-right">
                                            {m.role === "lawyer" && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Ishdan chiqarish"
                                                    onClick={() => remove(m)}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            {can.manage_members && (
                <form
                    onSubmit={add}
                    className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-end sm:p-6"
                >
                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <Label htmlFor="add-lawyer">
                            Jamoadan yurist qo'shish
                        </Label>
                        {available_lawyers.length === 0 ? (
                            <p className="text-sm text-ink-secondary">
                                Qo'shish mumkin bo'lgan yurist yo'q.{" "}
                                <Link
                                    href="/team"
                                    className="text-ink underline underline-offset-4"
                                >
                                    Jamoaga yurist taklif qiling
                                </Link>
                            </p>
                        ) : (
                            <Select
                                value={lawyerId}
                                onValueChange={setLawyerId}
                            >
                                <SelectTrigger
                                    id="add-lawyer"
                                    aria-label="Yurist"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Yuristni tanlang" />
                                </SelectTrigger>
                                <SelectContent>
                                    {available_lawyers.map((l) => (
                                        <SelectItem
                                            key={l.id}
                                            value={String(l.id)}
                                        >
                                            {l.name}
                                            {l.email ? ` · ${l.email}` : ""}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <InputError message={addForm.errors.user_id} />
                    </div>
                    {available_lawyers.length > 0 && (
                        <Button
                            type="submit"
                            variant="outline"
                            className="h-11"
                            disabled={!lawyerId || addForm.processing}
                        >
                            <UserPlus /> Qo'shish
                        </Button>
                    )}
                </form>
            )}
        </Section>
    );
}

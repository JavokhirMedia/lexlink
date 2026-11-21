import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    CheckCircle2,
    Copy,
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
    XCircle,
} from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";
import InputError from "@/components/input-error";
import { CaseRequestsPanel } from "@/components/requests/case-requests-panel";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
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
import { cn } from "@/lib/utils";
import { index as casesIndex, show as casesShow } from "@/routes/cases";
import advocateOrdersRoutes from "@/routes/cases/advocate-orders";
import basesRoutes from "@/routes/cases/bases";
import clientLinksRoutes from "@/routes/cases/client-links";
import membersRoutes from "@/routes/cases/members";
import secrecyDeclaration from "@/routes/cases/secrecy-declaration";
import type {
    DataRequest,
    DataRequestType,
    LegalBasis,
    LegalBasisType,
    LegalCase,
} from "@/types/lexlink";
import { formatDate } from "@/components/brand/money";

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

type Tab = "bases" | "requests" | "team";

const fmtDate = (v: string | null) => formatDate(v);

const STATUS_META: Record<
    LegalBasis["verification_status"],
    { label: string; className: string }
> = {
    verified: {
        label: "Tasdiqlangan",
        className:
            "bg-emerald-100 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-300",
    },
    pending: {
        label: "Tekshirilmoqda",
        className: "bg-muted text-muted-foreground",
    },
    not_found: {
        label: "Topilmadi",
        className:
            "bg-red-100 text-red-900 dark:bg-red-500/15 dark:text-red-300",
    },
    expired: {
        label: "Muddati tugagan",
        className:
            "bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300",
    },
    mismatch: {
        label: "Mos emas",
        className:
            "bg-red-100 text-red-900 dark:bg-red-500/15 dark:text-red-300",
    },
};

export default function CaseShow(props: Props) {
    const {
        case: legalCase,
        bases,
        requests,
        membership,
        can,
        members,
    } = props;
    const [tab, setTab] = useState<Tab>(
        bases.length === 0 && can.add_basis ? "bases" : "requests",
    );

    const tabs: { key: Tab; label: string; count: number }[] = [
        { key: "bases", label: "Huquqiy asoslar", count: bases.length },
        { key: "requests", label: "So'rovlar", count: requests.length },
        { key: "team", label: "Jamoa", count: members.length },
    ];

    return (
        <>
            <Head title={legalCase.title} />
            <div className="flex w-full flex-col gap-8">
                <CaseHeader
                    legalCase={legalCase}
                    members={members}
                    canViewResponses={can.view_responses}
                />

                {membership.role === "lawyer" &&
                    !membership.secrecy_declared_at && (
                        <SecrecyDeclaration caseId={legalCase.id} />
                    )}

                <div
                    className="border-b"
                    role="tablist"
                    aria-label="Ish bo'limlari"
                >
                    <div className="-mb-px flex gap-6 overflow-x-auto">
                        {tabs.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                role="tab"
                                aria-selected={tab === t.key}
                                onClick={() => setTab(t.key)}
                                className={cn(
                                    "flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium whitespace-nowrap transition-colors",
                                    tab === t.key
                                        ? "border-primary text-foreground"
                                        : "border-transparent text-muted-foreground hover:text-foreground",
                                )}
                            >
                                {t.label}
                                <span className="rounded bg-muted px-1.5 text-xs tabular-nums text-muted-foreground">
                                    {t.count}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {tab === "bases" && <BasesSection {...props} />}
                {tab === "requests" && (
                    <CaseRequestsPanel
                        caseId={legalCase.id}
                        requests={requests}
                    />
                )}
                {tab === "team" && <TeamSection {...props} />}
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
    members,
    canViewResponses,
}: {
    legalCase: CaseDetail;
    members: Member[];
    canViewResponses: boolean;
}) {
    return (
        <div className="flex flex-col gap-4 rounded-lg border bg-card p-5 md:flex-row md:items-start md:justify-between">
            <div className="min-w-0 space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {legalCase.title}
                    </h1>
                    <Badge
                        variant={
                            legalCase.status === "closed"
                                ? "secondary"
                                : "outline"
                        }
                    >
                        {legalCase.status === "closed" ? "Yopilgan" : "Faol"}
                    </Badge>
                </div>
                <dl className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Mijoz</dt>
                        <dd className="font-medium">{legalCase.client_name}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Sud ishi raqami
                        </dt>
                        <dd className="font-mono">
                            {legalCase.court_case_number ?? "—"}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Toifa</dt>
                        <dd>{legalCase.category_label ?? "—"}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Jamoa</dt>
                        <dd>
                            {members.map((m) => (
                                <span key={m.id} className="mr-2 inline-block">
                                    {m.user.name}
                                    <span className="text-muted-foreground">
                                        {" "}
                                        (
                                        {m.role === "advocate"
                                            ? "advokat"
                                            : "yurist"}
                                        )
                                    </span>
                                </span>
                            ))}
                        </dd>
                    </div>
                </dl>
                {legalCase.parties.length > 0 && (
                    <div className="flex flex-wrap gap-2 text-sm">
                        {legalCase.parties.map((p, i) => (
                            <span
                                key={i}
                                className="rounded border px-2 py-0.5"
                            >
                                <span className="text-muted-foreground">
                                    {p.role}:
                                </span>{" "}
                                {p.name}
                            </span>
                        ))}
                    </div>
                )}
                {legalCase.description && (
                    <p className="max-w-3xl text-sm whitespace-pre-line text-muted-foreground">
                        {legalCase.description}
                    </p>
                )}
            </div>
            <div className="flex shrink-0 gap-2">
                {canViewResponses && (
                    <Button asChild variant="outline" size="sm">
                        <a href={`/cases/${legalCase.id}/claim`}>
                            <FileText /> Da'vo arizasi (AI)
                        </a>
                    </Button>
                )}
                <Button asChild variant="outline" size="sm">
                    <a href={`/audit?case_id=${legalCase.id}`}>
                        <History /> Audit jurnali
                    </a>
                </Button>
            </div>
        </div>
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
        <Alert>
            <ShieldCheck />
            <AlertTitle>Advokat sirini saqlash majburiyati</AlertTitle>
            <AlertDescription>
                <p>
                    Ushbu ish bo'yicha idoralardan olingan javoblarni ko'rish
                    uchun advokat sirini saqlash majburiyatini qabul qilishingiz
                    kerak. Javoblarni ko'rish ruxsatini advokat alohida beradi.
                </p>
                <div className="mt-3 flex flex-wrap items-center gap-4">
                    <label className="flex items-center gap-2 text-sm text-foreground">
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
            </AlertDescription>
        </Alert>
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
    client_links,
}: Props) {
    const [adding, setAdding] = useState(false);

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-medium">Huquqiy asoslar</h2>
                        <p className="text-sm text-muted-foreground">
                            Asossiz so'rov yuborilmaydi: har bir so'rov
                            tasdiqlangan, amaldagi asos bilan qoplanishi shart.
                        </p>
                    </div>
                    {can.add_basis && !adding && (
                        <Button size="sm" onClick={() => setAdding(true)}>
                            <Plus /> Asos qo'shish
                        </Button>
                    )}
                </div>

                {!can.add_basis && (
                    <p className="text-sm text-muted-foreground">
                        Huquqiy asosni faqat faol litsenziyaga ega advokat
                        qo'shadi.
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
                    <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-12 text-center">
                        <Gavel className="size-7 text-muted-foreground" />
                        <p className="font-medium">Huquqiy asos yo'q</p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Advokat orderi raqamini kiriting — ko'chmas mulk,
                            avtotransport, sud ishi, soliq (ochiq qism) va
                            ulushlar uchun yetarli. Ish haqi, bank siri va
                            chegaradan o'tish uchun sud qarori (ajrimi) kerak.
                        </p>
                    </div>
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

                {can.add_basis && (
                    <ClientAccessSection
                        caseId={legalCase.id}
                        bases={bases}
                        links={client_links}
                    />
                )}
            </div>

            <CoveragePanel coverage={coverage} requestTypes={request_types} />
        </div>
    );
}

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
    const status = STATUS_META[basis.verification_status];
    const Icon = basis.type === "court_decision" ? Gavel : ScrollText;
    const notCovered = basis.not_covered_types.map((t) =>
        t.label.toLowerCase(),
    );

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
        <article className="rounded-lg border bg-card">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
                <div className="flex items-start gap-3">
                    <Icon className="mt-0.5 size-5 text-muted-foreground" />
                    <div>
                        <div className="text-xs text-muted-foreground">
                            {basis.type_label}
                        </div>
                        <div className="font-mono font-medium">
                            {basis.number}
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <span
                        className={cn(
                            "rounded px-2 py-0.5 text-xs font-medium",
                            status.className,
                        )}
                    >
                        {status.label}
                    </span>
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
                                <FileText /> PDF
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
                            <RefreshCw
                                className={cn(verifying && "animate-spin")}
                            />{" "}
                            Qayta tekshirish
                        </Button>
                    )}
                </div>
            </header>

            <div className="space-y-4 px-5 py-4">
                {basis.verification_error &&
                    basis.verification_status !== "verified" && (
                        <p className="flex items-center gap-2 text-sm text-destructive">
                            <XCircle className="size-4" />{" "}
                            {basis.verification_error}
                        </p>
                    )}

                <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-muted-foreground">
                            {basis.type === "court_decision"
                                ? "Sud"
                                : "Bergan advokat"}
                        </dt>
                        <dd>
                            {basis.type === "court_decision"
                                ? (basis.court_name ?? "—")
                                : (basis.created_by?.name ?? "—")}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Subyekt</dt>
                        <dd>
                            {basis.subject_name ?? "—"}
                            {basis.subject_pinfl_masked && (
                                <div className="font-mono text-xs text-muted-foreground">
                                    JShShIR {basis.subject_pinfl_masked}
                                </div>
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Amal qilish muddati
                        </dt>
                        <dd
                            className={cn(
                                basis.is_expired && "text-destructive",
                            )}
                        >
                            {fmtDate(basis.issued_at)} —{" "}
                            {fmtDate(basis.expires_at)}
                        </dd>
                    </div>
                </dl>

                {basis.type === "advocate_order" &&
                    (basis.order || basis.purpose) && (
                        <dl className="grid gap-x-6 gap-y-3 rounded-md bg-muted/40 px-3 py-2 text-sm sm:grid-cols-3">
                            {basis.order && (
                                <>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Ishonch bildiruvchi (mijoz)
                                        </dt>
                                        <dd>
                                            {basis.order.client_name ?? "—"}
                                            {basis.order
                                                .client_pinfl_masked && (
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    JShShIR{" "}
                                                    {
                                                        basis.order
                                                            .client_pinfl_masked
                                                    }
                                                </div>
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Order bo'yicha ish mazmuni
                                        </dt>
                                        <dd>{basis.order.matter ?? "—"}</dd>
                                    </div>
                                </>
                            )}
                            {basis.purpose && (
                                <div>
                                    <dt className="text-muted-foreground">
                                        So'rov maqsadi
                                    </dt>
                                    <dd>{basis.purpose}</dd>
                                </div>
                            )}
                        </dl>
                    )}

                <div className="space-y-2">
                    <div className="text-sm text-muted-foreground">
                        Qamraydigan ma'lumot turlari
                    </div>
                    {basis.allowed_types.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Hech qaysi tur qamrab olinmagan.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {basis.allowed_type_labels.map((l) => (
                                <span
                                    key={l}
                                    className="inline-flex items-center gap-1 rounded border border-primary/30 bg-primary/5 px-2 py-0.5 text-xs font-medium text-primary"
                                >
                                    <CheckCircle2 className="size-3" /> {l}
                                </span>
                            ))}
                        </div>
                    )}
                    {basis.verification_status === "verified" &&
                        notCovered.length > 0 && (
                            <p className="flex items-start gap-1.5 text-sm text-muted-foreground">
                                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                Bu asos {notCovered.join(", ")} ma'lumotini
                                qamramaydi.
                            </p>
                        )}
                    {basis.type === "advocate_order" && (
                        <p className="text-xs text-muted-foreground">
                            Advokat orderi{" "}
                            {courtOnly
                                .map((t) => t.label.toLowerCase())
                                .join(", ")}{" "}
                            ma'lumotini ochmaydi — buning uchun sud qarori yoki
                            ajrimi kerak.
                        </p>
                    )}
                </div>
            </div>
        </article>
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
            className="space-y-4 rounded-lg border border-primary/30 bg-card p-5"
        >
            <div
                className="flex gap-1 rounded-md border p-1"
                role="radiogroup"
                aria-label="Asos turi"
            >
                {(
                    [
                        {
                            value: "advocate_order",
                            label: "Advokat orderi (advokat so'rovi)",
                        },
                        {
                            value: "court_decision",
                            label: "Sud qarori / ajrimi",
                        },
                    ] as const
                ).map((o) => (
                    <button
                        key={o.value}
                        type="button"
                        role="radio"
                        aria-checked={data.type === o.value}
                        onClick={() => setData("type", o.value)}
                        className={cn(
                            "flex-1 rounded px-3 py-1.5 text-sm transition-colors",
                            data.type === o.value
                                ? "bg-primary text-primary-foreground"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        {o.label}
                    </button>
                ))}
            </div>

            <p className="text-sm text-muted-foreground">
                {isOrder ? (
                    <>
                        Advokatura to'g'risidagi Qonunning 6- va 7¹-moddalari:
                        advokat orderi asosida so'rov yuboriladi, idora{" "}
                        {orderMatrix.deadlines.default_days} kun ichida, sud
                        ishi boshlangan bo'lsa —{" "}
                        {orderMatrix.deadlines.proceedings_work_days} ish kuni
                        ichida javob beradi. Sud qarori talab qilinmaydi.
                    </>
                ) : (
                    "Raqam sud reyestridan tekshiriladi: subyekt, sud, muddat va ruxsat etilgan ma'lumot turlari avtomatik to'ldiriladi."
                )}
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="basis-number">
                        {isOrder ? "Order raqami" : "Qaror / ajrim raqami"}
                    </Label>
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
                    {isOrder && (
                        <p className="text-xs text-muted-foreground">
                            Advokatlar palatasining elektron orderlar
                            reyestridan tekshiriladi: order faol va aynan
                            sizning litsenziyangiz bilan berilgan bo'lishi
                            kerak.
                        </p>
                    )}
                    <InputError message={errors.number} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="basis-file">
                        PDF nusxa{" "}
                        <span className="text-muted-foreground">
                            (ixtiyoriy)
                        </span>
                    </Label>
                    <Input
                        id="basis-file"
                        type="file"
                        accept="application/pdf"
                        onChange={(e) =>
                            setData("file", e.target.files?.[0] ?? null)
                        }
                    />
                    <InputError message={errors.file} />
                </div>
            </div>

            {isOrder && (
                <>
                    {issued ? (
                        <p className="flex items-start gap-2 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                            <span>
                                Elektron order berildi:{" "}
                                <span className="font-mono font-medium">
                                    {issued.order_number}
                                </span>{" "}
                                · {issued.client_name} · {issued.matter}
                            </span>
                        </p>
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
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIssuing(true)}
                        >
                            <ScrollText /> Elektron order berish (demo)
                        </Button>
                    )}

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="subject-pinfl">
                                So'raladigan shaxs JShShIR
                            </Label>
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
                            <InputError message={errors.subject_pinfl} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="subject-name">
                                So'raladigan shaxs F.I.Sh.
                            </Label>
                            <Input
                                id="subject-name"
                                value={data.subject_name}
                                onChange={(e) =>
                                    setData("subject_name", e.target.value)
                                }
                            />
                            <InputError message={errors.subject_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="expires-at">
                                Amal qilish muddati
                            </Label>
                            <Input
                                id="expires-at"
                                type="date"
                                value={data.expires_at}
                                onChange={(e) =>
                                    setData("expires_at", e.target.value)
                                }
                            />
                            <InputError message={errors.expires_at} />
                        </div>
                    </div>
                    <p className="-mt-2 text-xs text-muted-foreground">
                        Order ishonch bildiruvchini (mijozni) vakillik qilish
                        vakolatini beradi — ma'lumoti so'raladigan shaxsni
                        (masalan, javobgarni) advokat o'zi ko'rsatadi va so'rov
                        maqsadini ish mazmuniga bog'laydi.
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="order-purpose">So'rov maqsadi</Label>
                        <textarea
                            id="order-purpose"
                            rows={2}
                            maxLength={500}
                            value={data.purpose}
                            onChange={(e) => setData("purpose", e.target.value)}
                            placeholder="Masalan: aliment undirish ishi bo'yicha javobgarning undiruv qaratiladigan mol-mulkini aniqlash"
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={errors.purpose} />
                    </div>

                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium">
                            Order qamraydigan ma'lumotlar
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {orderMatrix.order.map((t) => (
                                <label
                                    key={t.value}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={data.allowed_types.includes(
                                            t.value,
                                        )}
                                        onCheckedChange={(v) =>
                                            setData(
                                                "allowed_types",
                                                v === true
                                                    ? [
                                                          ...data.allowed_types,
                                                          t.value,
                                                      ]
                                                    : data.allowed_types.filter(
                                                          (x) => x !== t.value,
                                                      ),
                                            )
                                        }
                                    />
                                    {t.label}
                                </label>
                            ))}
                        </div>
                        <div className="space-y-1.5 border-t pt-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Faqat sud qarori bilan (orderga kiritilmaydi):
                            </p>
                            {orderMatrix.court_only.map((t) => (
                                <label
                                    key={t.value}
                                    className="flex items-start gap-2 text-sm text-muted-foreground opacity-70"
                                    title={t.why ?? undefined}
                                >
                                    <Checkbox
                                        checked={false}
                                        disabled
                                        className="mt-0.5"
                                    />
                                    <span>
                                        {t.label}
                                        {t.why && (
                                            <span className="block text-xs">
                                                {t.why}
                                            </span>
                                        )}
                                    </span>
                                </label>
                            ))}
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

            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onDone}>
                    Bekor qilish
                </Button>
                <Button type="submit" disabled={processing}>
                    {isOrder
                        ? "Orderni qo'shish va tekshirish"
                        : "Qo'shish va tekshirish"}
                </Button>
            </div>
        </form>
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
        <div className="space-y-3 rounded-md border border-dashed p-4">
            <div>
                <p className="text-sm font-medium">
                    Elektron order berish (demo)
                </p>
                <p className="text-xs text-muted-foreground">
                    Advokatlar palatasining elektron orderlar reyestrida ushbu
                    ish mijozi uchun order rasmiylashtiriladi va raqami formaga
                    qo'yiladi.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="order-client-pinfl" className="text-xs">
                        Mijoz JShShIR
                    </Label>
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
                    <InputError message={errors.client_pinfl} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="order-client-name" className="text-xs">
                        Mijoz F.I.Sh.
                    </Label>
                    <Input
                        id="order-client-name"
                        value={clientName}
                        onChange={(e) => setClientName(e.target.value)}
                    />
                    <InputError message={errors.client_name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="order-matter" className="text-xs">
                        Ish mazmuni
                    </Label>
                    <Input
                        id="order-matter"
                        value={matter}
                        onChange={(e) => setMatter(e.target.value)}
                    />
                    <InputError message={errors.matter} />
                </div>
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
        <section className="space-y-3 rounded-lg border bg-card p-5">
            <div className="flex items-start gap-3">
                <Link2 className="mt-0.5 size-5 text-muted-foreground" />
                <div>
                    <h3 className="font-medium">Mijoz uchun kirish</h3>
                    <p className="text-sm text-muted-foreground">
                        Ishonch bildiruvchi OneID orqali kirib, ish va so'rovlar
                        holatini ko'radi. Kirish faqat uning JShShIR'i advokat
                        orderidagi mijoz JShShIR'iga mos kelsa beriladi. To'liq
                        javoblar advokatda qoladi.
                    </p>
                </div>
            </div>

            {newUrl && <CopyLink url={newUrl} highlight />}

            <form onSubmit={create} className="flex flex-wrap items-end gap-2">
                <div className="grid min-w-56 flex-1 gap-1.5">
                    <Label htmlFor="client-link-order" className="text-xs">
                        Advokat orderi
                    </Label>
                    {candidates.length > 0 ? (
                        <Select
                            value={form.data.order_number}
                            onValueChange={(v) =>
                                form.setData("order_number", v)
                            }
                        >
                            <SelectTrigger id="client-link-order">
                                <SelectValue placeholder="Orderni tanlang" />
                            </SelectTrigger>
                            <SelectContent>
                                {candidates.map((b) => (
                                    <SelectItem key={b.id} value={b.number}>
                                        {b.number} · {b.order?.client_name}
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
                </div>
                <Button type="submit" size="sm" disabled={form.processing}>
                    <Link2 /> Havola yaratish
                </Button>
            </form>
            <InputError message={form.errors.order_number} />

            {active.length > 0 && (
                <ul className="divide-y rounded-md border text-sm">
                    {active.map((l) => (
                        <li
                            key={l.id}
                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                        >
                            <div className="min-w-0">
                                <span className="font-mono">
                                    {l.order_number}
                                </span>{" "}
                                · {l.client_name ?? "Mijoz"}
                                <div className="text-xs text-muted-foreground">
                                    {l.granted_at
                                        ? `Mijoz kirgan: ${fmtDate(l.granted_at)}`
                                        : "Mijoz hali kirmagan"}
                                    {l.last_viewed_at &&
                                        ` · oxirgi ko'rish ${fmtDate(l.last_viewed_at)}`}
                                </div>
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
        </section>
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

    return highlight ? (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm">
            <span className="min-w-0 flex-1 truncate font-mono text-xs">
                {url}
            </span>
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => {
                    void navigator.clipboard?.writeText(url);
                    setCopied(true);
                }}
            >
                <Copy /> {copied ? "Nusxalandi" : "Nusxalash"}
            </Button>
        </div>
    ) : (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            title={url}
            onClick={() => {
                void navigator.clipboard?.writeText(url);
                setCopied(true);
            }}
        >
            <Copy /> {copied ? "Nusxalandi" : "Havola"}
        </Button>
    );
}

function CoveragePanel({
    coverage,
    requestTypes,
}: {
    coverage: Record<DataRequestType, CoverageEntry[]>;
    requestTypes: RequestTypeInfo[];
}) {
    return (
        <aside className="h-fit space-y-3 rounded-lg border bg-card p-4">
            <div>
                <h3 className="text-sm font-medium">Qamrov</h3>
                <p className="text-xs text-muted-foreground">
                    Qaysi so'rov turini qaysi asos ochadi.
                </p>
            </div>
            <ul className="space-y-3">
                {requestTypes.map((t) => {
                    const entries = coverage[t.value] ?? [];
                    return (
                        <li key={t.value} className="space-y-1 text-sm">
                            <div className="flex items-center gap-2">
                                {entries.length > 0 ? (
                                    <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                                ) : (
                                    <XCircle className="size-4 text-muted-foreground" />
                                )}
                                <span className="font-medium">{t.label}</span>
                            </div>
                            {entries.length > 0 ? (
                                entries.map((e) => (
                                    <div
                                        key={e.basis_id}
                                        className="pl-6 text-xs text-muted-foreground"
                                    >
                                        <span className="font-mono">
                                            {e.number}
                                        </span>{" "}
                                        · {e.subject_name ?? "—"}{" "}
                                        <span className="font-mono">
                                            {e.subject_pinfl_masked}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <div className="pl-6 text-xs text-muted-foreground">
                                    {t.secrecy_regime}
                                </div>
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
        <div className="space-y-4">
            <div>
                <h2 className="font-medium">Jamoa</h2>
                <p className="text-sm text-muted-foreground">
                    Yurist javoblarni faqat advokat ruxsat bergandan va advokat
                    siri majburiyatini qabul qilgandan so'ng ko'radi.
                </p>
            </div>

            <ul className="divide-y rounded-lg border bg-card">
                {members.map((m) => (
                    <li
                        key={m.id}
                        className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div className="font-medium">{m.user.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {m.role === "advocate"
                                    ? "Advokat — ish egasi"
                                    : "Yurist"}
                                {m.role === "lawyer" &&
                                    (m.secrecy_declared_at
                                        ? ` · Advokat siri majburiyati: ${fmtDate(m.secrecy_declared_at)}`
                                        : " · Advokat siri majburiyati qabul qilinmagan")}
                            </div>
                        </div>
                        {m.role === "lawyer" && (
                            <div className="flex items-center gap-4">
                                <label
                                    className={cn(
                                        "flex items-center gap-2 text-sm",
                                        !can.manage_members &&
                                            "text-muted-foreground",
                                    )}
                                >
                                    <Checkbox
                                        checked={m.can_view_responses}
                                        disabled={!can.manage_members}
                                        onCheckedChange={(v) =>
                                            toggle(m, v === true)
                                        }
                                    />
                                    Javoblarni ko'rish ruxsati
                                </label>
                                {can.manage_members && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Ishdan chiqarish"
                                        onClick={() => remove(m)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>
                        )}
                        {m.role === "advocate" && (
                            <Badge variant="outline">Barcha javoblar</Badge>
                        )}
                    </li>
                ))}
            </ul>

            {can.manage_members && (
                <form
                    onSubmit={add}
                    className="flex flex-col gap-2 rounded-lg border bg-card p-4 sm:flex-row sm:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label>Jamoadan yurist qo'shish</Label>
                        {available_lawyers.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Qo'shish mumkin bo'lgan yurist yo'q.{" "}
                                <Link
                                    href="/team"
                                    className="underline underline-offset-4"
                                >
                                    Jamoaga yurist taklif qiling
                                </Link>
                                .
                            </p>
                        ) : (
                            <Select
                                value={lawyerId}
                                onValueChange={setLawyerId}
                            >
                                <SelectTrigger aria-label="Yurist">
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
                            disabled={!lawyerId || addForm.processing}
                        >
                            <UserPlus /> Qo'shish
                        </Button>
                    )}
                </form>
            )}
        </div>
    );
}

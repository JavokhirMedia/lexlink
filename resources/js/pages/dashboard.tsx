import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";
import { ArrowRight, PenLine } from "lucide-react";
import type { ReactNode } from "react";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { dashboard } from "@/routes";
import { index as billingIndex } from "@/routes/billing";
import type {
    DataRequestStatus,
    DataRequestType,
    RequestStatusUpdatedEvent,
} from "@/types/lexlink";
import { formatDateTime, formatMoney, formatNumber } from "@/components/brand/money";

type RequestRow = {
    id: number;
    case_id: number;
    case_title: string | null;
    type: DataRequestType;
    type_label: string;
    agency: string;
    status: DataRequestStatus;
    status_label: string;
    subject_pinfl_masked: string | null;
    created_by_name: string | null;
    cost: number;
    due_at?: string | null;
    updated_at: string;
};

type CaseRow = {
    id: number;
    title: string;
    client_name: string;
    court_case_number: string | null;
    requests_count: number;
    in_flight_count: number;
    updated_at: string;
};

type AdvocateRow = {
    id: number;
    name: string;
    bureau_name: string | null;
    license_status: string | null;
};

type Props = {
    role: "advocate" | "lawyer" | "admin" | null;
    kpi: {
        active_cases: number;
        awaiting_signature: number;
        in_flight: number;
        used_requests: number;
        included_requests: number;
        plan_name: string | null;
        balance: number;
    };
    active_cases: CaseRow[];
    recent: RequestRow[];
    in_flight: RequestRow[];
    awaiting_signature?: RequestRow[];
    my_drafts?: RequestRow[];
    advocates?: AdvocateRow[];
};

const nf = { format: (n: number) => formatNumber(n) };
const rtf = new Intl.RelativeTimeFormat("uz", { numeric: "auto" });

function ago(iso: string): string {
    const diff = (new Date(iso).getTime() - Date.now()) / 1000;
    const abs = Math.abs(diff);

    if (abs < 60) {
        return rtf.format(Math.round(diff), "second");
    }

    if (abs < 3600) {
        return rtf.format(Math.round(diff / 60), "minute");
    }

    if (abs < 86400) {
        return rtf.format(Math.round(diff / 3600), "hour");
    }

    return formatDateTime(iso);
}

const statusTone: Record<DataRequestStatus, string> = {
    draft: "border-border text-muted-foreground",
    awaiting_signature:
        "border-amber-500/60 text-amber-800 dark:text-amber-300",
    submitted: "border-sky-500/60 text-sky-800 dark:text-sky-300",
    processing: "border-sky-500/60 text-sky-800 dark:text-sky-300",
    completed: "border-emerald-600/60 text-emerald-800 dark:text-emerald-300",
    rejected: "border-red-500/60 text-red-700 dark:text-red-300",
    failed: "border-red-500/60 text-red-700 dark:text-red-300",
};

function StatusBadge({
    status,
    label,
}: {
    status: DataRequestStatus;
    label: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn("font-normal", statusTone[status])}
        >
            {label}
        </Badge>
    );
}

export default function Dashboard(props: Props) {
    const { auth } = usePage().props;
    const isAdvocate = props.role === "advocate";

    useEcho<RequestStatusUpdatedEvent>(
        `user.${auth.user.id}`,
        ".RequestStatusUpdated",
        () => {
            router.reload();
        },
    );

    const { kpi } = props;

    return (
        <>
            <Head title="Bosh sahifa" />
            <div className="flex w-full flex-col gap-8">
                <header className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Bosh sahifa
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {auth.user.name}
                            {auth.user.role_label
                                ? ` · ${auth.user.role_label}`
                                : ""}
                            {kpi.plan_name ? ` · ${kpi.plan_name} tarifi` : ""}
                        </p>
                    </div>
                </header>

                <dl className="grid grid-cols-2 divide-y rounded-lg border bg-card sm:grid-cols-3 sm:divide-y-0 lg:grid-cols-5 lg:divide-x">
                    <Kpi
                        label="Faol ishlar"
                        value={nf.format(kpi.active_cases)}
                        href="/cases"
                    />
                    <Kpi
                        label={
                            isAdvocate ? "Imzo kutilmoqda" : "Imzoga yuborilgan"
                        }
                        value={nf.format(kpi.awaiting_signature)}
                        accent={kpi.awaiting_signature > 0}
                    />
                    <Kpi label="Jarayonda" value={nf.format(kpi.in_flight)} />
                    <Kpi
                        label="Bu oy so'rovlar"
                        value={`${kpi.used_requests} / ${kpi.included_requests}`}
                        href={billingIndex().url}
                    />
                    <Kpi
                        label="Balans"
                        value={formatMoney(kpi.balance)}
                        href={billingIndex().url}
                    />
                </dl>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                    <div className="flex flex-col gap-6">
                        {isAdvocate ? (
                            <Panel
                                title="Imzongizni kutayotgan so'rovlar"
                                count={props.awaiting_signature?.length}
                                empty="Imzo kutayotgan so'rovlar yo'q."
                            >
                                {props.awaiting_signature?.map((r) => (
                                    <li
                                        key={r.id}
                                        className="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {r.type_label}
                                                <span className="font-normal text-muted-foreground">
                                                    {" "}
                                                    · {r.case_title}
                                                </span>
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {r.agency}
                                                {r.subject_pinfl_masked
                                                    ? ` · JShShIR ${r.subject_pinfl_masked}`
                                                    : ""}
                                                {r.created_by_name
                                                    ? ` · tayyorladi: ${r.created_by_name}`
                                                    : ""}
                                            </p>
                                        </div>
                                        <Button size="sm" asChild>
                                            <Link href={`/requests/${r.id}`}>
                                                <PenLine />
                                                Imzolash
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </Panel>
                        ) : (
                            <Panel
                                title="Mening loyihalarim"
                                count={props.my_drafts?.length}
                                empty="Loyihalar yo'q. Ish sahifasidan so'rov loyihasini yarating."
                            >
                                {props.my_drafts?.map((r) => (
                                    <RequestLine key={r.id} r={r} />
                                ))}
                            </Panel>
                        )}

                        <Panel
                            title="So'nggi javoblar"
                            count={props.recent.length}
                            empty="Hozircha javoblar yo'q."
                        >
                            {props.recent.map((r) => (
                                <RequestLine key={r.id} r={r} />
                            ))}
                        </Panel>

                        {props.in_flight.length > 0 && (
                            <Panel
                                title="Idorada ko'rib chiqilmoqda"
                                count={props.in_flight.length}
                            >
                                {props.in_flight.map((r) => (
                                    <RequestLine key={r.id} r={r} />
                                ))}
                            </Panel>
                        )}
                    </div>

                    <div className="flex flex-col gap-6">
                        <Panel
                            title={
                                isAdvocate
                                    ? "Faol ishlar"
                                    : "Men ishtirok etayotgan ishlar"
                            }
                            count={props.active_cases.length}
                            empty="Faol ishlar yo'q."
                            action={
                                <Link
                                    href="/cases"
                                    className="text-xs text-primary hover:underline"
                                >
                                    Barchasi
                                </Link>
                            }
                        >
                            {props.active_cases.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={`/cases/${c.id}`}
                                        className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-muted/50"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {c.title}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {c.client_name}
                                                {c.court_case_number
                                                    ? ` · № ${c.court_case_number}`
                                                    : ""}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                            <p>{c.requests_count} so'rov</p>
                                            {c.in_flight_count > 0 && (
                                                <p className="text-sky-700 dark:text-sky-300">
                                                    {c.in_flight_count}{" "}
                                                    jarayonda
                                                </p>
                                            )}
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </Panel>

                        {!isAdvocate && (
                            <Panel
                                title="Advokatlarim"
                                count={props.advocates?.length}
                                empty="Siz hali biror advokat jamoasiga qo'shilmagansiz."
                            >
                                {props.advocates?.map((a) => (
                                    <li
                                        key={a.id}
                                        className="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {a.name}
                                            </p>
                                            {a.bureau_name && (
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {a.bureau_name}
                                                </p>
                                            )}
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                "font-normal",
                                                a.license_status === "active"
                                                    ? "border-emerald-600/60 text-emerald-800 dark:text-emerald-300"
                                                    : "border-red-500/60 text-red-700 dark:text-red-300",
                                            )}
                                        >
                                            {a.license_status === "active"
                                                ? "Litsenziya faol"
                                                : "Litsenziya to'xtatilgan"}
                                        </Badge>
                                    </li>
                                ))}
                            </Panel>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Kpi({
    label,
    value,
    href,
    accent,
}: {
    label: string;
    value: string;
    href?: string;
    accent?: boolean;
}) {
    const body = (
        <>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    "mt-1 text-xl font-semibold tabular-nums",
                    accent && "text-amber-700 dark:text-amber-400",
                )}
            >
                {value}
            </dd>
        </>
    );

    return href ? (
        <Link href={href} className="block px-4 py-3 hover:bg-muted/50">
            {body}
        </Link>
    ) : (
        <div className="px-4 py-3">{body}</div>
    );
}

function Panel({
    title,
    count,
    empty,
    action,
    children,
}: {
    title: string;
    count?: number;
    empty?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-lg border bg-card">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <h2 className="text-sm font-semibold">
                    {title}
                    {count ? (
                        <span className="ml-2 font-normal text-muted-foreground tabular-nums">
                            {count}
                        </span>
                    ) : null}
                </h2>
                {action}
            </div>
            {count ? (
                <ul className="divide-y">{children}</ul>
            ) : (
                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                    {empty}
                </p>
            )}
        </section>
    );
}

function RequestLine({ r }: { r: RequestRow }) {
    return (
        <li>
            <Link
                href={`/requests/${r.id}`}
                className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-muted/50"
            >
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {r.type_label}
                        <span className="font-normal text-muted-foreground">
                            {" "}
                            · {r.case_title}
                        </span>
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {r.agency} · {ago(r.updated_at)}
                    </p>
                    {r.due_at && (
                        <DeadlineChip
                            dueAt={r.due_at}
                            status={r.status}
                            compact
                            className="mt-1"
                        />
                    )}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <StatusBadge status={r.status} label={r.status_label} />
                    <ArrowRight
                        className="size-4 text-muted-foreground"
                        aria-hidden
                    />
                </div>
            </Link>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: "Bosh sahifa", href: dashboard() }],
};

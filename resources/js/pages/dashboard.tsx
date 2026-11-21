import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";
import {
    ChevronRight,
    FileCheck2,
    FolderOpen,
    Inbox,
    PenLine,
    Users,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import {
    formatDateTime,
    formatMoney,
    formatNumber,
} from "@/components/brand/money";
import { EmptyState } from "@/components/brand/empty-state";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import { StatTile } from "@/components/brand/stat-tile";
import { StatusBadge } from "@/components/brand/status-badge";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { requestTypeIcon } from "@/components/requests/request-type-icon";
import { Button } from "@/components/ui/button";
import { dashboard } from "@/routes";
import { index as billingIndex } from "@/routes/billing";
import type {
    DataRequestStatus,
    DataRequestType,
    RequestStatusUpdatedEvent,
} from "@/types/lexlink";

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
    const description = [
        auth.user.name,
        auth.user.role_label,
        kpi.plan_name ? `${kpi.plan_name} tarifi` : null,
    ]
        .filter(Boolean)
        .join(" · ");

    const signList = isAdvocate
        ? (props.awaiting_signature ?? [])
        : (props.my_drafts ?? []);

    return (
        <>
            <Head title="Bosh sahifa" />
            <div className="flex flex-col gap-8">
                <PageHeader title="Bosh sahifa" description={description} />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]">
                    <StatTile
                        label={
                            isAdvocate ? "Imzo kutilmoqda" : "Imzoga yuborilgan"
                        }
                        value={formatNumber(kpi.awaiting_signature)}
                        hint={
                            isAdvocate
                                ? kpi.awaiting_signature > 0
                                    ? "Yurist loyihalari imzongizni kutmoqda"
                                    : "Hammasi imzolangan"
                                : "Advokat imzosini kutmoqda"
                        }
                        tone={
                            isAdvocate && kpi.awaiting_signature > 0
                                ? "warning"
                                : "default"
                        }
                        emphasis={isAdvocate && kpi.awaiting_signature > 0}
                        className="col-span-2 lg:col-span-1"
                    />
                    <StatTile
                        label="Idorada"
                        value={formatNumber(kpi.in_flight)}
                        hint="Javob kutilmoqda"
                    />
                    <StatTile
                        label="Faol ishlar"
                        value={formatNumber(kpi.active_cases)}
                        hint="Barcha ishlar"
                        href="/cases"
                    />
                    <StatTile
                        label="Bu oy so'rovlar"
                        value={
                            <>
                                {formatNumber(kpi.used_requests)}
                                <span className="text-xl font-normal text-ink-muted">
                                    {" "}
                                    / {formatNumber(kpi.included_requests)}
                                </span>
                            </>
                        }
                        hint={`Balans ${formatMoney(kpi.balance)}`}
                        href={billingIndex().url}
                        className="col-span-2 sm:col-span-1"
                    />
                </div>

                <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <div className="flex min-w-0 flex-col gap-8">
                        <ListSection
                            title={
                                isAdvocate
                                    ? "Imzongizni kutayotgan so'rovlar"
                                    : "Mening loyihalarim"
                            }
                            count={signList.length}
                            empty={
                                <EmptyState
                                    bordered={false}
                                    className="py-8"
                                    icon={isAdvocate ? PenLine : FileCheck2}
                                    title={
                                        isAdvocate
                                            ? "Imzo kutayotgan so'rovlar yo'q"
                                            : "Loyihalar yo'q"
                                    }
                                    description={
                                        isAdvocate
                                            ? "Yurist loyiha yuborsa, shu yerda ko'rinadi"
                                            : "So'rov loyihasi ish sahifasidan yaratiladi"
                                    }
                                    action={
                                        isAdvocate ? undefined : (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link href="/cases">
                                                    Ishlarga o'tish
                                                </Link>
                                            </Button>
                                        )
                                    }
                                />
                            }
                        >
                            {signList.map((r) =>
                                isAdvocate ? (
                                    <RequestLine
                                        key={r.id}
                                        r={r}
                                        meta={
                                            r.created_by_name
                                                ? `Tayyorladi: ${r.created_by_name}`
                                                : r.agency
                                        }
                                        action={
                                            <Button size="sm" asChild>
                                                <Link
                                                    href={`/requests/${r.id}`}
                                                >
                                                    <PenLine />
                                                    Imzolash
                                                </Link>
                                            </Button>
                                        }
                                    />
                                ) : (
                                    <RequestLine key={r.id} r={r} linked />
                                ),
                            )}
                        </ListSection>

                        <ListSection
                            title="So'nggi javoblar"
                            count={props.recent.length}
                            empty={
                                <EmptyState
                                    bordered={false}
                                    className="py-8"
                                    icon={Inbox}
                                    title="Hozircha javoblar yo'q"
                                    description="Idora javob berganda shu yerda ko'rinadi"
                                />
                            }
                        >
                            {props.recent.map((r) => (
                                <RequestLine key={r.id} r={r} linked />
                            ))}
                        </ListSection>

                        {props.in_flight.length > 0 && (
                            <ListSection
                                title="Idorada ko'rib chiqilmoqda"
                                count={props.in_flight.length}
                            >
                                {props.in_flight.map((r) => (
                                    <RequestLine key={r.id} r={r} linked />
                                ))}
                            </ListSection>
                        )}
                    </div>

                    <div className="flex min-w-0 flex-col gap-8">
                        <ListSection
                            title={
                                isAdvocate
                                    ? "Faol ishlar"
                                    : "Men ishtirok etayotgan ishlar"
                            }
                            count={props.active_cases.length}
                            actions={
                                <Button asChild variant="ghost" size="sm">
                                    <Link href="/cases">Barchasi</Link>
                                </Button>
                            }
                            empty={
                                <EmptyState
                                    bordered={false}
                                    className="py-8"
                                    icon={FolderOpen}
                                    title="Faol ishlar yo'q"
                                    action={
                                        isAdvocate ? (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link href="/cases">
                                                    Ish ochish
                                                </Link>
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        >
                            {props.active_cases.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={`/cases/${c.id}`}
                                        className="focus-ring flex min-h-14 items-center gap-3 px-4 py-3 transition-colors hover:bg-page"
                                    >
                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            <p className="truncate text-base font-medium text-ink">
                                                {c.title}
                                            </p>
                                            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-secondary">
                                                <span className="truncate">
                                                    {c.client_name}
                                                </span>
                                                {c.court_case_number && (
                                                    <RegistryChip
                                                        value={
                                                            c.court_case_number
                                                        }
                                                        kind="case"
                                                        size="sm"
                                                    />
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end text-meta tabular-nums">
                                            <span className="text-ink-secondary">
                                                {formatNumber(c.requests_count)}{" "}
                                                so'rov
                                            </span>
                                            {c.in_flight_count > 0 && (
                                                <span className="text-brand">
                                                    {formatNumber(
                                                        c.in_flight_count,
                                                    )}{" "}
                                                    idorada
                                                </span>
                                            )}
                                        </div>
                                        <ChevronRight
                                            aria-hidden
                                            strokeWidth={1.5}
                                            className="size-4 shrink-0 text-ink-muted"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ListSection>

                        {!isAdvocate && (
                            <ListSection
                                title="Advokatlarim"
                                count={props.advocates?.length ?? 0}
                                empty={
                                    <EmptyState
                                        bordered={false}
                                        className="py-8"
                                        icon={Users}
                                        title="Siz hali advokat jamoasida emassiz"
                                        description="Advokat yuborgan taklif havolasini oching"
                                    />
                                }
                            >
                                {props.advocates?.map((a) => (
                                    <li
                                        key={a.id}
                                        className="flex min-h-14 items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-base font-medium text-ink">
                                                {a.name}
                                            </p>
                                            {a.bureau_name && (
                                                <p className="truncate text-sm text-ink-secondary">
                                                    {a.bureau_name}
                                                </p>
                                            )}
                                        </div>
                                        <span
                                            className={
                                                a.license_status === "active"
                                                    ? "inline-flex h-6 shrink-0 items-center rounded-sm bg-success-soft px-2 text-xs font-medium text-success"
                                                    : "inline-flex h-6 shrink-0 items-center rounded-sm bg-danger-soft px-2 text-xs font-medium text-danger"
                                            }
                                        >
                                            {a.license_status === "active"
                                                ? "Litsenziya faol"
                                                : "Litsenziya to'xtatilgan"}
                                        </span>
                                    </li>
                                ))}
                            </ListSection>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function ListSection({
    title,
    count,
    actions,
    empty,
    children,
}: {
    title: string;
    count: number;
    actions?: ReactNode;
    empty?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Section
            title={
                <>
                    {title}
                    {count > 0 && (
                        <span className="ml-2 font-normal text-ink-muted tabular-nums">
                            {formatNumber(count)}
                        </span>
                    )}
                </>
            }
            actions={actions}
            card
            contentClassName="overflow-hidden p-0"
        >
            {count > 0 ? <ul className="divide-y">{children}</ul> : empty}
        </Section>
    );
}

function TypeIcon({ type }: { type: string }) {
    const Icon: LucideIcon = requestTypeIcon(type);

    return (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-raised text-ink-secondary">
            <Icon aria-hidden strokeWidth={1.5} className="size-[18px]" />
        </span>
    );
}

function RequestLine({
    r,
    meta,
    action,
    linked = false,
}: {
    r: RequestRow;
    meta?: string;
    action?: ReactNode;
    linked?: boolean;
}) {
    const body = (
        <>
            <TypeIcon type={r.type} />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="truncate text-base font-medium text-ink">
                    {r.type_label}
                </p>
                <p className="line-clamp-2 text-sm text-ink-secondary sm:line-clamp-1">
                    {r.case_title ?? "Ishsiz"} ·{" "}
                    {meta ?? `${r.agency} · ${ago(r.updated_at)}`}
                </p>
                {(r.subject_pinfl_masked || r.due_at) && (
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        {r.subject_pinfl_masked && (
                            <RegistryChip
                                value={r.subject_pinfl_masked}
                                kind="pinfl"
                                size="sm"
                            />
                        )}
                        {r.due_at && (
                            <DeadlineChip
                                dueAt={r.due_at}
                                status={r.status}
                                compact
                            />
                        )}
                    </div>
                )}
            </div>
            <div className="flex shrink-0 items-center gap-3 self-start sm:self-center">
                {action ?? (
                    <>
                        <StatusBadge
                            status={r.status}
                            label={r.status_label}
                            size="sm"
                        />
                        <ChevronRight
                            aria-hidden
                            strokeWidth={1.5}
                            className="hidden size-4 text-ink-muted sm:block"
                        />
                    </>
                )}
            </div>
        </>
    );

    const classes = "flex items-start gap-3 px-4 py-3 sm:items-center";

    return (
        <li>
            {linked ? (
                <Link
                    href={`/requests/${r.id}`}
                    className={`focus-ring ${classes} transition-colors hover:bg-page`}
                >
                    {body}
                </Link>
            ) : (
                <div className={classes}>{body}</div>
            )}
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: "Bosh sahifa", href: dashboard() }],
};

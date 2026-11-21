import { Head, Link, router } from "@inertiajs/react";
import { Inbox } from "lucide-react";
import { EmptyState } from "@/components/brand/empty-state";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { formatDate, formatNumber } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { StatusBadge } from "@/components/brand/status-badge";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { RequestTypeIcon } from "@/components/requests/request-type-icon";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { index as requestsIndex, show as requestShow } from "@/routes/requests";
import type {
    DataRequest,
    DataRequestStatus,
    DataRequestType,
    RequestDeadlineFields,
} from "@/types/lexlink";

type Row = DataRequest &
    Partial<RequestDeadlineFields> & {
        case: {
            id: number;
            title: string;
            court_case_number: string | null;
        } | null;
    };

type Props = {
    requests: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { status: DataRequestStatus | null; type: DataRequestType | null };
    statuses: { value: DataRequestStatus; label: string }[];
    types: { value: DataRequestType; label: string; agency: string }[];
};

const ALL = "all";

/** Mirrors DeadlineChip: open requests, or answered ones with an on-time verdict. */
const showsDeadline = (r: Row) =>
    !!r.due_at &&
    (r.status === "submitted" ||
        r.status === "processing" ||
        (r.status === "completed" &&
            r.answered_on_time !== null &&
            r.answered_on_time !== undefined));

export default function RequestsIndex({
    requests,
    filters,
    statuses,
    types,
}: Props) {
    const apply = (next: Partial<Props["filters"]>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = {};
        if (merged.status) query.status = merged.status;
        if (merged.type) query.type = merged.type;
        router.get(
            requestsIndex.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    const filtered = !!(filters.status || filters.type);

    return (
        <>
            <Head title="So'rovlar" />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title="So'rovlar"
                    description="Ishlaringiz bo'yicha idoralarga yuborilgan so'rovlar. Yangi so'rov ish sahifasidan yaratiladi"
                />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="flex flex-col gap-2 sm:w-64">
                        <Label htmlFor="filter-status">Holat</Label>
                        <Select
                            value={filters.status ?? ALL}
                            onValueChange={(v) =>
                                apply({
                                    status:
                                        v === ALL
                                            ? null
                                            : (v as DataRequestStatus),
                                })
                            }
                        >
                            <SelectTrigger id="filter-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Barchasi</SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col gap-2 sm:w-72">
                        <Label htmlFor="filter-type">Ma'lumot turi</Label>
                        <Select
                            value={filters.type ?? ALL}
                            onValueChange={(v) =>
                                apply({
                                    type:
                                        v === ALL
                                            ? null
                                            : (v as DataRequestType),
                                })
                            }
                        >
                            <SelectTrigger id="filter-type" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Barchasi</SelectItem>
                                {types.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {filtered && (
                        <Button
                            variant="ghost"
                            onClick={() => apply({ status: null, type: null })}
                        >
                            Filtrni tozalash
                        </Button>
                    )}
                    <p className="text-xs text-ink-muted tabular-nums sm:ml-auto sm:pb-3">
                        Jami {formatNumber(requests.total)} ta
                    </p>
                </div>

                {requests.data.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title={
                            filtered
                                ? "Filtr bo'yicha so'rov topilmadi"
                                : "Hali so'rov yo'q"
                        }
                        description={
                            filtered
                                ? undefined
                                : "So'rov ish sahifasida, huquqiy asos qo'shilgandan keyin yaratiladi"
                        }
                    />
                ) : (
                    <>
                        {/* Desktop: table */}
                        <div className="hidden overflow-hidden rounded-lg border bg-card shadow-xs md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>So'rov</TableHead>
                                        <TableHead>Ish</TableHead>
                                        <TableHead>Shaxs</TableHead>
                                        <TableHead>Holat</TableHead>
                                        <TableHead>Javob muddati</TableHead>
                                        <TableHead className="text-right">
                                            Sana
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.data.map((r) => (
                                        <TableRow
                                            key={r.id}
                                            className="cursor-pointer"
                                            onClick={() =>
                                                router.visit(
                                                    requestShow.url(r.id),
                                                )
                                            }
                                        >
                                            <TableCell className="py-3">
                                                <div className="flex items-center gap-3">
                                                    <RequestTypeIcon
                                                        type={r.type}
                                                        className="size-4 shrink-0 text-ink-secondary"
                                                    />
                                                    <div className="flex min-w-0 flex-col">
                                                        <Link
                                                            href={requestShow.url(
                                                                r.id,
                                                            )}
                                                            className="focus-ring rounded-xs font-medium text-ink hover:underline"
                                                            onClick={(e) =>
                                                                e.stopPropagation()
                                                            }
                                                        >
                                                            {r.type_label}
                                                        </Link>
                                                        <span className="text-xs text-ink-secondary">
                                                            {r.agency}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-64 py-3">
                                                <div className="flex flex-col items-start gap-1">
                                                    <span className="line-clamp-1">
                                                        {r.case?.title ?? "—"}
                                                    </span>
                                                    {r.case
                                                        ?.court_case_number && (
                                                        <RegistryChip
                                                            value={
                                                                r.case
                                                                    .court_case_number
                                                            }
                                                            kind="case"
                                                            size="sm"
                                                        />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3">
                                                <div className="flex flex-col">
                                                    <span>
                                                        {r.subject_name ?? "—"}
                                                    </span>
                                                    <MaskedPinfl
                                                        value={
                                                            r.subject_pinfl_masked
                                                        }
                                                        className="text-xs"
                                                    />
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={r.status}
                                                    label={r.status_label}
                                                    size="sm"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <DeadlineChip
                                                    dueAt={r.due_at ?? null}
                                                    status={r.status}
                                                    answeredOnTime={
                                                        r.answered_on_time
                                                    }
                                                    compact
                                                />
                                                {!showsDeadline(r) && (
                                                    <span className="text-ink-muted">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right text-ink-secondary tabular-nums">
                                                {formatDate(
                                                    r.submitted_at ??
                                                        r.created_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Mobile: stacked rows */}
                        <ul className="divide-y overflow-hidden rounded-lg border bg-card md:hidden">
                            {requests.data.map((r) => (
                                <li key={r.id}>
                                    <Link
                                        href={requestShow.url(r.id)}
                                        className="flex items-start gap-3 px-4 py-4 transition-colors hover:bg-page"
                                    >
                                        <RequestTypeIcon
                                            type={r.type}
                                            className="mt-0.5 size-[18px] shrink-0 text-ink-secondary"
                                        />
                                        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                            <div className="flex items-start justify-between gap-2">
                                                <span className="font-medium text-ink">
                                                    {r.type_label}
                                                </span>
                                                <span className="shrink-0 text-xs text-ink-muted tabular-nums">
                                                    {formatDate(
                                                        r.submitted_at ??
                                                            r.created_at,
                                                    )}
                                                </span>
                                            </div>
                                            <span className="truncate text-sm text-ink-secondary">
                                                {r.case?.title ?? "—"}
                                            </span>
                                            <span className="flex flex-wrap items-center gap-x-2 text-xs text-ink-secondary">
                                                {r.subject_name}
                                                <MaskedPinfl
                                                    value={
                                                        r.subject_pinfl_masked
                                                    }
                                                />
                                            </span>
                                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                                <StatusBadge
                                                    status={r.status}
                                                    label={r.status_label}
                                                    size="sm"
                                                />
                                                <DeadlineChip
                                                    dueAt={r.due_at ?? null}
                                                    status={r.status}
                                                    answeredOnTime={
                                                        r.answered_on_time
                                                    }
                                                    compact
                                                />
                                            </div>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                {requests.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4 text-sm text-ink-secondary">
                        <span className="tabular-nums">
                            {requests.current_page}-sahifa,{" "}
                            {requests.last_page} tadan
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!requests.prev_page_url}
                                onClick={() =>
                                    requests.prev_page_url &&
                                    router.visit(requests.prev_page_url)
                                }
                            >
                                Oldingi
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!requests.next_page_url}
                                onClick={() =>
                                    requests.next_page_url &&
                                    router.visit(requests.next_page_url)
                                }
                            >
                                Keyingi
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

RequestsIndex.layout = {
    breadcrumbs: [{ title: "So'rovlar", href: requestsIndex() }],
};

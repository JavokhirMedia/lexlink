import { Head, Link, router } from "@inertiajs/react";
import { ChevronRight, Inbox } from "lucide-react";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { PageHeader } from "@/components/brand/page-header";
import { StatusBadge } from "@/components/brand/status-badge";
import { formatDateTime } from "@/components/requests/types";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { index as requestsIndex, show as requestShow } from "@/routes/requests";
import type {
    DataRequest,
    DataRequestStatus,
    DataRequestType,
} from "@/types/lexlink";

type Row = DataRequest & {
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

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                "rounded-md border px-2.5 py-1 text-xs transition-colors",
                active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "bg-background text-muted-foreground hover:bg-muted",
            )}
        >
            {children}
        </button>
    );
}

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

    return (
        <>
            <Head title="So'rovlar" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="So'rovlar"
                    description="Sizning ishlaringiz bo'yicha idoralarga yuborilgan barcha so'rovlar. Yangi so'rov ish sahifasidan yaratiladi."
                />

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="mr-1 text-xs text-muted-foreground">
                            Holat:
                        </span>
                        <Chip
                            active={!filters.status}
                            onClick={() => apply({ status: null })}
                        >
                            Barchasi
                        </Chip>
                        {statuses.map((s) => (
                            <Chip
                                key={s.value}
                                active={filters.status === s.value}
                                onClick={() => apply({ status: s.value })}
                            >
                                {s.label}
                            </Chip>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="mr-1 text-xs text-muted-foreground">
                            Turi:
                        </span>
                        <Chip
                            active={!filters.type}
                            onClick={() => apply({ type: null })}
                        >
                            Barchasi
                        </Chip>
                        {types.map((t) => (
                            <Chip
                                key={t.value}
                                active={filters.type === t.value}
                                onClick={() => apply({ type: t.value })}
                            >
                                {t.label}
                            </Chip>
                        ))}
                    </div>
                </div>

                {requests.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-md border border-dashed p-10 text-center text-sm text-muted-foreground">
                        <Inbox className="size-6" aria-hidden />
                        So'rovlar topilmadi.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">№</th>
                                    <th className="px-4 py-2 font-medium">
                                        Turi / idora
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Ish
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Shaxs
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Sana
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Holat
                                    </th>
                                    <th className="w-8" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {requests.data.map((r) => (
                                    <tr
                                        key={r.id}
                                        className="cursor-pointer hover:bg-muted/40"
                                        onClick={() =>
                                            router.visit(requestShow.url(r.id))
                                        }
                                    >
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            #{r.id}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={requestShow.url(r.id)}
                                                className="font-medium hover:underline"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                {r.type_label}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {r.agency}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="line-clamp-1">
                                                {r.case?.title ?? "—"}
                                            </p>
                                            {r.case?.court_case_number && (
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {r.case.court_case_number}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p>{r.subject_name ?? "—"}</p>
                                            <MaskedPinfl
                                                value={r.subject_pinfl_masked}
                                                className="text-xs"
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                            {formatDateTime(
                                                r.submitted_at ?? r.created_at,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={r.status}
                                                label={r.status_label}
                                            />
                                        </td>
                                        <td className="pr-3">
                                            <ChevronRight
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {requests.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Jami {requests.total} ta · {requests.current_page}/
                            {requests.last_page}-sahifa
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

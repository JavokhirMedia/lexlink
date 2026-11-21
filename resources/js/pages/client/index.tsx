import { Head, Link } from "@inertiajs/react";
import { ChevronRight, FolderOpen } from "lucide-react";
import { EmptyState } from "@/components/brand/empty-state";
import { formatNumber } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import ClientLayout from "@/components/client/client-layout";
import { ClientNote } from "@/components/client/client-note";
import { show as clientCaseShow } from "@/routes/client/cases";
import type { ClientCaseInfo } from "@/types/lexlink";

type Props = {
    cases: (ClientCaseInfo & {
        requests_count: number;
        granted_at: string | null;
    })[];
    note: string;
};

export default function ClientIndex({ cases, note }: Props) {
    return (
        <>
            <Head title="Mening ishlarim" />
            <PageHeader
                title="Mening ishlarim"
                description="Advokatingiz advokat orderi asosida sizga ochgan ishlar"
            />

            {cases.length === 0 ? (
                <EmptyState
                    icon={FolderOpen}
                    title="Hozircha sizga ochilgan ish yo'q"
                    description="Advokatingiz yuborgan kirish havolasini oching"
                />
            ) : (
                <ul className="flex flex-col divide-y divide-hairline rounded-lg border bg-card shadow-xs">
                    {cases.map((c) => (
                        <li key={c.id}>
                            <Link
                                href={clientCaseShow.url(c.id)}
                                className="focus-ring group flex items-center justify-between gap-4 px-6 py-4 transition-colors first:rounded-t-lg last:rounded-b-lg hover:bg-page"
                            >
                                <div className="flex min-w-0 flex-col gap-2">
                                    <p className="text-base font-medium text-ink">
                                        {c.title}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-secondary">
                                        <span>
                                            Advokat:{" "}
                                            <span className="text-ink">
                                                {c.advocate.name ?? "—"}
                                            </span>
                                        </span>
                                        {c.advocate.license_number && (
                                            <RegistryChip
                                                value={c.advocate.license_number}
                                                kind="license"
                                                size="sm"
                                            />
                                        )}
                                        <RegistryChip
                                            value={c.order_number}
                                            kind="order"
                                            size="sm"
                                        />
                                        <span className="tabular-nums">
                                            {formatNumber(c.requests_count)} ta
                                            so'rov
                                        </span>
                                    </div>
                                </div>
                                <ChevronRight
                                    className="size-[18px] shrink-0 text-ink-muted transition-colors group-hover:text-ink"
                                    strokeWidth={1.5}
                                    aria-hidden
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <ClientNote>{note}</ClientNote>
        </>
    );
}

ClientIndex.layout = ClientLayout;

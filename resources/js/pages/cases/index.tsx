import { Head, Link, router } from "@inertiajs/react";
import { Briefcase, ChevronRight, Plus, Search } from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";
import { EmptyState } from "@/components/brand/empty-state";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import {
    create as casesCreate,
    index as casesIndex,
    show as casesShow,
} from "@/routes/cases";
import type { DataRequestStatus, LegalCase } from "@/types/lexlink";

type CaseRow = LegalCase & {
    category_label: string | null;
    members: { id: number; name: string; role: "advocate" | "lawyer" }[];
    bases_count: number;
    request_counts: Partial<Record<DataRequestStatus, number>>;
};

type Props = {
    cases: CaseRow[];
    filters: { search: string; status: string | null };
    status_labels: Record<DataRequestStatus, string>;
    can_create: boolean;
};

const STATUS_ORDER: DataRequestStatus[] = [
    "draft",
    "awaiting_signature",
    "submitted",
    "processing",
    "completed",
    "rejected",
    "failed",
];

/** A small dot carries the status tone; the count stays quiet text. */
const DOT: Record<DataRequestStatus, string> = {
    draft: "bg-ink-muted",
    awaiting_signature: "bg-warning",
    submitted: "bg-brand",
    processing: "bg-brand",
    completed: "bg-success",
    rejected: "bg-danger",
    failed: "bg-danger",
};

const STATUS_FILTERS = [
    { value: null, label: "Barchasi" },
    { value: "active", label: "Faol" },
    { value: "closed", label: "Yopilgan" },
] as const;

/** "Rustamova Dilnoza Akmalovna" -> "Rustamova D. A." */
const shortName = (name: string) => {
    const [last, ...rest] = name.trim().split(/\s+/);

    return rest.length
        ? `${last} ${rest.map((p) => `${p[0]}.`).join(" ")}`
        : last;
};

const GRID =
    "lg:grid lg:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_176px_minmax(0,1.2fr)_minmax(0,1.5fr)_16px] lg:items-center lg:gap-6";

export default function CasesIndex({
    cases,
    filters,
    status_labels,
    can_create,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");

    const apply = (next: { search?: string; status?: string | null }) => {
        const query: Record<string, string> = {};
        const s = next.search ?? search;
        const st = next.status === undefined ? filters.status : next.status;
        if (s) query.search = s;
        if (st) query.status = st;
        router.get(
            casesIndex.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        apply({ search });
    };

    return (
        <>
            <Head title="Ishlar" />
            <div className="flex w-full flex-col gap-8">
                <PageHeader
                    title="Ishlar"
                    description="Siz a'zo bo'lgan ishlar, ularning huquqiy asoslari va idoralarga so'rovlari"
                    actions={
                        can_create && (
                            <Button asChild>
                                <Link href={casesCreate()}>
                                    <Plus /> Yangi ish
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form
                        onSubmit={onSubmit}
                        className="relative flex-1"
                        role="search"
                    >
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-[18px] -translate-y-1/2 text-ink-muted"
                            strokeWidth={1.5}
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Ish nomi, mijoz yoki sud ishi raqami"
                            className="pl-10"
                            aria-label="Qidirish"
                        />
                    </form>
                    <div
                        className="inline-flex h-11 w-fit shrink-0 items-center gap-1 rounded-md bg-raised p-1"
                        role="radiogroup"
                        aria-label="Ish holati"
                    >
                        {STATUS_FILTERS.map((o) => {
                            const active = (filters.status ?? null) === o.value;

                            return (
                                <button
                                    key={o.label}
                                    type="button"
                                    role="radio"
                                    aria-checked={active}
                                    onClick={() => apply({ status: o.value })}
                                    className={cn(
                                        "focus-ring h-9 rounded-sm px-3 text-sm transition-colors",
                                        active
                                            ? "bg-surface font-medium text-ink shadow-xs"
                                            : "text-ink-secondary hover:text-ink",
                                    )}
                                >
                                    {o.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {cases.length === 0 ? (
                    <EmptyState
                        icon={Briefcase}
                        title={
                            filters.search
                                ? "Qidiruv bo'yicha mos ish yo'q"
                                : "Hali ish yo'q"
                        }
                        description={
                            filters.search
                                ? undefined
                                : "Ishni yarating, so'ng huquqiy asos qo'shib idoralarga so'rov yuborasiz"
                        }
                        action={
                            can_create && !filters.search ? (
                                <Button asChild variant="outline">
                                    <Link href={casesCreate()}>
                                        <Plus /> Yangi ish
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
                        <div
                            className={cn(
                                GRID,
                                "hidden h-10 border-b bg-raised px-6 text-xs font-medium text-ink-secondary",
                            )}
                            aria-hidden
                        >
                            <span>Ish</span>
                            <span>Mijoz</span>
                            <span>Sud ishi raqami</span>
                            <span>Jamoa</span>
                            <span>Asoslar va so'rovlar</span>
                            <span />
                        </div>
                        <ul className="divide-y">
                            {cases.map((c) => {
                                const counts = STATUS_ORDER.filter(
                                    (s) => (c.request_counts[s] ?? 0) > 0,
                                );
                                const members = c.members
                                    .map((m) => shortName(m.name))
                                    .join(", ");

                                return (
                                    <li key={c.id}>
                                        <Link
                                            href={casesShow(c.id)}
                                            className={cn(
                                                GRID,
                                                "focus-ring flex flex-col gap-2 px-4 py-4 transition-colors hover:bg-page sm:px-6",
                                            )}
                                        >
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="flex items-center gap-2">
                                                    <span className="truncate text-base font-medium text-ink">
                                                        {c.title}
                                                    </span>
                                                    {c.status === "closed" && (
                                                        <span className="shrink-0 rounded-sm bg-raised px-1.5 text-xs text-ink-secondary">
                                                            Yopilgan
                                                        </span>
                                                    )}
                                                </span>
                                                {c.category_label && (
                                                    <span className="truncate text-xs text-ink-muted">
                                                        {c.category_label}
                                                    </span>
                                                )}
                                            </div>
                                            <span className="truncate text-sm text-ink-secondary lg:text-ink">
                                                <span className="lg:hidden">
                                                    Mijoz:{" "}
                                                </span>
                                                {c.client_name}
                                            </span>
                                            <span className="flex">
                                                {c.court_case_number ? (
                                                    <RegistryChip
                                                        value={
                                                            c.court_case_number
                                                        }
                                                        kind="case"
                                                        size="sm"
                                                    />
                                                ) : (
                                                    <span className="hidden text-ink-muted lg:inline">
                                                        —
                                                    </span>
                                                )}
                                            </span>
                                            <span
                                                className="truncate text-sm text-ink-secondary"
                                                title={c.members
                                                    .map((m) => m.name)
                                                    .join(", ")}
                                            >
                                                {members}
                                            </span>
                                            <span className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-secondary tabular-nums">
                                                <span>
                                                    {c.bases_count} asos
                                                </span>
                                                {counts.length === 0 ? (
                                                    <span className="text-ink-muted">
                                                        so'rov yo'q
                                                    </span>
                                                ) : (
                                                    counts.map((s) => (
                                                        <span
                                                            key={s}
                                                            className="inline-flex items-center gap-1.5"
                                                        >
                                                            <span
                                                                className={cn(
                                                                    "size-1.5 rounded-full",
                                                                    DOT[s],
                                                                )}
                                                                aria-hidden
                                                            />
                                                            {status_labels[s]}{" "}
                                                            {
                                                                c
                                                                    .request_counts[
                                                                    s
                                                                ]
                                                            }
                                                        </span>
                                                    ))
                                                )}
                                            </span>
                                            <ChevronRight
                                                className="hidden size-4 text-ink-muted lg:block"
                                                strokeWidth={1.5}
                                                aria-hidden
                                            />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}

CasesIndex.layout = {
    breadcrumbs: [{ title: "Ishlar", href: casesIndex() }],
};

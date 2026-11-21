import { Head, Link, router } from "@inertiajs/react";
import { Briefcase, Plus, Search, Users } from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";
import { Badge } from "@/components/ui/badge";
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

const requestStatusTone: Record<DataRequestStatus, string> = {
    draft: "bg-muted text-muted-foreground",
    awaiting_signature:
        "bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300",
    submitted: "bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-300",
    processing: "bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-300",
    completed:
        "bg-emerald-100 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-300",
    rejected: "bg-red-100 text-red-900 dark:bg-red-500/15 dark:text-red-300",
    failed: "bg-red-100 text-red-900 dark:bg-red-500/15 dark:text-red-300",
};

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
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Ishlar
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Siz a'zo bo'lgan ishlar, huquqiy asoslar va
                            idoralarga so'rovlar.
                        </p>
                    </div>
                    {can_create && (
                        <Button asChild>
                            <Link href={casesCreate()}>
                                <Plus /> Yangi ish
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form onSubmit={onSubmit} className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Ish nomi, mijoz yoki sud ishi raqami bo'yicha qidirish"
                            className="pl-9"
                            aria-label="Qidirish"
                        />
                    </form>
                    <div
                        className="flex gap-1 rounded-md border p-1"
                        role="tablist"
                        aria-label="Holat"
                    >
                        {[
                            { value: null, label: "Barchasi" },
                            { value: "active", label: "Faol" },
                            { value: "closed", label: "Yopilgan" },
                        ].map((o) => (
                            <button
                                key={o.label}
                                type="button"
                                role="tab"
                                aria-selected={filters.status === o.value}
                                onClick={() => apply({ status: o.value })}
                                className={cn(
                                    "rounded px-3 py-1 text-sm transition-colors",
                                    (filters.status ?? null) === o.value
                                        ? "bg-primary text-primary-foreground"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                {o.label}
                            </button>
                        ))}
                    </div>
                </div>

                {cases.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-16 text-center">
                        <Briefcase className="size-8 text-muted-foreground" />
                        <div>
                            <p className="font-medium">Ishlar topilmadi</p>
                            <p className="text-sm text-muted-foreground">
                                {filters.search
                                    ? "Qidiruv bo'yicha mos ish yo'q."
                                    : "Birinchi ishni yarating — so'ng huquqiy asos qo'shib, idoralarga so'rov yuborasiz."}
                            </p>
                        </div>
                        {can_create && !filters.search && (
                            <Button asChild variant="outline">
                                <Link href={casesCreate()}>
                                    <Plus /> Yangi ish
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border bg-card">
                        {cases.map((c) => {
                            const counts = STATUS_ORDER.filter(
                                (s) => (c.request_counts[s] ?? 0) > 0,
                            );
                            return (
                                <li key={c.id}>
                                    <Link
                                        href={casesShow(c.id)}
                                        className="flex flex-col gap-3 px-4 py-4 transition-colors hover:bg-muted/50 md:flex-row md:items-center md:gap-6"
                                    >
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="truncate font-medium">
                                                    {c.title}
                                                </span>
                                                {c.status === "closed" ? (
                                                    <Badge variant="secondary">
                                                        Yopilgan
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Faol
                                                    </Badge>
                                                )}
                                                {c.category_label && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {c.category_label}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                                <span>
                                                    Mijoz: {c.client_name}
                                                </span>
                                                {c.court_case_number && (
                                                    <span className="font-mono text-xs leading-5">
                                                        {c.court_case_number}
                                                    </span>
                                                )}
                                                <span className="inline-flex items-center gap-1">
                                                    <Users className="size-3.5" />
                                                    {c.members
                                                        .map((m) => m.name)
                                                        .join(", ")}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1.5 md:justify-end">
                                            <span className="mr-1 text-xs text-muted-foreground">
                                                Asoslar: {c.bases_count}
                                            </span>
                                            {counts.length === 0 ? (
                                                <span className="text-xs text-muted-foreground">
                                                    So'rov yo'q
                                                </span>
                                            ) : (
                                                counts.map((s) => (
                                                    <span
                                                        key={s}
                                                        className={cn(
                                                            "rounded px-2 py-0.5 text-xs font-medium tabular-nums",
                                                            requestStatusTone[
                                                                s
                                                            ],
                                                        )}
                                                    >
                                                        {status_labels[s]}:{" "}
                                                        {c.request_counts[s]}
                                                    </span>
                                                ))
                                            )}
                                        </div>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

CasesIndex.layout = {
    breadcrumbs: [{ title: "Ishlar", href: casesIndex() }],
};

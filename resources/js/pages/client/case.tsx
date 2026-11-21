import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    BadgeCheck,
    CheckCircle2,
    Circle,
    Loader2,
    Lock,
    Sparkles,
    XCircle,
} from "lucide-react";
import { StatusBadge } from "@/components/brand/status-badge";
import ClientLayout from "@/components/client/client-layout";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { cn } from "@/lib/utils";
import { index as clientIndex } from "@/routes/client";
import type {
    ClientCaseInfo,
    ClientRequestStep,
    ClientRequestView,
} from "@/types/lexlink";

type Props = {
    case: ClientCaseInfo;
    requests: ClientRequestView[];
    note: string;
};

const dtFmt = new Intl.DateTimeFormat("uz-UZ", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
});
const fmt = (v: string | null) => (v ? dtFmt.format(new Date(v)) : null);

function Steps({ steps }: { steps: ClientRequestStep[] }) {
    return (
        <ol className="flex flex-col gap-3 sm:flex-row sm:gap-0">
            {steps.map((s, i) => {
                const Icon =
                    s.state === "done" || s.state === "success"
                        ? CheckCircle2
                        : s.state === "danger"
                          ? XCircle
                          : s.state === "current"
                            ? Loader2
                            : Circle;
                return (
                    <li
                        key={i}
                        className="flex flex-1 items-start gap-2 sm:flex-col sm:gap-1"
                    >
                        <div className="flex items-center gap-2 sm:w-full">
                            <Icon
                                className={cn(
                                    "size-4 shrink-0",
                                    s.state === "success" || s.state === "done"
                                        ? "text-emerald-600 dark:text-emerald-400"
                                        : s.state === "danger"
                                          ? "text-danger"
                                          : s.state === "current"
                                            ? "animate-spin text-primary"
                                            : "text-muted-foreground",
                                )}
                                aria-hidden
                            />
                            {i < steps.length - 1 && (
                                <span className="hidden h-px flex-1 bg-border sm:block" />
                            )}
                        </div>
                        <div className="text-sm">
                            <div
                                className={cn(
                                    s.state === "upcoming" &&
                                        "text-muted-foreground",
                                )}
                            >
                                {s.label}
                            </div>
                            {s.at && (
                                <div className="text-xs text-muted-foreground">
                                    {fmt(s.at)}
                                </div>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export default function ClientCase({ case: c, requests, note }: Props) {
    return (
        <>
            <Head title={c.title} />
            <Link
                href={clientIndex()}
                className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft className="size-4" /> Mening ishlarim
            </Link>

            <section className="rounded-lg border bg-card p-5">
                <h1 className="text-xl font-semibold tracking-tight">
                    {c.title}
                </h1>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-muted-foreground">Advokat</dt>
                        <dd className="flex items-center gap-1.5">
                            {c.advocate.name ?? "—"}
                            <BadgeCheck
                                className="size-4 text-primary"
                                aria-label="Litsenziya tasdiqlangan"
                            />
                        </dd>
                        <dd className="text-xs text-muted-foreground">
                            Litsenziya {c.advocate.license_number ?? "—"}
                            {c.advocate.bureau_name &&
                                ` · ${c.advocate.bureau_name}`}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Advokat orderi
                        </dt>
                        <dd className="font-mono">{c.order_number}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Sud ishi</dt>
                        <dd className="font-mono">
                            {c.court_case_number ?? "—"}
                        </dd>
                    </div>
                </dl>
            </section>

            <p className="flex items-start gap-2 rounded-md border border-primary/30 bg-primary/5 px-4 py-3 text-sm">
                <Lock className="mt-0.5 size-4 shrink-0 text-primary" />
                <span data-test="client-note">{note}</span>
            </p>

            <section className="space-y-3">
                <h2 className="font-medium">So'rovlar holati</h2>
                {requests.length === 0 ? (
                    <p className="rounded-lg border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        Advokatingiz hali davlat idoralariga so'rov yubormagan.
                    </p>
                ) : (
                    requests.map((r) => (
                        <article
                            key={r.id}
                            className="space-y-4 rounded-lg border bg-card p-5"
                        >
                            <header className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-medium">
                                        {r.type_label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {r.agency}
                                    </p>
                                </div>
                                <StatusBadge
                                    status={r.status}
                                    label={r.status_label}
                                />
                            </header>

                            <Steps steps={r.steps} />

                            <DeadlineChip
                                dueAt={r.due_at}
                                status={r.status}
                                deadlineLabel={r.deadline_label}
                                answeredOnTime={r.answered_on_time}
                            />

                            {r.rejection_reason && (
                                <p className="text-sm text-danger">
                                    Idora javobi: {r.rejection_reason}
                                </p>
                            )}

                            {r.status === "completed" && (
                                <div className="space-y-3 border-t pt-4">
                                    {r.summary_items.length > 0 && (
                                        <dl className="grid gap-3 text-sm sm:grid-cols-3">
                                            {r.summary_items.map((it) => (
                                                <div key={it.label}>
                                                    <dt className="text-xs text-muted-foreground">
                                                        {it.label}
                                                    </dt>
                                                    <dd className="font-medium tabular-nums">
                                                        {it.value}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                    )}
                                    {r.ai_summary && (
                                        <div className="rounded-md bg-muted/40 p-3 text-sm">
                                            <p className="mb-1 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                                <Sparkles className="size-3.5" />
                                                AI xulosa (shaxs ma'lumotlari
                                                yashirilgan)
                                            </p>
                                            <p className="whitespace-pre-wrap">
                                                {r.ai_summary}
                                            </p>
                                        </div>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        {note}
                                    </p>
                                </div>
                            )}
                        </article>
                    ))
                )}
            </section>
        </>
    );
}

ClientCase.layout = ClientLayout;

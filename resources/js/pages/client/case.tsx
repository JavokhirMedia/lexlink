import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, BadgeCheck, FileSearch, Inbox } from "lucide-react";
import { AiMarkdown } from "@/components/ai/markdown";
import { EmptyState } from "@/components/brand/empty-state";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { StatusBadge } from "@/components/brand/status-badge";
import ClientLayout from "@/components/client/client-layout";
import { ClientNote } from "@/components/client/client-note";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { StatusTimeline } from "@/components/requests/status-timeline";
import { index as clientIndex } from "@/routes/client";
import type { ClientCaseInfo, ClientRequestView } from "@/types/lexlink";

type Props = {
    case: ClientCaseInfo;
    requests: ClientRequestView[];
    note: string;
};

export default function ClientCase({ case: c, requests, note }: Props) {
    return (
        <>
            <Head title={c.title} />
            <Link
                href={clientIndex()}
                className="focus-ring inline-flex w-fit items-center gap-1.5 rounded-xs text-sm text-ink-secondary transition-colors hover:text-ink"
            >
                <ArrowLeft className="size-4" strokeWidth={1.5} aria-hidden />
                Mening ishlarim
            </Link>

            <PageHeader
                title={c.title}
                description={c.category_label ?? undefined}
            />

            <section
                aria-label="Ish rekvizitlari"
                className="rounded-lg border bg-card p-6 shadow-xs"
            >
                <dl className="grid gap-6 sm:grid-cols-3">
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <dt className="text-meta text-ink-secondary">Advokat</dt>
                        <dd className="text-base font-medium text-ink">
                            {c.advocate.name ?? "—"}
                        </dd>
                        {c.advocate.bureau_name && (
                            <dd className="text-sm text-ink-secondary">
                                {c.advocate.bureau_name}
                            </dd>
                        )}
                        {c.advocate.license_number && (
                            <dd className="flex flex-wrap items-center gap-2">
                                <RegistryChip
                                    value={c.advocate.license_number}
                                    kind="license"
                                    size="sm"
                                />
                                <span className="inline-flex items-center gap-1 text-meta text-success">
                                    <BadgeCheck
                                        className="size-3.5"
                                        strokeWidth={1.75}
                                        aria-hidden
                                    />
                                    tasdiqlangan
                                </span>
                            </dd>
                        )}
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <dt className="text-meta text-ink-secondary">
                            Advokat orderi
                        </dt>
                        <dd>
                            <RegistryChip value={c.order_number} kind="order" />
                        </dd>
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <dt className="text-meta text-ink-secondary">Sud ishi</dt>
                        <dd>
                            {c.court_case_number ? (
                                <RegistryChip
                                    value={c.court_case_number}
                                    kind="case"
                                />
                            ) : (
                                <span className="text-ink-muted">—</span>
                            )}
                        </dd>
                    </div>
                </dl>
            </section>

            <ClientNote data-test="client-note">{note}</ClientNote>

            <section aria-labelledby="client-requests" className="flex flex-col gap-4">
                <h2 id="client-requests" className="text-section-title text-ink">
                    So'rovlar holati
                </h2>
                {requests.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title="Advokatingiz hali davlat idoralariga so'rov yubormagan"
                    />
                ) : (
                    requests.map((r) => (
                        <article
                            key={r.id}
                            className="flex flex-col gap-6 rounded-lg border bg-card p-6 shadow-xs"
                        >
                            <header className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <h3 className="text-base font-medium text-ink">
                                        {r.type_label}
                                    </h3>
                                    <p className="text-sm text-ink-secondary">
                                        {r.agency}
                                    </p>
                                </div>
                                <StatusBadge
                                    status={r.status}
                                    label={r.status_label}
                                />
                            </header>

                            <StatusTimeline steps={r.steps} dense />

                            {r.due_at && (
                                <DeadlineChip
                                    dueAt={r.due_at}
                                    status={r.status}
                                    deadlineLabel={r.deadline_label}
                                    answeredOnTime={r.answered_on_time}
                                    className="w-fit"
                                />
                            )}

                            {r.rejection_reason && (
                                <p className="rounded-md border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger">
                                    Idora javobi: {r.rejection_reason}
                                </p>
                            )}

                            {r.status === "completed" &&
                                (r.summary_items.length > 0 ||
                                    r.ai_summary) && (
                                    <div className="flex flex-col gap-4 border-t pt-6">
                                        {r.summary_items.length > 0 && (
                                            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 rounded-lg border bg-card p-4 sm:grid-cols-3">
                                                {r.summary_items.map((it) => (
                                                    <div
                                                        key={it.label}
                                                        className="flex min-w-0 flex-col gap-1"
                                                    >
                                                        <dt className="text-meta text-ink-secondary">
                                                            {it.label}
                                                        </dt>
                                                        <dd className="text-base font-semibold text-ink tabular-nums">
                                                            {it.value}
                                                        </dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        )}
                                        {r.ai_summary && (
                                            <div className="flex flex-col gap-2 rounded-lg bg-raised px-4 py-3">
                                                <p className="flex items-center gap-2 text-meta font-medium text-ink-secondary">
                                                    <FileSearch
                                                        className="size-4"
                                                        strokeWidth={1.5}
                                                        aria-hidden
                                                    />
                                                    AI xulosa (shaxs
                                                    ma'lumotlari yashirilgan)
                                                </p>
                                                <AiMarkdown className="max-w-[68ch]">
                                                    {r.ai_summary}
                                                </AiMarkdown>
                                            </div>
                                        )}
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

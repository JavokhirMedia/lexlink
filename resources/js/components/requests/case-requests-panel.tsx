import { Link, router } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";
import { ChevronRight, FilePlus2, Inbox } from "lucide-react";
import { useState } from "react";
import { EmptyState } from "@/components/brand/empty-state";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import { StatusBadge } from "@/components/brand/status-badge";
import { DeadlineChip } from "@/components/orders/deadline-chip";
import { Button } from "@/components/ui/button";
import { create as createRoute, show as showRoute } from "@/routes/requests";
import type {
    DataRequest,
    RequestDeadlineFields,
    RequestStatusUpdatedEvent,
} from "@/types/lexlink";
import { formatDate } from "./types";
import { RequestTypeIcon } from "./request-type-icon";

type Row = DataRequest & Partial<RequestDeadlineFields>;

/**
 * Compact request list for cases/show with live status updates.
 * Props: caseId: number, requests: DataRequest[], canCreate?: boolean (default true)
 * The "Yangi so'rov" action lives in the case page header; this panel only
 * offers it from its empty state.
 * On a RequestStatusUpdated broadcast the badge updates instantly and the `requests` prop is reloaded.
 */
export function CaseRequestsPanel({
    caseId,
    requests,
    canCreate = true,
}: {
    caseId: number;
    requests: Row[];
    canCreate?: boolean;
}) {
    const [live, setLive] = useState<Record<number, RequestStatusUpdatedEvent>>(
        {},
    );

    useEcho<RequestStatusUpdatedEvent>(
        `case.${caseId}`,
        ".RequestStatusUpdated",
        (e) => {
            setLive((prev) => ({ ...prev, [e.id]: e }));
            router.reload({ only: ["requests"] });
        },
        [caseId],
    );

    return (
        <Section
            title="Idoralarga so'rovlar"
            description="Har bir so'rov huquqiy asos bilan tekshiriladi va advokat ERI bilan imzolaydi"
        >
            {requests.length === 0 ? (
                <EmptyState
                    icon={Inbox}
                    title="Bu ish bo'yicha hali so'rov yo'q"
                    action={
                        canCreate ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={createRoute.url({
                                        query: { case: caseId },
                                    })}
                                >
                                    <FilePlus2 />
                                    Yangi so'rov
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
            ) : (
                <ul className="divide-y overflow-hidden rounded-lg border bg-card shadow-xs">
                    {requests.map((r) => {
                        const ev = live[r.id];
                        const status = ev?.status ?? r.status;
                        const rejection =
                            ev?.rejection_reason ?? r.rejection_reason;

                        return (
                            <li key={r.id}>
                                <Link
                                    href={showRoute.url(r.id)}
                                    className="focus-ring flex items-start gap-4 px-4 py-4 transition-colors hover:bg-page sm:items-center sm:px-6"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-raised text-ink-secondary">
                                        <RequestTypeIcon
                                            type={r.type}
                                            className="size-[18px]"
                                        />
                                    </span>
                                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                                        <p className="flex flex-wrap items-baseline gap-x-2 text-base font-medium text-ink">
                                            {r.type_label}
                                            <span className="text-sm font-normal text-ink-secondary">
                                                {r.agency}
                                            </span>
                                        </p>
                                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-secondary">
                                            {r.gov_request_id ? (
                                                <RegistryChip
                                                    value={r.gov_request_id}
                                                    kind="gov"
                                                    size="sm"
                                                />
                                            ) : (
                                                <span className="font-mono text-ink-muted">
                                                    #{r.id}
                                                </span>
                                            )}
                                            {r.subject_name && (
                                                <span>{r.subject_name}</span>
                                            )}
                                            <MaskedPinfl
                                                value={r.subject_pinfl_masked}
                                            />
                                            <span className="text-ink-muted tabular-nums">
                                                {formatDate(
                                                    r.submitted_at ??
                                                        r.created_at,
                                                )}
                                            </span>
                                        </p>
                                        {rejection && (
                                            <p className="text-xs text-danger">
                                                {rejection}
                                            </p>
                                        )}
                                        <div className="flex flex-wrap items-center gap-2 pt-1 sm:hidden">
                                            <StatusBadge
                                                status={status}
                                                label={
                                                    ev?.status_label ??
                                                    r.status_label
                                                }
                                                size="sm"
                                            />
                                            <DeadlineChip
                                                dueAt={r.due_at ?? null}
                                                status={status}
                                                answeredOnTime={
                                                    r.answered_on_time
                                                }
                                                compact
                                            />
                                        </div>
                                    </div>
                                    <div className="hidden shrink-0 flex-col items-end gap-1.5 sm:flex">
                                        <StatusBadge
                                            status={status}
                                            label={
                                                ev?.status_label ??
                                                r.status_label
                                            }
                                            size="sm"
                                        />
                                        <DeadlineChip
                                            dueAt={r.due_at ?? null}
                                            status={status}
                                            answeredOnTime={r.answered_on_time}
                                            compact
                                        />
                                    </div>
                                    <ChevronRight
                                        className="hidden size-4 shrink-0 text-ink-muted sm:block"
                                        strokeWidth={1.5}
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Section>
    );
}

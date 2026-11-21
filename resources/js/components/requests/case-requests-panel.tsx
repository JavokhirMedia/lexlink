import { Link, router } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";
import { ChevronRight, FilePlus2, Inbox } from "lucide-react";
import { useState } from "react";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { StatusBadge } from "@/components/brand/status-badge";
import { Button } from "@/components/ui/button";
import { create as createRoute, show as showRoute } from "@/routes/requests";
import type { DataRequest, RequestStatusUpdatedEvent } from "@/types/lexlink";
import { formatDateTime } from "./types";
import { RequestTypeIcon } from "./request-type-icon";

/**
 * Compact request list for cases/show with live status updates.
 * Props: caseId: number, requests: DataRequest[], canCreate?: boolean (default true)
 * On a RequestStatusUpdated broadcast the badge updates instantly and the `requests` prop is reloaded.
 */
export function CaseRequestsPanel({
    caseId,
    requests,
    canCreate = true,
}: {
    caseId: number;
    requests: DataRequest[];
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
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="text-base font-semibold">
                        Idoralarga so'rovlar
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Har bir so'rov huquqiy asos bilan tekshiriladi va
                        advokat ERI bilan imzolaydi.
                    </p>
                </div>
                {canCreate && (
                    <Button asChild size="sm">
                        <Link
                            href={createRoute.url({ query: { case: caseId } })}
                        >
                            <FilePlus2 />
                            Yangi so'rov
                        </Link>
                    </Button>
                )}
            </div>

            {requests.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                    <Inbox className="size-6" aria-hidden />
                    Bu ish bo'yicha hali so'rov yo'q.
                </div>
            ) : (
                <ul className="divide-y rounded-md border">
                    {requests.map((r) => {
                        const ev = live[r.id];
                        const status = ev?.status ?? r.status;

                        return (
                            <li key={r.id}>
                                <Link
                                    href={showRoute.url(r.id)}
                                    className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-center gap-x-2 text-sm font-medium">
                                            <RequestTypeIcon
                                                type={r.type}
                                                className="size-4 text-muted-foreground"
                                            />
                                            {r.type_label}
                                            <span className="text-xs font-normal text-muted-foreground">
                                                · {r.agency}
                                            </span>
                                        </p>
                                        <p className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                            <span>#{r.id}</span>
                                            {r.subject_name && (
                                                <span>{r.subject_name}</span>
                                            )}
                                            <MaskedPinfl
                                                value={r.subject_pinfl_masked}
                                            />
                                            <span>
                                                {formatDateTime(
                                                    r.submitted_at ??
                                                        r.created_at,
                                                )}
                                            </span>
                                        </p>
                                        {(ev?.rejection_reason ??
                                            r.rejection_reason) && (
                                            <p className="mt-0.5 text-xs text-danger">
                                                {ev?.rejection_reason ??
                                                    r.rejection_reason}
                                            </p>
                                        )}
                                    </div>
                                    <StatusBadge
                                        status={status}
                                        label={
                                            ev?.status_label ?? r.status_label
                                        }
                                    />
                                    <ChevronRight
                                        className="size-4 text-muted-foreground"
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

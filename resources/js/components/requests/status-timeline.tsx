import { Check, X } from "lucide-react";
import { formatDateTime } from "@/components/brand/money";
import { RegistryChip } from "@/components/brand/registry-chip";
import { cn } from "@/lib/utils";
import type { TimelineStep } from "./types";

type Step = Omit<TimelineStep, "key" | "note"> & {
    key?: string;
    note?: string | null;
};

type Signer = { name: string; license_number: string | null } | null;

/**
 * Moment #1 (docs/DESIGN.md): Loyiha → Imzo kutilmoqda → Yuborildi → Jarayonda → Tayyor.
 * Horizontal from md, vertical below. Completed steps are brand, the current step
 * carries a ring and "Hozirgi bosqich", the final answer gets a seal-like node.
 *
 * Props:
 *   steps:         TimelineStep[] (key/note optional so the client portal can reuse it)
 *   signer?:       shown with a licence RegistryChip on the "Yuborildi" step
 *   govRequestId?: shown as a RegistryChip on the "Jarayonda" step
 *   dense?:        smaller labels (client portal cards)
 */
export function StatusTimeline({
    steps,
    signer = null,
    govRequestId = null,
    dense = false,
    className,
}: {
    steps: Step[];
    signer?: Signer;
    govRequestId?: string | null;
    dense?: boolean;
    className?: string;
}) {
    return (
        <ol
            className={cn(
                "grid gap-0",
                steps.length === 5 && "md:grid-cols-5",
                steps.length === 4 && "md:grid-cols-4",
                steps.length === 3 && "md:grid-cols-3",
                className,
            )}
        >
            {steps.map((s, i) => {
                const done = s.state === "done";
                const success = s.state === "success";
                const danger = s.state === "danger";
                const current = s.state === "current";
                const upcoming = s.state === "upcoming";
                const last = i === steps.length - 1;
                const key = s.key ?? String(i);

                return (
                    <li
                        key={key}
                        aria-current={current ? "step" : undefined}
                        className={cn(
                            "relative flex gap-4 md:flex-col md:gap-3 md:pr-4",
                            !last && "pb-6 md:pb-0",
                        )}
                    >
                        {!last && (
                            <span
                                aria-hidden
                                className={cn(
                                    "absolute top-9 bottom-1 left-[13px] w-0.5 rounded-full md:top-[13px] md:right-2 md:bottom-auto md:left-9 md:h-0.5 md:w-auto",
                                    done ? "bg-brand" : "bg-hairline",
                                )}
                            />
                        )}

                        <span
                            className={cn(
                                "relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full text-meta tabular-nums",
                                done && "bg-brand text-white",
                                success &&
                                    "bg-success text-white ring-1 ring-success ring-offset-2 ring-offset-surface",
                                danger && "bg-danger text-white",
                                current &&
                                    "border-2 border-brand bg-surface text-brand ring-4 ring-brand-soft",
                                upcoming &&
                                    "border border-hairline bg-surface text-ink-muted",
                            )}
                        >
                            {done || success ? (
                                <Check
                                    className="size-4"
                                    strokeWidth={2}
                                    aria-hidden
                                />
                            ) : danger ? (
                                <X
                                    className="size-4"
                                    strokeWidth={2}
                                    aria-hidden
                                />
                            ) : current ? (
                                <span className="size-2.5 rounded-full bg-brand" />
                            ) : (
                                i + 1
                            )}
                        </span>

                        <div className="flex min-w-0 flex-col gap-1">
                            <p
                                className={cn(
                                    "font-medium",
                                    dense ? "text-sm" : "text-base",
                                    upcoming && "text-ink-muted",
                                    current && "text-brand",
                                    danger && "text-danger",
                                    success && "text-success",
                                    done && "text-ink",
                                )}
                            >
                                {s.label}
                            </p>
                            {current && (
                                <p className="text-meta text-ink-secondary">
                                    Hozirgi bosqich
                                </p>
                            )}
                            {s.at && (
                                <p className="text-meta text-ink-secondary tabular-nums">
                                    {formatDateTime(s.at)}
                                </p>
                            )}
                            <StepDetail
                                step={s}
                                signer={signer}
                                govRequestId={govRequestId}
                            />
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

function StepDetail({
    step,
    signer,
    govRequestId,
}: {
    step: Step;
    signer: Signer;
    govRequestId: string | null;
}) {
    if (step.key === "submitted" && signer && step.state !== "upcoming") {
        return (
            <div className="flex flex-col items-start gap-1.5 pt-1">
                <span className="text-meta text-ink-secondary">
                    Imzoladi: <span className="text-ink">{signer.name}</span>
                </span>
                {signer.license_number && (
                    <RegistryChip
                        value={signer.license_number}
                        kind="license"
                        size="sm"
                    />
                )}
            </div>
        );
    }

    if (step.key === "processing" && govRequestId) {
        return (
            <div className="flex flex-col items-start gap-1.5 pt-1">
                <span className="text-meta text-ink-secondary">
                    Idora raqami
                </span>
                <RegistryChip value={govRequestId} kind="gov" size="sm" />
            </div>
        );
    }

    if (!step.note) {
        return null;
    }

    return (
        <p
            className={cn(
                "text-meta break-words",
                step.state === "danger" ? "text-danger" : "text-ink-secondary",
            )}
        >
            {step.note}
        </p>
    );
}

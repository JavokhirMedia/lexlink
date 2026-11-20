import { Check, Circle, X } from "lucide-react";
import { cn } from "@/lib/utils";
import type { TimelineStep } from "./types";
import { formatDateTime } from "./types";

/** Loyiha → Imzo kutilmoqda → Yuborildi → Jarayonda → Tayyor / Rad etildi */
export function StatusTimeline({ steps }: { steps: TimelineStep[] }) {
    return (
        <ol className="grid gap-0 sm:grid-cols-5">
            {steps.map((s, i) => {
                const done = s.state === "done" || s.state === "success";
                const danger = s.state === "danger";
                const current = s.state === "current";

                return (
                    <li
                        key={s.key}
                        className="relative flex gap-3 pb-5 sm:flex-col sm:gap-2 sm:pb-0 sm:pr-3"
                    >
                        {i < steps.length - 1 && (
                            <span
                                aria-hidden
                                className={cn(
                                    "absolute top-7 left-[13px] h-[calc(100%-1.75rem)] w-px sm:top-[13px] sm:left-7 sm:h-px sm:w-[calc(100%-1.75rem)]",
                                    done ? "bg-primary" : "bg-border",
                                )}
                            />
                        )}
                        <span
                            className={cn(
                                "relative z-10 flex size-[27px] shrink-0 items-center justify-center rounded-full border text-xs",
                                done &&
                                    "border-primary bg-primary text-primary-foreground",
                                danger && "border-danger bg-danger text-white",
                                current &&
                                    "border-primary bg-background text-primary ring-4 ring-primary/15",
                                s.state === "upcoming" &&
                                    "border-border bg-background text-muted-foreground",
                            )}
                        >
                            {done ? (
                                <Check className="size-3.5" />
                            ) : danger ? (
                                <X className="size-3.5" />
                            ) : (
                                <Circle
                                    className={cn(
                                        "size-2.5",
                                        current &&
                                            "fill-current motion-safe:animate-pulse",
                                    )}
                                />
                            )}
                        </span>
                        <div className="min-w-0">
                            <p
                                className={cn(
                                    "text-sm font-medium",
                                    s.state === "upcoming" &&
                                        "text-muted-foreground",
                                    danger && "text-danger",
                                )}
                            >
                                {s.label}
                            </p>
                            {s.at && (
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    {formatDateTime(s.at)}
                                </p>
                            )}
                            {s.note && (
                                <p className="mt-0.5 text-xs break-words text-muted-foreground">
                                    {s.note}
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

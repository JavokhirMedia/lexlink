import { CalendarClock, CheckCircle2, TriangleAlert } from "lucide-react";
import { cn } from "@/lib/utils";

const LAW_REF = "Advokatura to'g'risidagi Qonun 7¹-modda";

/** ISO date part as rendered by the server (Tashkent time). */
export const isoDay = (v: string) => v.slice(0, 10);

export function daysLeft(dueAt: string, now: Date = new Date()): number {
    return Math.ceil((new Date(dueAt).getTime() - now.getTime()) / 86_400_000);
}

type Props = {
    dueAt: string | null;
    status: string;
    deadlineLabel?: string | null;
    answeredOnTime?: boolean | null;
    compact?: boolean;
    className?: string;
};

/**
 * "Javob muddati: 2026-12-05 gacha (Advokatura to'g'risidagi Qonun 7¹-modda)" + remaining days;
 * after completion: "Muddatida javob berildi" / "Muddat buzilgan".
 */
export function DeadlineChip({
    dueAt,
    status,
    deadlineLabel,
    answeredOnTime,
    compact = false,
    className,
}: Props) {
    if (!dueAt) {
        return null;
    }

    if (
        status === "completed" &&
        answeredOnTime !== null &&
        answeredOnTime !== undefined
    ) {
        return (
            <span
                className={cn(
                    "inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium",
                    answeredOnTime
                        ? "border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                        : "border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300",
                    className,
                )}
            >
                {answeredOnTime ? (
                    <CheckCircle2 className="size-3.5" aria-hidden />
                ) : (
                    <TriangleAlert className="size-3.5" aria-hidden />
                )}
                {answeredOnTime
                    ? "Muddatida javob berildi"
                    : "Javob muddatidan kech berildi"}
                {!compact && (
                    <span className="font-normal opacity-80">
                        {" "}
                        · muddat {isoDay(dueAt)}
                    </span>
                )}
            </span>
        );
    }

    if (status !== "submitted" && status !== "processing") {
        return null;
    }

    const left = daysLeft(dueAt);
    const overdue = left < 0;

    return (
        <span
            className={cn(
                "inline-flex flex-wrap items-center gap-1.5 rounded-md border px-2 py-1 text-xs",
                overdue
                    ? "border-danger-border bg-danger-soft text-danger"
                    : left <= 2
                      ? "border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                      : "border-primary/30 bg-primary/5 text-foreground",
                className,
            )}
            title={deadlineLabel ?? undefined}
        >
            <CalendarClock className="size-3.5 shrink-0" aria-hidden />
            <span className="font-medium">
                Javob muddati: {isoDay(dueAt)} gacha
            </span>
            {!compact && (
                <span className="text-muted-foreground">({LAW_REF})</span>
            )}
            <span className="font-medium tabular-nums">
                ·{" "}
                {overdue
                    ? `muddat ${Math.abs(left)} kun o'tdi`
                    : left === 0
                      ? "bugun tugaydi"
                      : `${left} kun qoldi`}
            </span>
        </span>
    );
}

import { CalendarClock, CheckCircle2, TriangleAlert } from "lucide-react";
import { formatDate } from "@/components/brand/money";
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

const BASE =
    "inline-flex w-fit max-w-full flex-wrap items-center gap-x-1.5 gap-y-0.5 rounded-sm border px-2 py-0.5 text-xs";

/**
 * Open request:   "Javob muddati 05.12.2026 gacha · 12 kun qoldi" (+ law ref unless compact).
 * After answer:   "Muddatida javob berildi" / "Javob muddatidan kech berildi".
 * Neutral while there is time, warning at <= 2 days, danger when overdue.
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
        const Icon = answeredOnTime ? CheckCircle2 : TriangleAlert;

        return (
            <span
                className={cn(
                    BASE,
                    answeredOnTime
                        ? "border-success-border bg-success-soft text-success"
                        : "border-warning-border bg-warning-soft text-warning",
                    className,
                )}
            >
                <Icon
                    className="size-3.5 shrink-0"
                    strokeWidth={1.75}
                    aria-hidden
                />
                <span className="font-medium">
                    {answeredOnTime
                        ? "Muddatida javob berildi"
                        : "Javob muddatidan kech berildi"}
                </span>
                {!compact && (
                    <span className="tabular-nums opacity-80">
                        · muddat {formatDate(dueAt)}
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
    const tone = overdue
        ? "border-danger-border bg-danger-soft text-danger"
        : left <= 2
          ? "border-warning-border bg-warning-soft text-warning"
          : "border-hairline bg-surface text-ink";

    return (
        <span
            className={cn(
                BASE,
                tone,
                compact && "flex-nowrap whitespace-nowrap",
                className,
            )}
            title={deadlineLabel ?? LAW_REF}
        >
            <CalendarClock
                className={cn(
                    "size-3.5 shrink-0",
                    !overdue && left > 2 && "text-ink-muted",
                )}
                strokeWidth={1.75}
                aria-hidden
            />
            <span className="tabular-nums">
                {compact
                    ? formatDate(dueAt)
                    : `Javob muddati ${formatDate(dueAt)} gacha`}
            </span>
            <span className="font-medium tabular-nums">
                ·{" "}
                {overdue
                    ? `${Math.abs(left)} kun o'tdi`
                    : left === 0
                      ? "bugun tugaydi"
                      : `${left} kun qoldi`}
            </span>
            {!compact && (
                <span className="text-ink-muted">· {LAW_REF}</span>
            )}
        </span>
    );
}

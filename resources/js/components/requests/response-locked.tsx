import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * <ResponseLocked icon={Lock} title="…" description="…" action={<Button/>} />
 *
 * Calm locked state for the response area (lawyer without a secrecy declaration,
 * admin metadata-only, purged payload): line icon + one sentence + one action.
 */
export function ResponseLocked({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon: LucideIcon;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                "flex flex-col gap-4 rounded-lg border bg-card p-6 shadow-xs sm:flex-row sm:items-center",
                className,
            )}
        >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-raised text-ink-secondary">
                <Icon className="size-[18px]" strokeWidth={1.5} aria-hidden />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-medium text-ink">{title}</p>
                {description && (
                    <p className="text-sm text-ink-secondary">{description}</p>
                )}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

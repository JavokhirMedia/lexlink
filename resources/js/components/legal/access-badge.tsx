/**
 * <AccessBadge access="order" />  — "Advokat orderi yetarli"
 * <AccessBadge access="court" />  — "Faqat sud qarori"
 *
 * Which legal basis a data type needs (App\Support\LegalMatrix `access`).
 *
 * Props:
 *   access:     'order' | 'court'
 *   size?:      'sm' | 'md' (default 'md')
 *   label?:     string — override the default Uzbek label
 *   className?: string
 */
import { Check, Gavel } from "lucide-react";
import { cn } from "@/lib/utils";
import type { LegalAccess } from "@/types/legal-map";

export const ACCESS_LABELS: Record<LegalAccess, string> = {
    order: "Advokat orderi yetarli",
    court: "Faqat sud qarori",
};

export function AccessBadge({
    access,
    size = "md",
    label,
    className,
}: {
    access: LegalAccess;
    size?: "sm" | "md";
    label?: string;
    className?: string;
}) {
    const Icon = access === "order" ? Check : Gavel;

    return (
        <span
            data-access={access}
            className={cn(
                "inline-flex w-fit shrink-0 items-center gap-1.5 rounded-sm border font-medium whitespace-nowrap",
                size === "sm" ? "h-6 px-2 text-xs" : "h-7 px-2.5 text-sm",
                access === "order"
                    ? "border-hairline bg-surface text-ink"
                    : "border-transparent bg-danger-soft text-danger",
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                strokeWidth={1.75}
                className={cn(
                    size === "sm" ? "size-3.5" : "size-4",
                    access === "order" && "text-success",
                )}
            />
            {label ?? ACCESS_LABELS[access]}
        </span>
    );
}

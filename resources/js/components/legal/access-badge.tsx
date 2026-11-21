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
import { Gavel, ScrollText } from "lucide-react";
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
    const Icon = access === "order" ? ScrollText : Gavel;

    return (
        <span
            data-access={access}
            className={cn(
                "inline-flex w-fit shrink-0 items-center gap-1.5 rounded-sm border font-medium whitespace-nowrap",
                size === "sm"
                    ? "px-1.5 py-px text-[11px]"
                    : "px-2 py-0.5 text-xs",
                access === "order"
                    ? "border-success-border bg-success-soft text-success"
                    : "border-danger-border bg-danger-soft text-danger",
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                className={size === "sm" ? "size-3" : "size-3.5"}
            />
            {label ?? ACCESS_LABELS[access]}
        </span>
    );
}

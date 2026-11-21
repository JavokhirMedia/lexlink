import { Info } from "lucide-react";
import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

/** The ShMQ data-minimisation note, as a calm info panel. */
export function ClientNote({
    children,
    className,
    ...rest
}: {
    children: ReactNode;
    className?: string;
    "data-test"?: string;
}) {
    return (
        <div
            className={cn(
                "flex items-start gap-3 rounded-lg border border-brand-border bg-brand-soft/60 px-4 py-3 text-sm text-ink",
                className,
            )}
        >
            <Info
                className="mt-0.5 size-[18px] shrink-0 text-brand"
                strokeWidth={1.5}
                aria-hidden
            />
            <p data-test={rest["data-test"]}>{children}</p>
        </div>
    );
}

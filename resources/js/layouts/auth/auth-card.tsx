import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

/** The 440px auth card: title 28/600, description 16 secondary, then content. */
export function AuthCard({
    title,
    description,
    className,
    children,
}: {
    title?: ReactNode;
    description?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                "relative w-full max-w-[440px] rounded-xl border bg-card p-6 shadow-xs sm:p-8",
                className,
            )}
        >
            {(title || description) && (
                <div className="mb-6 flex flex-col gap-2">
                    {title && (
                        <h1 className="text-page-title text-ink">{title}</h1>
                    )}
                    {description && (
                        <p className="text-base text-ink-secondary">
                            {description}
                        </p>
                    )}
                </div>
            )}
            {children}
        </div>
    );
}

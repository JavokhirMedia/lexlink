import type { ReactNode } from "react";
import PublicLayout from "@/layouts/public-layout";
import { cn } from "@/lib/utils";

/**
 * Onboarding and invite pages: public shell + one centered 672px card
 * (the user may not have a role yet, so no sidebar). Sign-out lives in the
 * header user menu. `step` = [current, total] draws a quiet two-segment
 * progress line above the title.
 */
export function OnboardingShell({
    title,
    description,
    step,
    children,
}: {
    title: string;
    description?: ReactNode;
    step?: [number, number];
    children: ReactNode;
}) {
    return (
        <PublicLayout>
            <div className="mx-auto w-full max-w-2xl sm:py-4">
                <div className="rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                    <div className="mb-8 flex flex-col gap-2">
                        {step && (
                            <div className="mb-2 flex flex-col gap-2">
                                <p className="text-meta text-ink-secondary">
                                    {step[0]}-qadam, jami {step[1]}
                                </p>
                                <div className="flex gap-1" aria-hidden="true">
                                    {Array.from({ length: step[1] }, (_, i) => (
                                        <span
                                            key={i}
                                            className={cn(
                                                "h-1 flex-1 rounded-full",
                                                i < step[0]
                                                    ? "bg-brand"
                                                    : "bg-raised",
                                            )}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                        <h1 className="text-page-title text-ink">{title}</h1>
                        {description && (
                            <p className="text-base text-ink-secondary">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </div>
        </PublicLayout>
    );
}

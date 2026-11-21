import type { ReactNode } from 'react';
import PublicLayout from '@/layouts/public-layout';

/**
 * Onboarding and invite pages: public shell + one centered 672px card
 * (the user may not have a role yet, so no sidebar). Sign-out lives in the
 * header user menu.
 */
export function OnboardingShell({
    title,
    description,
    step,
    children,
}: {
    title: string;
    description?: ReactNode;
    step?: string;
    children: ReactNode;
}) {
    return (
        <PublicLayout>
            <div className="mx-auto w-full max-w-2xl">
                <div className="rounded-xl border bg-card p-6 shadow-xs md:p-8">
                    <div className="mb-6 flex flex-col gap-2">
                        {step && (
                            <p className="text-meta text-ink-secondary">{step}</p>
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

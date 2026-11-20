import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/brand/logo';
import { home, logout } from '@/routes';

/** Centered, sidebar-less frame for onboarding and invite pages (user may not have a role yet). */
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
        <div className="flex min-h-svh flex-col items-center bg-muted px-4 py-10 md:py-16">
            <div className="w-full max-w-2xl">
                <div className="mb-8 flex items-center justify-between">
                    <Link href={home()}>
                        <Logo />
                    </Link>
                    <Link
                        href={logout()}
                        as="button"
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Chiqish
                    </Link>
                </div>
                <div className="rounded-lg border bg-card p-6 md:p-8">
                    <div className="mb-6 space-y-1.5">
                        {step && (
                            <p className="text-xs font-medium text-muted-foreground">
                                {step}
                            </p>
                        )}
                        <h1 className="font-display text-2xl font-semibold">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

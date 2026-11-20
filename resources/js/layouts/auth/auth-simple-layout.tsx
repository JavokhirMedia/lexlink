import { Link } from '@inertiajs/react';
import { DemoBadge } from '@/components/brand/demo-badge';
import { Logo } from '@/components/brand/logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8 rounded-lg border bg-card p-8">
                    <div className="flex flex-col items-center gap-5">
                        <Link href={home()}>
                            <Logo />
                        </Link>
                        <div className="space-y-2 text-center">
                            <h1 className="font-display text-xl font-semibold">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
                <div className="mt-4 flex justify-center">
                    <DemoBadge />
                </div>
            </div>
        </div>
    );
}

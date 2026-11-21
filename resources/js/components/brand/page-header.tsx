/**
 * <PageHeader title="Ishlar" description="..." actions={<Button/>} />
 *
 * Page title block (docs/DESIGN.md "Content"): title 28/34 600, description
 * 16 secondary, actions right (wrap under on mobile). Breadcrumbs are drawn by
 * the app shell above it, so pages never render their own.
 *
 * Props:
 *   title:        ReactNode — rendered as the page h1
 *   description?: ReactNode — one or two lines of secondary text
 *   meta?:        ReactNode — row under the description (RegistryChip, StatusBadge…)
 *   actions?:     ReactNode — right-aligned buttons, primary last
 *   className?:   string
 *   children?:    ReactNode — optional extra content below (e.g. tabs)
 */
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function PageHeader({
    title,
    description,
    meta,
    actions,
    className,
    children,
}: {
    title: ReactNode;
    description?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
    children?: ReactNode;
}) {
    return (
        <header className={cn('flex flex-col gap-4', className)}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex min-w-0 flex-col gap-2">
                    <h1 className="text-page-title text-ink">{title}</h1>
                    {description && (
                        <p className="max-w-3xl text-base text-ink-secondary">
                            {description}
                        </p>
                    )}
                    {meta && (
                        <div className="flex flex-wrap items-center gap-2 pt-1 text-sm text-ink-secondary">
                            {meta}
                        </div>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
            {children}
        </header>
    );
}

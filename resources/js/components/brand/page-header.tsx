/**
 * <PageHeader title="Ishlar" description="..." actions={<Button/>} />
 *
 * Props:
 *   title:        ReactNode — rendered as h1 in the serif display face
 *   description?: ReactNode — one line of muted text under the title
 *   meta?:        ReactNode — small row under the description (badges, IDs)
 *   actions?:     ReactNode — right-aligned buttons (wraps under on mobile)
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
        <div className={cn('border-b pb-5', className)}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 space-y-1.5">
                    <h1 className="font-display text-[26px] leading-tight font-semibold text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                    {meta && (
                        <div className="flex flex-wrap items-center gap-2 pt-1 text-xs text-muted-foreground">
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
            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}

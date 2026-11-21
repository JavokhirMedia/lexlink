/**
 * <Section title="So'nggi so'rovlar" description="..." actions={<Button/>}>
 *   ...content
 * </Section>
 *
 * A titled block inside a page: title 20/28 600, optional one-line
 * description (secondary), optional actions on the right, then content.
 * Pages stack Sections with gap-8 (32) to gap-12 (48).
 *
 * Props:
 *   title:        ReactNode — h2
 *   description?: ReactNode
 *   actions?:     ReactNode — e.g. a "Barchasi" link button
 *   as?:          'section' | 'div' (default 'section')
 *   card?:        boolean — wrap content in a white card (radius 12, padding 24)
 *   id?:          string — anchor id (also links the heading for a11y)
 *   className?, contentClassName?
 */
import type { ReactNode } from 'react';
import { useId } from 'react';
import { cn } from '@/lib/utils';

export function Section({
    title,
    description,
    actions,
    as: Tag = 'section',
    card = false,
    id,
    className,
    contentClassName,
    children,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    as?: 'section' | 'div';
    card?: boolean;
    id?: string;
    className?: string;
    contentClassName?: string;
    children?: ReactNode;
}) {
    const autoId = useId();
    const headingId = `${id ?? autoId}-title`;

    return (
        <Tag
            id={id}
            aria-labelledby={headingId}
            className={cn('flex flex-col gap-4', className)}
        >
            <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                <div className="flex min-w-0 flex-col gap-1">
                    <h2 id={headingId} className="text-section-title text-ink">
                        {title}
                    </h2>
                    {description && (
                        <p className="text-sm text-ink-secondary">
                            {description}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
            {card ? (
                <div
                    className={cn(
                        'rounded-lg border bg-card p-6 shadow-xs',
                        contentClassName,
                    )}
                >
                    {children}
                </div>
            ) : (
                <div className={contentClassName}>{children}</div>
            )}
        </Tag>
    );
}

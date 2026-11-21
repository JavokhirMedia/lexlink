/**
 * <StatTile label="Faol ishlar" value={12} hint="+2 o'tgan haftaga nisbatan" />
 *
 * Label + figure + comparison. No icons, no sparklines (docs/DESIGN.md).
 *
 * Props:
 *   label:      string — short caption above the value (secondary, 14)
 *   value:      ReactNode — the figure (wrap money in <Money/>); 28px 600 tabular
 *   hint?:      ReactNode — comparison / meta line below (13, muted)
 *   tone?:      'default' | 'success' | 'warning' | 'danger' | 'brand' — colours the hint only
 *   emphasis?:  boolean — the one key figure on the screen: value in brand blue
 *   href?:      string — the tile becomes an Inertia <Link>
 *   className?: string
 *   icon?:      deprecated, ignored (kept so old call sites compile)
 */
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const TONES = {
    default: 'text-ink-muted',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    brand: 'text-brand',
    gold: 'text-brand',
} as const;

export function StatTile({
    label,
    value,
    hint,
    tone = 'default',
    emphasis = false,
    href,
    className,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
    tone?: keyof typeof TONES;
    emphasis?: boolean;
    href?: string;
    className?: string;
}) {
    const body = (
        <>
            <span className="text-sm text-ink-secondary">{label}</span>
            <span
                className={cn(
                    'mt-2 block text-[28px] leading-[34px] font-semibold tabular-nums',
                    emphasis ? 'text-brand' : 'text-ink',
                )}
            >
                {value}
            </span>
            {hint && (
                <span className={cn('mt-1 block text-meta', TONES[tone])}>
                    {hint}
                </span>
            )}
        </>
    );

    const classes = cn(
        'flex flex-col rounded-lg border bg-card p-6 shadow-xs',
        href &&
            'focus-ring transition-colors hover:border-brand-border hover:bg-brand-soft/40',
        className,
    );

    return href ? (
        <Link href={href} className={classes}>
            {body}
        </Link>
    ) : (
        <div className={classes}>{body}</div>
    );
}

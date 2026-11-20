/**
 * <StatTile label="Faol ishlar" value={12} hint="+2 shu hafta" />
 *
 * Props:
 *   label:      string — short caption above the value
 *   value:      ReactNode — the number (wrap money in <Money/>); tabular nums
 *   hint?:      ReactNode — small muted line below
 *   icon?:      LucideIcon — optional icon at top-right
 *   tone?:      'default' | 'success' | 'warning' | 'danger' | 'gold' — tints
 *               the hint/icon only, the tile stays white
 *   href?:      string — if set, the tile becomes an Inertia <Link>
 *   className?: string
 */
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const TONES = {
    default: 'text-muted-foreground',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    gold: 'text-gold',
} as const;

export function StatTile({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
    href,
    className,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
    tone?: keyof typeof TONES;
    href?: string;
    className?: string;
}) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <span className="text-[13px] text-muted-foreground">
                    {label}
                </span>
                {Icon && (
                    <Icon
                        aria-hidden="true"
                        className={cn('size-4 shrink-0', TONES[tone])}
                    />
                )}
            </div>
            <div className="mt-2 text-2xl font-semibold tracking-tight text-foreground tabular-nums">
                {value}
            </div>
            {hint && (
                <div className={cn('mt-1 text-xs', TONES[tone])}>{hint}</div>
            )}
        </>
    );

    const classes = cn(
        'block rounded-lg border bg-card p-4',
        href && 'transition-colors hover:border-primary/40 hover:bg-accent/40',
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

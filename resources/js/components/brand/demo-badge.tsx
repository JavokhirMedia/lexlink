/**
 * <DemoBadge /> — persistent, unobtrusive "demo data" marker.
 *
 * Props:
 *   variant?:   'light' (default, on white) | 'dark' (on navy)
 *   compact?:   boolean — show only "DEMO"
 *   className?: string
 */
import { cn } from '@/lib/utils';

export function DemoBadge({
    variant = 'light',
    compact = false,
    className,
}: {
    variant?: 'light' | 'dark';
    compact?: boolean;
    className?: string;
}) {
    return (
        <span
            title="Demo rejim: barcha shaxslar, raqamlar va hujjatlar uydirma"
            className={cn(
                'inline-flex items-center gap-1.5 rounded-sm border px-1.5 py-0.5 text-[11px] leading-none whitespace-nowrap',
                variant === 'light'
                    ? 'border-gold-border bg-gold-soft text-foreground/80'
                    : 'border-white/15 bg-white/5 text-white/70',
                className,
            )}
        >
            <span className="font-mono font-medium text-gold">DEMO</span>
            {!compact && <span>barcha ma'lumotlar uydirma</span>}
        </span>
    );
}

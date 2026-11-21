/**
 * <EmptyState icon={FolderOpen} title="Hali ish yo'q" action={<Button>Yangi ish</Button>} />
 *
 * Line icon + one sentence + one action (docs/DESIGN.md). No illustrations.
 *
 * Props:
 *   icon?:        LucideIcon — 18px, muted, inside a 40px raised square
 *   title:        ReactNode — the one sentence (no trailing period)
 *   description?: ReactNode — optional second line, secondary text
 *   action?:      ReactNode — a single Button (or Link button)
 *   bordered?:    boolean — dashed hairline frame when standing alone (default true)
 *   className?:   string
 */
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    bordered = true,
    className,
}: {
    icon?: LucideIcon;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    bordered?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 px-6 py-12 text-center',
                bordered && 'rounded-lg border border-dashed bg-card',
                className,
            )}
        >
            {Icon && (
                <span className="flex size-10 items-center justify-center rounded-md bg-raised text-ink-muted">
                    <Icon aria-hidden="true" className="size-[18px]" strokeWidth={1.5} />
                </span>
            )}
            <div className="flex max-w-md flex-col gap-1">
                <p className="text-base font-medium text-ink">{title}</p>
                {description && (
                    <p className="text-sm text-ink-secondary">{description}</p>
                )}
            </div>
            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}

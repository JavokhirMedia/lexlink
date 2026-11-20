/**
 * <LegalBasisChip state="verified" detail="2-1234/2026" />
 * <LegalBasisChip state="blocked" detail="Ish hujjatlarida asos yo'q" />
 *
 * The visual for rule #1 "Asos bo'lmasa — so'rov yo'q".
 *
 * Props:
 *   state:      'verified' | 'blocked' | 'pending' | 'expired' | 'not_found' | 'mismatch'
 *               (accepts LegalBasis['verification_status'] directly)
 *   label?:     string — override the default Uzbek label
 *   detail?:    ReactNode — e.g. decision number or block reason, shown after a divider
 *   size?:      'sm' | 'md' (default 'md')
 *   className?: string
 */
import {
    ShieldAlert,
    ShieldCheck,
    ShieldEllipsis,
    ShieldX,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type LegalBasisChipState =
    | 'verified'
    | 'blocked'
    | 'pending'
    | 'expired'
    | 'not_found'
    | 'mismatch';

const CONFIG: Record<
    LegalBasisChipState,
    { label: string; icon: LucideIcon; classes: string }
> = {
    verified: {
        label: 'Huquqiy asos tasdiqlangan',
        icon: ShieldCheck,
        classes: 'border-success-border bg-success-soft text-success',
    },
    blocked: {
        label: "Asos yo'q — so'rov bloklangan",
        icon: ShieldX,
        classes: 'border-danger-border bg-danger-soft text-danger',
    },
    pending: {
        label: 'Asos tekshirilmoqda',
        icon: ShieldEllipsis,
        classes: 'border-warning-border bg-warning-soft text-warning',
    },
    expired: {
        label: "Asos muddati o'tgan",
        icon: ShieldAlert,
        classes: 'border-danger-border bg-danger-soft text-danger',
    },
    not_found: {
        label: 'Asos reyestrda topilmadi',
        icon: ShieldX,
        classes: 'border-danger-border bg-danger-soft text-danger',
    },
    mismatch: {
        label: 'Asos subyektga mos emas',
        icon: ShieldAlert,
        classes: 'border-danger-border bg-danger-soft text-danger',
    },
};

export function LegalBasisChip({
    state,
    label,
    detail,
    size = 'md',
    className,
}: {
    state: LegalBasisChipState;
    label?: string;
    detail?: ReactNode;
    size?: 'sm' | 'md';
    className?: string;
}) {
    const cfg = CONFIG[state] ?? CONFIG.pending;
    const Icon = cfg.icon;

    return (
        <span
            data-basis={state}
            className={cn(
                'inline-flex w-fit max-w-full items-center gap-1.5 rounded-sm border font-medium',
                size === 'sm'
                    ? 'px-1.5 py-px text-[11px]'
                    : 'px-2 py-1 text-xs',
                cfg.classes,
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                className={size === 'sm' ? 'size-3' : 'size-3.5'}
            />
            <span className="whitespace-nowrap">{label ?? cfg.label}</span>
            {detail && (
                <>
                    <span
                        aria-hidden="true"
                        className="h-3 w-px bg-current opacity-30"
                    />
                    <span className="truncate font-normal opacity-90">
                        {detail}
                    </span>
                </>
            )}
        </span>
    );
}

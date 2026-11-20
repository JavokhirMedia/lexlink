/**
 * <StatusBadge status="processing" />
 *
 * Props:
 *   status:     DataRequestStatus — draft | awaiting_signature | submitted |
 *               processing | completed | rejected | failed
 *   label?:     string — override text (e.g. backend `status_label`)
 *   size?:      'sm' | 'md' (default 'md')
 *   className?: string
 *
 * Also exported: STATUS_LABELS (Uzbek labels), statusTone(status).
 */
import {
    CheckCircle2,
    CircleDashed,
    Clock,
    Loader2,
    PenLine,
    Send,
    TriangleAlert,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DataRequestStatus } from '@/types/lexlink';

export const STATUS_LABELS: Record<DataRequestStatus, string> = {
    draft: 'Qoralama',
    awaiting_signature: 'Imzo kutilmoqda',
    submitted: 'Yuborildi',
    processing: "Ko'rib chiqilmoqda",
    completed: 'Javob olindi',
    rejected: 'Rad etildi',
    failed: 'Texnik xato',
};

type Tone = 'neutral' | 'warning' | 'info' | 'success' | 'danger';

const CONFIG: Record<DataRequestStatus, { tone: Tone; icon: LucideIcon }> = {
    draft: { tone: 'neutral', icon: CircleDashed },
    awaiting_signature: { tone: 'warning', icon: PenLine },
    submitted: { tone: 'info', icon: Send },
    processing: { tone: 'info', icon: Loader2 },
    completed: { tone: 'success', icon: CheckCircle2 },
    rejected: { tone: 'danger', icon: XCircle },
    failed: { tone: 'danger', icon: TriangleAlert },
};

const TONE_CLASSES: Record<Tone, string> = {
    neutral: 'border-border bg-muted text-muted-foreground',
    warning: 'border-warning-border bg-warning-soft text-warning',
    info: 'border-info-border bg-info-soft text-info',
    success: 'border-success-border bg-success-soft text-success',
    danger: 'border-danger-border bg-danger-soft text-danger',
};

export function statusTone(status: DataRequestStatus): Tone {
    return CONFIG[status]?.tone ?? 'neutral';
}

export function StatusBadge({
    status,
    label,
    size = 'md',
    className,
}: {
    status: DataRequestStatus;
    label?: string | null;
    size?: 'sm' | 'md';
    className?: string;
}) {
    const cfg = CONFIG[status] ?? { tone: 'neutral' as Tone, icon: Clock };
    const Icon = cfg.icon;

    return (
        <span
            data-status={status}
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1.5 rounded-sm border font-medium whitespace-nowrap',
                size === 'sm'
                    ? 'px-1.5 py-px text-[11px]'
                    : 'px-2 py-0.5 text-xs',
                TONE_CLASSES[cfg.tone],
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                className={cn(
                    size === 'sm' ? 'size-3' : 'size-3.5',
                    status === 'processing' &&
                        'animate-spin [animation-duration:1.6s] motion-reduce:animate-none',
                )}
            />
            {label || STATUS_LABELS[status] || status}
        </span>
    );
}

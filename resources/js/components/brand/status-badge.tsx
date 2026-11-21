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
    neutral: 'bg-raised text-ink-secondary',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-brand-soft text-brand',
    success: 'bg-success-soft text-success',
    danger: 'bg-danger-soft text-danger',
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
                'inline-flex w-fit shrink-0 items-center gap-1.5 rounded-sm font-medium whitespace-nowrap',
                size === 'sm' ? 'h-6 px-2 text-xs' : 'h-7 px-2.5 text-sm',
                TONE_CLASSES[cfg.tone],
                className,
            )}
        >
            <Icon
                aria-hidden="true"
                strokeWidth={1.75}
                className={cn(
                    size === 'sm' ? 'size-3.5' : 'size-4',
                    status === 'processing' &&
                        'animate-spin [animation-duration:2s] motion-reduce:animate-none',
                )}
            />
            {label || STATUS_LABELS[status] || status}
        </span>
    );
}

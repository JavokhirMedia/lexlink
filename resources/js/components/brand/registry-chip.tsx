/**
 * <RegistryChip value="GOV-2026-000002" kind="gov" />
 * <RegistryChip value="ORD-2026-000457" kind="order" copyable />
 * <RegistryChip value="2-1001-2611/0457" kind="decision" />
 * <RegistryChip value="AL-00123" kind="license" />
 * <RegistryChip value="3***********12" kind="pinfl" />
 *
 * The signature detail (docs/DESIGN.md): every legal identifier is set in
 * IBM Plex Mono inside a thin bordered chip with a tiny seal glyph. Use it for
 * registry numbers only, never as decoration.
 *
 * Props:
 *   value:      string | null | undefined — rendered as "—" when empty
 *   kind?:      'gov' | 'order' | 'decision' | 'license' | 'pinfl' | 'case'
 *               (sets the accessible label; visual is identical)
 *   copyable?:  boolean — click copies the value (shows a check for 1.5s)
 *   size?:      'sm' | 'md' (default 'md'; sm for dense tables)
 *   className?: string
 */
import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

export type RegistryKind =
    | 'gov'
    | 'order'
    | 'decision'
    | 'license'
    | 'pinfl'
    | 'case';

const KIND_LABELS: Record<RegistryKind, string> = {
    gov: "Davlat so'rovi raqami",
    order: 'Advokat orderi raqami',
    decision: 'Sud qarori raqami',
    license: 'Litsenziya raqami',
    pinfl: 'JShShIR (maskalangan)',
    case: 'Ish raqami',
};

/** Tiny seal: a ring with a dotted inner ring, matching the logo mark. */
export function SealGlyph({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 12 12"
            aria-hidden="true"
            className={cn('size-3 shrink-0', className)}
        >
            <circle
                cx="6"
                cy="6"
                r="5.2"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.1"
            />
            <circle
                cx="6"
                cy="6"
                r="3"
                fill="none"
                stroke="currentColor"
                strokeWidth="0.9"
                strokeDasharray="0.9 1"
            />
        </svg>
    );
}

export function RegistryChip({
    value,
    kind,
    copyable = false,
    size = 'md',
    className,
}: {
    value: string | null | undefined;
    kind?: RegistryKind;
    copyable?: boolean;
    size?: 'sm' | 'md';
    className?: string;
}) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const t = window.setTimeout(() => setCopied(false), 1500);

        return () => window.clearTimeout(t);
    }, [copied]);

    const text = value && value.length > 0 ? value : '—';
    const label = kind ? KIND_LABELS[kind] : undefined;

    const classes = cn(
        'inline-flex w-fit max-w-full shrink-0 items-center gap-1.5 rounded-sm border border-hairline bg-surface font-mono whitespace-nowrap text-ink tabular-nums',
        size === 'sm' ? 'h-6 px-1.5 text-xs' : 'h-7 px-2 text-sm',
        className,
    );

    const inner = (
        <>
            <SealGlyph className="text-brand" />
            <span className="truncate">{text}</span>
        </>
    );

    if (copyable && value) {
        return (
            <button
                type="button"
                data-registry={kind}
                aria-label={`${label ? `${label}: ` : ''}${value}. Nusxa olish`}
                title="Nusxa olish"
                onClick={(e) => {
                    e.stopPropagation();
                    e.preventDefault();

                    try {
                        void navigator.clipboard
                            ?.writeText(value)
                            .then(() => setCopied(true))
                            .catch(() => {});
                    } catch {
                        // clipboard unavailable
                    }
                }}
                className={cn(
                    classes,
                    'focus-ring cursor-pointer transition-colors hover:border-brand-border hover:bg-brand-soft',
                )}
            >
                {inner}
                {copied ? (
                    <Check aria-hidden="true" className="size-3 text-success" />
                ) : (
                    <Copy
                        aria-hidden="true"
                        className="size-3 text-ink-muted"
                    />
                )}
            </button>
        );
    }

    return (
        <span
            data-registry={kind}
            className={classes}
            aria-label={label ? `${label}: ${text}` : undefined}
            title={label}
        >
            {inner}
        </span>
    );
}

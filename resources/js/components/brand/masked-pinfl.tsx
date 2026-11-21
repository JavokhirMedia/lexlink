/**
 * <MaskedPinfl value={request.subject_pinfl_masked} />
 *
 * Shows a JShShIR (PINFL) in mono, grouped for readability. If a raw 14-digit
 * value is passed it is masked client-side the same way as App\Support\Pinfl
 * (first digit + stars + last two). Never pass raw PINFL unless you must.
 *
 * Props:
 *   value:       string | null | undefined — masked ("3***********12") or raw
 *   label?:      boolean — prefix with "JShShIR" caption (default false)
 *   className?:  string
 *
 * Also exported: maskPinfl(value).
 */
import { cn } from '@/lib/utils';

export function maskPinfl(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    if (/^\d{14}$/.test(value)) {
        return value[0] + '*'.repeat(value.length - 3) + value.slice(-2);
    }

    return value;
}

export function MaskedPinfl({
    value,
    label = false,
    className,
}: {
    value: string | null | undefined;
    label?: boolean;
    className?: string;
}) {
    const masked = maskPinfl(value);

    return (
        <span
            className={cn('inline-flex items-baseline gap-1.5', className)}
            title="JShShIR maskalangan"
        >
            {label && (
                <span className="text-meta text-ink-secondary">JShShIR</span>
            )}
            <span className="font-mono text-[0.92em] tracking-wide text-ink tabular-nums">
                {masked ?? '—'}
            </span>
        </span>
    );
}

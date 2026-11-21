/**
 * The one number/date formatter for the whole app (docs/DESIGN.md "Numbers").
 *
 * <Money amount={55800000} />          -> "55 800 000 so'm"
 * <Money amount={-12000} signed />     -> "−12 000 so'm" (danger); positive -> "+…" (success)
 *
 * Props:
 *   amount:     number | null | undefined — integer so'm
 *   signed?:    boolean — show +/− and colour by sign (transactions)
 *   currency?:  boolean — append " so'm" (default true)
 *   className?: string
 *
 * Also exported:
 *   formatNumber(n)                       -> "1 250"   (space grouping, never commas)
 *   formatMoney(amount, { currency?, signed? }) -> "55 800 000 so'm"
 *   formatDate(value)                     -> "20.11.2026"
 *   formatDateTime(value)                 -> "20.11.2026, 14:05"
 *   formatTime(value)                     -> "14:05"
 */
import { cn } from '@/lib/utils';

/** No-break space between digit groups, so "55 800 000" never wraps. */
const GROUP = '\u00a0';

/** Integer grouping in threes with a space. Fractions (if any) use a comma. */
export function formatNumber(
    value: number | null | undefined,
    { fractionDigits = 0 }: { fractionDigits?: number } = {},
): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    const negative = value < 0;
    const fixed = Math.abs(value).toFixed(fractionDigits);
    const [int, frac] = fixed.split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, GROUP);

    return `${negative ? '−' : ''}${grouped}${frac ? `,${frac}` : ''}`;
}

export function formatMoney(
    amount: number | null | undefined,
    {
        currency = true,
        signed = false,
    }: { currency?: boolean; signed?: boolean } = {},
): string {
    if (amount === null || amount === undefined || Number.isNaN(amount)) {
        return '—';
    }

    const rounded = Math.round(amount);
    const sign =
        rounded > 0 && signed ? '+' : rounded < 0 ? '−' : '';
    const body = formatNumber(Math.abs(rounded));

    return `${sign}${body}${currency ? " so'm" : ''}`;
}

type DateInput = string | number | Date | null | undefined;

function toDate(value: DateInput): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const d = value instanceof Date ? value : new Date(value);

    return Number.isNaN(d.getTime()) ? null : d;
}

const pad = (n: number) => String(n).padStart(2, '0');

/** "20.11.2026" */
export function formatDate(value: DateInput): string {
    const d = toDate(value);

    if (!d) {
        return '—';
    }

    return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`;
}

/** "14:05" */
export function formatTime(value: DateInput): string {
    const d = toDate(value);

    if (!d) {
        return '—';
    }

    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** "20.11.2026, 14:05" */
export function formatDateTime(value: DateInput): string {
    const d = toDate(value);

    if (!d) {
        return '—';
    }

    return `${formatDate(d)}, ${formatTime(d)}`;
}

export function Money({
    amount,
    signed = false,
    currency = true,
    className,
}: {
    amount: number | null | undefined;
    signed?: boolean;
    currency?: boolean;
    className?: string;
}) {
    const n = amount ?? null;

    return (
        <span
            className={cn(
                'whitespace-nowrap tabular-nums',
                signed && n !== null && n > 0 && 'text-success',
                signed && n !== null && n < 0 && 'text-danger',
                className,
            )}
        >
            {formatMoney(amount, { currency, signed })}
        </span>
    );
}

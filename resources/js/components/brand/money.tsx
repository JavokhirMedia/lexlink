/**
 * <Money amount={149000} />            -> "149 000 so'm"
 * <Money amount={-12000} signed />     -> "−12 000 so'm" (red); positive -> "+…" (green)
 *
 * Props:
 *   amount:     number | null | undefined — integer so'm
 *   signed?:    boolean — show +/− and colour by sign (transactions)
 *   currency?:  boolean — append " so'm" (default true)
 *   className?: string
 *
 * Also exported: formatMoney(amount, { currency?, signed? }) -> string.
 */
import { cn } from '@/lib/utils';

const formatter = new Intl.NumberFormat('uz-UZ', { maximumFractionDigits: 0 });

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

    const sign = signed
        ? amount > 0
            ? '+'
            : amount < 0
              ? '−'
              : ''
        : amount < 0
          ? '−'
          : '';
    const body = formatter.format(Math.abs(amount));

    return `${sign}${body}${currency ? "\u00a0so'm" : ''}`;
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
    return (
        <span
            className={cn(
                'whitespace-nowrap tabular-nums',
                signed &&
                    amount !== null &&
                    amount !== undefined &&
                    amount > 0 &&
                    'text-success',
                signed &&
                    amount !== null &&
                    amount !== undefined &&
                    amount < 0 &&
                    'text-danger',
                className,
            )}
        >
            {formatMoney(amount, { currency, signed })}
        </span>
    );
}

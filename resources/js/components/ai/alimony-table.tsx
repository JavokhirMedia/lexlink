import { formatNumber } from '@/components/brand/money';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { AlimonyCalculation } from '@/types/lexlink';
import { formatSum } from './format';

/**
 * Alimony calculation (done in PHP, not by the model): four figures in one strip,
 * then the share table. The row for this case is emphasised with brand-soft.
 */
export function AlimonyTable({
    calc,
    className,
}: {
    calc: AlimonyCalculation;
    className?: string;
}) {
    const selected = calc.selected?.children ?? null;
    const floorUsed = calc.options.some((o) => o.floor_applied);

    return (
        <div className={cn('flex flex-col gap-4 @container', className)}>
            <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-hairline @xl:grid-cols-4">
                <Stat
                    label="Davr"
                    value={
                        calc.period.from
                            ? `${calc.period.from} — ${calc.period.to}`
                            : '—'
                    }
                />
                <Stat
                    label="Oylar soni"
                    value={formatNumber(calc.months_count)}
                />
                <Stat
                    label="Jami daromad"
                    value={formatSum(calc.total_income)}
                />
                <Stat
                    label="O'rtacha oylik"
                    value={formatSum(calc.average_monthly)}
                    strong
                />
            </dl>

            <div className="overflow-hidden rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Bolalar soni</TableHead>
                            <TableHead>Ulush</TableHead>
                            <TableHead className="text-right">
                                Aliment / oy
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {calc.options.map((o) => {
                            const active =
                                selected !== null &&
                                Math.min(selected, 3) === o.children;

                            return (
                                <TableRow
                                    key={o.children}
                                    aria-current={active ? 'true' : undefined}
                                    className={cn(
                                        active &&
                                            'bg-brand-soft font-medium hover:bg-brand-soft',
                                    )}
                                >
                                    <TableCell className="sm:whitespace-nowrap">
                                        {o.label}
                                        {active && (
                                            <span className="block text-meta font-normal text-brand sm:ml-2 sm:inline">
                                                ushbu ish
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-meta">
                                        {o.share}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        {formatSum(
                                            active && calc.selected
                                                ? calc.selected.amount
                                                : o.amount,
                                        )}
                                        {o.floor_applied && (
                                            <span className="text-ink-muted">
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
            <p className="text-meta text-ink-secondary">
                Hisob PHP'da bajarilgan, model emas: {calc.legal_basis} — 1 bola
                1/4, 2 bola 1/3, 3 va undan ortiq 1/2.
                {floorUsed &&
                    ` * Har bir bola uchun eng kam miqdor: eng kam ish haqining 26,5 % i (${formatSum(calc.min_per_child)}).`}
            </p>
        </div>
    );
}

function Stat({
    label,
    value,
    strong,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1 bg-card px-4 py-3">
            <dt className="text-meta text-ink-secondary">{label}</dt>
            <dd
                className={cn(
                    'text-base font-semibold break-words tabular-nums',
                    strong ? 'text-brand' : 'text-ink',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

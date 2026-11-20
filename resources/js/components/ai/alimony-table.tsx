import { cn } from '@/lib/utils';
import type { AlimonyCalculation } from '@/types/lexlink';
import { formatSum } from './format';

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
        <div className={cn('space-y-3', className)}>
            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Stat
                    label="Davr"
                    value={
                        calc.period.from
                            ? `${calc.period.from} — ${calc.period.to}`
                            : '—'
                    }
                />
                <Stat label="Oylar soni" value={String(calc.months_count)} />
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

            <div className="overflow-x-auto rounded-md border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-3 py-2 font-medium">
                                Bolalar soni
                            </th>
                            <th className="px-3 py-2 font-medium">Ulush</th>
                            <th className="px-3 py-2 text-right font-medium">
                                Aliment / oy
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {calc.options.map((o) => {
                            const active =
                                selected !== null &&
                                Math.min(selected, 3) === o.children;

                            return (
                                <tr
                                    key={o.children}
                                    className={cn(
                                        'border-t',
                                        active && 'bg-primary/5 font-medium',
                                    )}
                                >
                                    <td className="px-3 py-2">
                                        {o.label}
                                        {active && (
                                            <span className="ml-2 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                                ishda
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {o.share}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {formatSum(
                                            active && calc.selected
                                                ? calc.selected.amount
                                                : o.amount,
                                        )}
                                        {o.floor_applied && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-muted-foreground">
                Hisob PHP da bajarilgan (model emas): {calc.legal_basis} — 1
                bola 1/4, 2 bola 1/3, 3 va undan ortiq 1/2.
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
        <div className="rounded-md border bg-background px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'mt-0.5 text-sm tabular-nums',
                    strong && 'font-semibold text-primary',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

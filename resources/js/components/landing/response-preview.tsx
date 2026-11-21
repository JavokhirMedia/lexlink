/**
 * <ResponsePreview />
 *
 * The landing "moment" (docs/DESIGN.md): a code-built agency response document
 * as it appears in LexLink, with fictional data. On small screens it drops the
 * subject row, employer column, stamp and audit note so nothing is zoomed down.
 */
import { CalendarCheck, Eye } from 'lucide-react';
import { formatMoney } from '@/components/brand/money';
import { RegistryChip, SealGlyph } from '@/components/brand/registry-chip';
import { StatusBadge } from '@/components/brand/status-badge';

const ROWS = [
    { month: 'Avgust 2026', employer: '«Navbahor tekstil» MChJ', amount: 18_200_000 },
    { month: 'Sentabr 2026', employer: '«Navbahor tekstil» MChJ', amount: 18_600_000 },
    { month: 'Oktabr 2026', employer: '«Navbahor tekstil» MChJ', amount: 19_000_000 },
];

const TOTAL = ROWS.reduce((n, r) => n + r.amount, 0); // 55 800 000

/** A round e-signature stamp, drawn in code. Text sits on a circular path. */
function Stamp({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 120 120"
            aria-hidden="true"
            className={className}
        >
            <defs>
                <path
                    id="lexlink-stamp-ring"
                    d="M60 60m-45 0a45 45 0 1 1 90 0a45 45 0 1 1 -90 0"
                />
            </defs>
            <g fill="none" stroke="currentColor">
                <circle cx="60" cy="60" r="57" strokeWidth="2" />
                <circle cx="60" cy="60" r="53" strokeWidth="0.75" />
                <circle
                    cx="60"
                    cy="60"
                    r="37"
                    strokeWidth="1"
                    strokeDasharray="1.5 2.5"
                />
            </g>
            <text
                fill="currentColor"
                fontSize="11"
                fontFamily="IBM Plex Sans, sans-serif"
                fontWeight="500"
                >
                <textPath
                    href="#lexlink-stamp-ring"
                    startOffset="0"
                    textLength="276"
                    lengthAdjust="spacing"
                >
                    Elektron raqamli imzo · tasdiqlangan · 
                </textPath>
            </text>
            <text
                x="60"
                y="57"
                textAnchor="middle"
                fill="currentColor"
                fontSize="11"
                fontFamily="IBM Plex Mono, monospace"
            >
                12.11.2026
            </text>
            <text
                x="60"
                y="72"
                textAnchor="middle"
                fill="currentColor"
                fontSize="11"
                fontFamily="IBM Plex Sans, sans-serif"
                fontWeight="600"
            >
                Imzolangan
            </text>
        </svg>
    );
}

export function ResponsePreview() {
    return (
        <figure
            aria-label="Idora javobi namunasi, ma'lumotlar uydirma"
            className="relative w-full"
        >
            <div className="overflow-hidden rounded-xl border border-hairline bg-surface shadow-float">
                {/* Document header */}
                <div className="flex items-start justify-between gap-4 border-b border-hairline px-5 py-4 sm:px-6">
                    <div className="flex min-w-0 flex-col gap-1">
                        <span className="flex items-center gap-2 text-sm text-ink-secondary">
                            <SealGlyph className="size-3.5 text-brand" />
                            Idora javobi
                        </span>
                        <h3 className="text-lg font-semibold text-ink">
                            Ish haqi to'g'risida ma'lumot
                        </h3>
                    </div>
                    <StatusBadge status="completed" label="Tayyor" />
                </div>

                {/* Registry details */}
                <div className="relative px-5 py-4 sm:px-6">
                    <dl className="grid grid-cols-[96px_minmax(0,1fr)] items-center gap-x-4 gap-y-3 text-sm sm:grid-cols-[112px_minmax(0,1fr)]">
                        <dt className="text-ink-secondary">So'rov</dt>
                        <dd>
                            <RegistryChip value="GOV-2026-000002" kind="gov" size="sm" />
                        </dd>
                        <dt className="text-ink-secondary">Idora</dt>
                        <dd className="text-ink">Soliq va pensiya ma'lumotlari</dd>
                        <dt className="hidden text-ink-secondary sm:block">
                            Subyekt
                        </dt>
                        <dd className="hidden sm:block">
                            <RegistryChip value="3***********12" kind="pinfl" size="sm" />
                        </dd>
                        <dt className="text-ink-secondary">Asos</dt>
                        <dd className="flex flex-wrap items-center gap-2">
                            <RegistryChip
                                value="2-1001-2611/0457"
                                kind="decision"
                                size="sm"
                            />
                            <span className="hidden text-ink-secondary sm:inline">
                                sud qarori
                            </span>
                        </dd>
                    </dl>
                    <Stamp className="absolute top-3 right-5 hidden size-[112px] -rotate-12 text-brand/80 md:block" />
                </div>

                {/* Salary table */}
                <table className="w-full border-t border-hairline text-sm tabular-nums">
                    <thead className="bg-raised">
                        <tr>
                            <th className="h-9 px-5 text-left font-medium sm:px-6">
                                Oy
                            </th>
                            <th className="hidden h-9 px-4 text-left font-medium sm:table-cell">
                                Ish beruvchi
                            </th>
                            <th className="h-9 px-5 text-right font-medium sm:px-6">
                                Hisoblangan
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {ROWS.map((r) => (
                            <tr key={r.month} className="border-t border-hairline">
                                <td className="h-11 px-5 text-ink sm:px-6">{r.month}</td>
                                <td className="hidden h-11 px-4 text-ink-secondary sm:table-cell">
                                    {r.employer}
                                </td>
                                <td className="h-11 px-5 text-right whitespace-nowrap text-ink sm:px-6">
                                    {formatMoney(r.amount)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t border-hairline">
                            <td
                                colSpan={1}
                                className="h-12 px-5 font-medium text-ink sm:hidden"
                            >
                                Jami
                            </td>
                            <td
                                colSpan={2}
                                className="hidden h-12 px-6 font-medium text-ink sm:table-cell"
                            >
                                Jami, 3 oy
                            </td>
                            <td className="h-12 px-5 text-right text-base font-semibold whitespace-nowrap text-ink sm:px-6">
                                {formatMoney(TOTAL)}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                {/* Footer: deadline + audit */}
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-hairline bg-page px-5 py-3 sm:px-6">
                    <span className="inline-flex h-7 items-center gap-1.5 rounded-sm bg-success-soft px-2.5 text-sm font-medium text-success">
                        <CalendarCheck
                            aria-hidden="true"
                            strokeWidth={1.75}
                            className="size-4"
                        />
                        Muddatida javob berildi
                    </span>
                    <span className="hidden items-center gap-1.5 text-meta text-ink-secondary sm:inline-flex">
                        <Eye aria-hidden="true" strokeWidth={1.5} className="size-4" />
                        Har bir ko'rish auditda qayd etiladi
                    </span>
                </div>
            </div>
        </figure>
    );
}

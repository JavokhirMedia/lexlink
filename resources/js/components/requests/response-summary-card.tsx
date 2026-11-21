import { Archive, TriangleAlert } from "lucide-react";
import type { ReactNode } from "react";
import { formatMoney, formatNumber } from "@/components/brand/money";
import { Section } from "@/components/brand/section";
import { StatTile } from "@/components/brand/stat-tile";
import type { ResponseMeta, ResponseSummary } from "./types";
import {
    formatCurrency,
    formatDate,
    formatDateTime,
    formatMonth,
} from "./types";

const PHP_NOTE = "PHP'da hisoblangan";

type Tile = { label: string; value: ReactNode; hint?: ReactNode };
type Fact = { label: string; value: ReactNode };

/** Numbers go into tiles, words into a short definition list under them. */
function describe(summary: ResponseSummary): {
    tiles: Tile[];
    facts: Fact[];
    alert?: ReactNode;
} {
    switch (summary.type) {
        case "salary":
            return {
                tiles: [
                    {
                        label: "Davr",
                        value: `${formatNumber(summary.months_count)} oy`,
                        hint: `${formatMonth(summary.period_from)} — ${formatMonth(summary.period_to)}`,
                    },
                    {
                        label: "Ish beruvchilar",
                        value: formatNumber(summary.employers_count),
                    },
                    {
                        label: "Jami daromad",
                        value: formatMoney(summary.total),
                        hint: PHP_NOTE,
                    },
                    {
                        label: "O'rtacha oylik",
                        value: formatMoney(summary.average_monthly),
                        hint: PHP_NOTE,
                    },
                ],
                facts: [],
            };
        case "property":
            return {
                tiles: [
                    {
                        label: "Obyektlar",
                        value: formatNumber(summary.objects_count),
                    },
                    {
                        label: "Umumiy maydon",
                        value: `${formatNumber(summary.total_area, {
                            fractionDigits: Number.isInteger(summary.total_area)
                                ? 0
                                : 1,
                        })} m²`,
                    },
                    {
                        label: "Cheklov qo'yilgan",
                        value: formatNumber(summary.encumbered_count),
                    },
                ],
                facts: [
                    {
                        label: "Turlari",
                        value:
                            Object.entries(summary.types)
                                .map(([t, n]) => `${t}: ${n}`)
                                .join(", ") || "—",
                    },
                ],
            };
        case "vehicle":
            return {
                tiles: [
                    {
                        label: "Transport vositalari",
                        value: formatNumber(summary.vehicles_count),
                    },
                    {
                        label: "Garovda",
                        value: formatNumber(summary.pledged_count),
                    },
                    {
                        label: "Eng yangi yil",
                        value: summary.newest_year ?? "—",
                    },
                ],
                facts: [],
            };
        case "tax_info":
            return {
                tiles: [
                    {
                        label: "Soliq qarzi",
                        value: formatMoney(summary.tax_debt),
                    },
                    {
                        label: "O'tgan yili to'langan",
                        value: formatMoney(summary.taxes_paid_last_year),
                    },
                    {
                        label: "Yuridik shaxslarda ishtirok",
                        value: formatNumber(summary.participations_count),
                    },
                ],
                facts: [
                    {
                        label: "YaTT",
                        value: summary.is_individual_entrepreneur
                            ? `Ha${summary.ie_activity ? ` — ${summary.ie_activity}` : ""}`
                            : "Yo'q",
                    },
                    {
                        label: "QQS to'lovchi",
                        value: summary.vat_payer ? "Ha" : "Yo'q",
                    },
                    ...(summary.tax_regime
                        ? [{ label: "Soliq rejimi", value: summary.tax_regime }]
                        : []),
                ],
            };
        case "business_shares":
            return {
                tiles: [
                    {
                        label: "Yuridik shaxslar",
                        value: formatNumber(summary.companies_count),
                        hint: `faol: ${formatNumber(summary.active_count)}`,
                    },
                    {
                        label: "Ulushlar qiymati (nominal)",
                        value: formatMoney(summary.total_share_value),
                        hint: `Ustav fondi × ulush, ${PHP_NOTE}`,
                    },
                    {
                        label: "Eng katta ulush",
                        value:
                            summary.largest_share_percent !== null
                                ? `${formatNumber(summary.largest_share_percent)}%`
                                : "—",
                    },
                ],
                facts: [],
            };
        case "bank_accounts":
            return {
                tiles: [
                    {
                        label: "Hisobvaraqlar",
                        value: formatNumber(summary.accounts_count),
                        hint: `${formatNumber(summary.banks_count)} ta bankda`,
                    },
                    ...Object.entries(summary.totals_by_currency).map(
                        ([cur, t]) => ({
                            label: `Qoldiq (${cur})`,
                            value: formatCurrency(t.balance, cur),
                            hint: `${summary.turnover_months} oyda kirim ${formatCurrency(t.turnover_in, cur)}`,
                        }),
                    ),
                    {
                        label: "Cheklov qo'yilgan",
                        value: formatNumber(summary.restricted_count),
                    },
                ],
                facts: [],
            };
        case "border_crossing":
            return {
                alert: summary.currently_abroad
                    ? `Shaxs hozir chet elda: ${formatDate(summary.last_exit_date)} dan beri${summary.last_exit_country ? ` · ${summary.last_exit_country}` : ""}`
                    : undefined,
                tiles: [
                    {
                        label: "Joriy safar",
                        value: `${formatNumber(summary.days_abroad_current)} kun`,
                    },
                    {
                        label: "So'nggi 12 oyda chet elda",
                        value: `${formatNumber(summary.days_abroad_last_12m)} kun`,
                        hint: PHP_NOTE,
                    },
                    {
                        label: "Chegaradan o'tishlar",
                        value: formatNumber(summary.crossings_count),
                        hint: `chiqish: ${formatNumber(summary.exits_count)}`,
                    },
                ],
                facts: [
                    {
                        label: "Hozir chet eldami",
                        value: summary.currently_abroad ? "Ha" : "Yo'q",
                    },
                    {
                        label: "Davlatlar",
                        value: summary.countries.join(", ") || "—",
                    },
                ],
            };
        case "court_case":
            return {
                tiles: [
                    {
                        label: "Sud majlislari",
                        value: formatNumber(summary.hearings_count),
                        hint: summary.next_hearing
                            ? `keyingisi ${formatDate(summary.next_hearing)}`
                            : undefined,
                    },
                    {
                        label: "Qarorlar",
                        value: formatNumber(summary.decisions_count),
                    },
                    {
                        label: "Taraflar",
                        value: formatNumber(summary.parties_count),
                    },
                ],
                facts: [
                    {
                        label: "Ish raqami",
                        value: summary.case_number ?? "Topilmadi",
                    },
                    { label: "Sud", value: summary.court ?? "—" },
                    { label: "Toifa", value: summary.category ?? "—" },
                    { label: "Holati", value: summary.status ?? "—" },
                ],
            };
    }
}

/** Non-PII summary kept after purge. Props: response (ResponseMeta) */
export function ResponseSummaryCard({ response }: { response: ResponseMeta }) {
    const d = response.summary ? describe(response.summary) : null;

    return (
        <Section
            title="Idora javobi — qisqacha"
            description={
                <>
                    Qabul qilindi {formatDateTime(response.received_at)}
                    {!response.purged &&
                        response.expires_at &&
                        ` · to'liq javob ${formatDate(response.expires_at)} gacha saqlanadi`}
                </>
            }
            contentClassName="flex flex-col gap-4"
        >
            {d?.alert && (
                <p className="flex items-start gap-2.5 rounded-lg border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger">
                    <TriangleAlert
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden
                    />
                    {d.alert}
                </p>
            )}

            {d ? (
                <div className="@container">
                    <div className="grid grid-cols-1 gap-4 @md:grid-cols-2 @4xl:grid-cols-4">
                        {d.tiles.map((t) => (
                            <StatTile
                                key={t.label}
                                label={t.label}
                                value={t.value}
                                hint={t.hint}
                            />
                        ))}
                    </div>
                </div>
            ) : (
                <p className="rounded-lg border bg-card px-6 py-4 text-sm text-ink-secondary">
                    Javob mazmuni sizga ko'rsatilmaydi, faqat metama'lumotlar
                </p>
            )}

            {(d?.facts.length || response.gov_signature) && (
                <dl className="grid gap-x-8 gap-y-3 rounded-lg border bg-card px-6 py-4 text-sm sm:grid-cols-2">
                    {d?.facts.map((f) => (
                        <div key={f.label} className="flex min-w-0 flex-col">
                            <dt className="text-meta text-ink-secondary">
                                {f.label}
                            </dt>
                            <dd className="break-words text-ink">{f.value}</dd>
                        </div>
                    ))}
                    {response.gov_signature && (
                        <div className="flex min-w-0 flex-col sm:col-span-2">
                            <dt className="text-meta text-ink-secondary">
                                Idora elektron imzosi (demo)
                            </dt>
                            <dd
                                className="truncate font-mono text-meta text-ink-muted"
                                title={response.gov_signature}
                            >
                                {response.gov_signature}
                            </dd>
                        </div>
                    )}
                </dl>
            )}

            {response.purged && response.purged_notice && (
                <p className="flex items-start gap-2.5 rounded-lg border bg-raised px-4 py-3 text-sm text-ink-secondary">
                    <Archive
                        className="mt-0.5 size-4 shrink-0"
                        strokeWidth={1.5}
                        aria-hidden
                    />
                    {response.purged_notice}
                </p>
            )}
        </Section>
    );
}

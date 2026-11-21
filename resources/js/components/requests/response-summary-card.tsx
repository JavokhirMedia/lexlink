import { Archive, FileCheck2 } from "lucide-react";
import { formatMoney } from "@/components/brand/money";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import type { ResponseMeta, ResponseSummary } from "./types";
import {
    formatCurrency,
    formatDate,
    formatDateTime,
    formatMonth,
} from "./types";

function Figure({
    label,
    value,
    hint,
}: {
    label: string;
    value: React.ReactNode;
    hint?: React.ReactNode;
}) {
    return (
        <div className="min-w-0 rounded-md border bg-background p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-lg font-semibold tabular-nums">{value}</p>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function Figures({ summary }: { summary: ResponseSummary }) {
    switch (summary.type) {
        case "salary":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Figure
                        label="Davr"
                        value={`${summary.months_count} oy`}
                        hint={`${formatMonth(summary.period_from)} — ${formatMonth(summary.period_to)}`}
                    />
                    <Figure
                        label="Ish beruvchilar"
                        value={summary.employers_count}
                    />
                    <Figure
                        label="Jami daromad"
                        value={formatMoney(summary.total)}
                    />
                    <Figure
                        label="O'rtacha oylik"
                        value={formatMoney(summary.average_monthly)}
                        hint="PHP'da hisoblangan"
                    />
                </div>
            );
        case "property":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Figure label="Obyektlar" value={summary.objects_count} />
                    <Figure
                        label="Umumiy maydon"
                        value={`${summary.total_area} m²`}
                    />
                    <Figure
                        label="Cheklov qo'yilgan"
                        value={summary.encumbered_count}
                    />
                    <Figure
                        label="Turlari"
                        value={
                            <span className="text-sm font-medium">
                                {Object.entries(summary.types)
                                    .map(([t, n]) => `${t}: ${n}`)
                                    .join(", ") || "—"}
                            </span>
                        }
                    />
                </div>
            );
        case "vehicle":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <Figure
                        label="Transport vositalari"
                        value={summary.vehicles_count}
                    />
                    <Figure label="Garovda" value={summary.pledged_count} />
                    <Figure
                        label="Eng yangi yil"
                        value={summary.newest_year ?? "—"}
                    />
                </div>
            );
        case "tax_info":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Figure
                        label="Soliq qarzi"
                        value={formatMoney(summary.tax_debt)}
                    />
                    <Figure
                        label="O'tgan yili to'langan"
                        value={formatMoney(summary.taxes_paid_last_year)}
                    />
                    <Figure
                        label="YaTT"
                        value={
                            <span className="text-sm">
                                {summary.is_individual_entrepreneur
                                    ? "Ha"
                                    : "Yo'q"}
                            </span>
                        }
                        hint={summary.ie_activity ?? undefined}
                    />
                    <Figure
                        label="Yuridik shaxslarda ishtirok"
                        value={summary.participations_count}
                        hint={summary.vat_payer ? "QQS to'lovchi" : undefined}
                    />
                </div>
            );
        case "business_shares":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <Figure
                        label="Yuridik shaxslar"
                        value={summary.companies_count}
                        hint={`faol: ${summary.active_count}`}
                    />
                    <Figure
                        label="Ulushlar qiymati (nominal)"
                        value={formatMoney(summary.total_share_value)}
                        hint="Ustav fondi × ulush, PHP'da hisoblangan"
                    />
                    <Figure
                        label="Eng katta ulush"
                        value={
                            summary.largest_share_percent !== null
                                ? `${summary.largest_share_percent}%`
                                : "—"
                        }
                    />
                </div>
            );
        case "bank_accounts":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Figure
                        label="Hisobvaraqlar"
                        value={summary.accounts_count}
                        hint={`${summary.banks_count} ta bankda`}
                    />
                    {Object.entries(summary.totals_by_currency).map(
                        ([cur, t]) => (
                            <Figure
                                key={cur}
                                label={`Qoldiq (${cur})`}
                                value={formatCurrency(t.balance, cur)}
                                hint={`${summary.turnover_months} oyda kirim ${formatCurrency(t.turnover_in, cur)}`}
                            />
                        ),
                    )}
                    <Figure
                        label="Cheklov qo'yilgan"
                        value={summary.restricted_count}
                    />
                </div>
            );
        case "border_crossing":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Figure
                        label="Hozir chet eldami"
                        value={
                            <span
                                className={
                                    summary.currently_abroad
                                        ? "text-danger"
                                        : undefined
                                }
                            >
                                {summary.currently_abroad ? "Ha" : "Yo'q"}
                            </span>
                        }
                        hint={
                            summary.currently_abroad
                                ? `${formatDate(summary.last_exit_date)} dan beri · ${summary.last_exit_country ?? ""}`
                                : undefined
                        }
                    />
                    <Figure
                        label="Joriy safar"
                        value={`${summary.days_abroad_current} kun`}
                    />
                    <Figure
                        label="So'nggi 12 oyda chet elda"
                        value={`${summary.days_abroad_last_12m} kun`}
                        hint="PHP'da hisoblangan"
                    />
                    <Figure
                        label="Chegaradan o'tishlar"
                        value={summary.crossings_count}
                        hint={summary.countries.join(", ") || undefined}
                    />
                </div>
            );
        case "court_case":
            return (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Figure
                        label="Ish raqami"
                        value={
                            <span className="text-sm">
                                {summary.case_number ?? "Topilmadi"}
                            </span>
                        }
                        hint={summary.court}
                    />
                    <Figure
                        label="Holati"
                        value={
                            <span className="text-sm">
                                {summary.status ?? "—"}
                            </span>
                        }
                        hint={summary.category}
                    />
                    <Figure
                        label="Keyingi majlis"
                        value={
                            <span className="text-sm">
                                {formatDate(summary.next_hearing)}
                            </span>
                        }
                        hint={`${summary.hearings_count} ta majlis`}
                    />
                    <Figure
                        label="Qarorlar"
                        value={summary.decisions_count}
                        hint={`${summary.parties_count} ta taraf`}
                    />
                </div>
            );
    }
}

/** Non-PII summary kept after purge. Props: response (ResponseMeta) */
export function ResponseSummaryCard({ response }: { response: ResponseMeta }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileCheck2 className="size-5 text-success" aria-hidden />
                    Idora javobi — qisqacha
                </CardTitle>
                <CardDescription>
                    Qabul qilindi {formatDateTime(response.received_at)}
                    {!response.purged &&
                        response.expires_at &&
                        ` · to'liq javob ${formatDate(response.expires_at)} gacha saqlanadi`}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {response.summary ? (
                    <Figures summary={response.summary} />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Javob mazmuni sizga ko'rsatilmaydi — faqat
                        metama'lumotlar.
                    </p>
                )}
                {response.purged && (
                    <div className="flex items-start gap-2 rounded-md border bg-muted/50 p-3 text-sm text-muted-foreground">
                        <Archive
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        {response.purged_notice}
                    </div>
                )}
                {response.gov_signature && (
                    <p className="font-mono text-xs break-all text-muted-foreground">
                        Idora imzosi: {response.gov_signature}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

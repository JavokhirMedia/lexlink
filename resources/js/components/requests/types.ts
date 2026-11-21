import type { DataRequest, DataRequestType } from "@/types/lexlink";

export type RequestTypeOption = {
    value: DataRequestType;
    label: string;
    agency: string;
    secrecy_regime: string;
    accepted_bases: { value: string; label: string }[];
    access?: "order" | "court";
    domain?: string | null;
};

export type BasisCheck = {
    ok: boolean;
    reason: string | null;
    basis: {
        id: number;
        type: string;
        type_label: string;
        number: string;
        court_name: string | null;
        expires_at: string | null;
        subject_name: string | null;
        subject_pinfl_masked: string | null;
    } | null;
};

export type Quote = { cost: number; from_subscription: boolean };

export type TimelineStep = {
    key: string;
    label: string;
    state: "done" | "current" | "upcoming" | "success" | "danger";
    at: string | null;
    note: string | null;
};

export type SignerKey = {
    id: string;
    owner: string;
    stir: string;
    license_number: string | null;
    issuer: string;
    valid_to: string;
};

export type SalarySummary = {
    type: "salary";
    period_from: string | null;
    period_to: string | null;
    months_count: number;
    employers_count: number;
    total: number;
    average_monthly: number;
    pension_total: number;
};
export type PropertySummary = {
    type: "property";
    objects_count: number;
    total_area: number;
    types: Record<string, number>;
    encumbered_count: number;
};
export type VehicleSummary = {
    type: "vehicle";
    vehicles_count: number;
    pledged_count: number;
    newest_year: number | null;
};
export type CourtCaseSummary = {
    type: "court_case";
    found: boolean;
    case_number: string | null;
    court: string | null;
    category: string | null;
    status: string | null;
    parties_count: number;
    hearings_count: number;
    decisions_count: number;
    next_hearing: string | null;
};
export type TaxInfoSummary = {
    type: "tax_info";
    tin_present: boolean;
    tax_regime: string | null;
    vat_payer: boolean;
    is_individual_entrepreneur: boolean;
    ie_activity: string | null;
    tax_debt: number;
    taxes_paid_last_year: number;
    participations_count: number;
};
export type BusinessSharesSummary = {
    type: "business_shares";
    companies_count: number;
    active_count: number;
    total_share_value: number;
    largest_share_percent: number | null;
};
export type CurrencyTotals = {
    balance: number;
    turnover_in: number;
    turnover_out: number;
    accounts: number;
};
export type BankAccountsSummary = {
    type: "bank_accounts";
    accounts_count: number;
    banks_count: number;
    totals_by_currency: Record<string, CurrencyTotals>;
    restricted_count: number;
    turnover_months: number;
};
export type BorderCrossingSummary = {
    type: "border_crossing";
    crossings_count: number;
    exits_count: number;
    currently_abroad: boolean;
    last_exit_date: string | null;
    last_exit_country: string | null;
    days_abroad_current: number;
    days_abroad_last_12m: number;
    countries: string[];
};
export type ResponseSummary =
    | SalarySummary
    | PropertySummary
    | VehicleSummary
    | CourtCaseSummary
    | TaxInfoSummary
    | BusinessSharesSummary
    | BankAccountsSummary
    | BorderCrossingSummary;

const amountFmt = new Intl.NumberFormat("uz-UZ", { maximumFractionDigits: 0 });

/** 18436500, "UZS" -> "18 436 500 so'm"; 2350, "USD" -> "2 350 USD" */
export function formatCurrency(amount: number, currency: string): string {
    return currency === "UZS"
        ? `${amountFmt.format(amount)} so'm`
        : `${amountFmt.format(amount)} ${currency}`;
}

export type ResponseMeta = {
    received_at: string | null;
    expires_at: string | null;
    purged: boolean;
    purged_at: string | null;
    purged_notice: string | null;
    gov_signature: string | null;
    summary: ResponseSummary | null;
    restricted: boolean;
};

export type RequestDetail = DataRequest & {
    creator: { id: number; name: string } | null;
    signer: { id: number; name: string; license_number: string | null } | null;
    signature: string | null;
    failure_reason: string | null;
    charged_from_subscription: boolean;
    legal_basis: {
        id: number;
        type: string;
        type_label: string;
        number: string;
        court_name: string | null;
        expires_at: string | null;
    } | null;
    secrecy_regime: string;
    updated_at: string | null;
};

const dateFmt = new Intl.DateTimeFormat("uz-UZ", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
});
const dateTimeFmt = new Intl.DateTimeFormat("uz-UZ", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
});

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return "—";
    }
    const d = new Date(value);

    return Number.isNaN(d.getTime()) ? value : dateFmt.format(d);
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return "—";
    }
    const d = new Date(value);

    return Number.isNaN(d.getTime()) ? value : dateTimeFmt.format(d);
}

const MONTHS = [
    "yanvar",
    "fevral",
    "mart",
    "aprel",
    "may",
    "iyun",
    "iyul",
    "avgust",
    "sentabr",
    "oktabr",
    "noyabr",
    "dekabr",
];

/** "2026-05" -> "may 2026" */
export function formatMonth(value: string | null | undefined): string {
    if (!value) {
        return "—";
    }
    const [y, m] = value.split("-");
    const idx = Number(m) - 1;

    return MONTHS[idx] ? `${MONTHS[idx]} ${y}` : value;
}

export const textareaClass =
    "border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm";

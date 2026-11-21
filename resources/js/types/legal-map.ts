/** Shape of App\Support\LegalMatrix::toArray() — the "Huquqiy xarita" (single source of truth on the server). */
/** 'order' — advokat so'rovi + order yetarli (Advokatura 7¹); 'court' — faqat sud qarori / ajrimi. */
export type LegalAccess = "order" | "court";

export type LegalDomainKey = "court" | "tax" | "property" | "bank" | "border";

export type LegalRef = {
    law: string;
    article: string | null;
    note: string;
    url: string | null;
};

export type LegalMatrixType = {
    /** DataRequestType value (court_case, tax_info, salary, property, vehicle, business_shares, bank_accounts, border_crossing). */
    value: string;
    label: string;
    agency: string;
    access: LegalAccess;
    items: string[];
    why: string;
    refs: LegalRef[];
    /** true when the law reference still needs verification against lex.uz. */
    verify?: boolean;
    accepted_bases: { value: string; label: string }[];
};

export type LegalDomain = {
    key: LegalDomainKey | string;
    title: string;
    summary: string;
    types: LegalMatrixType[];
};

export type LegalMatrix = {
    domains: LegalDomain[];
    out_of_scope: { title: string; why: string }[];
    deadlines: {
        default_days: number;
        no_info_notice_days: number;
        proceedings_work_days: number;
    };
    disclaimer: string;
};

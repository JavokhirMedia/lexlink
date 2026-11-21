// Shared LexLink domain types. Extend (don't rename) fields; backend serializes snake_case.

export type UserRole = 'advocate' | 'lawyer' | 'admin' | 'client';

export type SharedAuthUser = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: UserRole | null;
    role_label: string | null;
    license: { number: string; status: 'active' | 'revoked' | 'suspended' | 'pending' } | null;
    can_sign: boolean;
};

export type DataRequestType = 'court_case' | 'tax_info' | 'salary' | 'property' | 'vehicle' | 'business_shares' | 'bank_accounts' | 'border_crossing';

export type DataRequestStatus =
    | 'draft'
    | 'awaiting_signature'
    | 'submitted'
    | 'processing'
    | 'completed'
    | 'rejected'
    | 'failed';

export type LegalBasisType = 'court_decision' | 'advocate_order';

export type LegalCase = {
    id: number;
    title: string;
    court_case_number: string | null;
    client_name: string;
    category: string | null;
    description: string | null;
    status: 'active' | 'closed';
    owner: { id: number; name: string };
    created_at: string;
};

export type LegalBasis = {
    id: number;
    type: LegalBasisType;
    type_label: string;
    number: string;
    issued_at: string | null;
    expires_at: string | null;
    subject_pinfl_masked: string | null;
    subject_name: string | null;
    court_name: string | null;
    allowed_types: DataRequestType[];
    verification_status: 'pending' | 'verified' | 'not_found' | 'expired' | 'mismatch';
    verified_at: string | null;
};

export type DataRequest = {
    id: number;
    case_id: number;
    type: DataRequestType;
    type_label: string;
    agency: string;
    status: DataRequestStatus;
    status_label: string;
    subject_pinfl_masked: string | null;
    subject_name: string | null;
    params: Record<string, unknown> | null;
    purpose: string | null;
    request_text: string | null;
    cost: number;
    gov_request_id: string | null;
    rejection_reason: string | null;
    signed_at: string | null;
    submitted_at: string | null;
    completed_at: string | null;
    created_at: string;
};

/** Payload of the RequestStatusUpdated broadcast (channels: case.{id}, user.{id}). */
export type RequestStatusUpdatedEvent = {
    id: number;
    case_id: number;
    type: DataRequestType;
    type_label: string;
    status: DataRequestStatus;
    status_label: string;
    rejection_reason: string | null;
    updated_at: string;
};

export type Citation = { law: string; article: string | null; title: string | null; url: string | null };

export type AiAnalysis = {
    id: number;
    kind: 'request_text' | 'summary' | 'draft_claim';
    status: 'pending' | 'completed' | 'failed';
    output: string | null;
    structured: Record<string, unknown> | null;
    citations: Citation[] | null;
    model: string | null;
    created_at: string;
    // ai module additions (present when serialized via App\Services\Ai\AiAnalysisPresenter)
    prompt_version?: string | null;
    tokens?: number;
    cost?: number;
    disclaimer?: string;
    data_request_id?: number | null;
    case_id?: number | null;
    /** exact egasizlantirilgan text sent to the AI (null = AI not called) */
    sent_payload?: string | null;
    anonymization?: AnonymizationReport | null;
};

/** What was sent to the AI (App\Services\Ai\Anonymization\AnonymizationReport) — categories and counts only, never values. */
export type AnonymizationReport = {
    leak_check: 'passed' | 'blocked' | 'not_run';
    ai_called: boolean;
    legal_basis: string[];
    replaced?: { label: string; count: number }[];
    replaced_by_kind?: Record<string, number>;
    dropped?: string[];
    generalized?: string[];
    blocked?: { category: string; label: string; count: number }[];
    note?: string;
};

export type AuditEntry = {
    id: number;
    action: string;
    action_label: string;
    user: { id: number; name: string } | null;
    subject_type: string | null;
    subject_id: number | null;
    case_id: number | null;
    meta: Record<string, unknown> | null;
    ip: string | null;
    created_at: string;
};

export type Plan = {
    id: number;
    code: 'start' | 'advocate' | 'firm';
    name: string;
    audience: string;
    price: number;
    included_requests: number;
    extra_price: number | null;
    included_ai: number;
    features: string[] | null;
};

// ---- ai module ----

export type AlimonyOption = {
    children: number;
    label: string;
    share: string; // "1/4"
    share_value: number;
    by_share: number;
    amount: number; // max(by_share, 26.5% of min wage per child)
    floor_applied: boolean;
};

/** structured.alimony of a salary AiAnalysis — computed in PHP (App\Services\Ai\AlimonyCalculator). */
export type AlimonyCalculation = {
    period: { from: string | null; to: string | null };
    months_count: number;
    total_income: number;
    average_monthly: number;
    monthly: { period: string; amount: number }[];
    children: number | null;
    min_wage: number;
    min_per_child: number;
    options: AlimonyOption[];
    selected: AlimonyOption | null;
    legal_basis: string;
};

export type LawAnswer = {
    answer: string;
    citations: Citation[];
    model: string;
    tokens: number;
    prompt_version: string;
    disclaimer: string;
    /** personal data was found in the question and replaced before sending */
    pii_removed?: boolean;
    sent_payload?: string | null;
    anonymization?: AnonymizationReport | null;
};

// ---- Advocate order path + client portal (order-client) ----

/** Answer deadline fields on serialized data requests (Advokatura to'g'risidagi Qonun 7¹-modda). */
export type RequestDeadlineFields = {
    due_at: string | null;
    deadline_basis: 'default' | 'proceedings' | 'court_decision' | null;
    deadline_label: string | null;
    answered_on_time: boolean | null;
};

export type ClientCaseInfo = {
    id: number;
    title: string;
    court_case_number: string | null;
    category_label: string | null;
    status: 'active' | 'closed';
    order_number: string;
    advocate: { name: string | null; license_number: string | null; bureau_name: string | null };
};

export type ClientRequestStep = {
    label: string;
    state: 'done' | 'current' | 'upcoming' | 'success' | 'danger';
    at: string | null;
};

export type ClientRequestView = {
    id: number;
    type_label: string;
    agency: string;
    status: DataRequestStatus;
    status_label: string;
    submitted_at: string | null;
    completed_at: string | null;
    due_at: string | null;
    deadline_label: string | null;
    answered_on_time: boolean | null;
    rejection_reason: string | null;
    steps: ClientRequestStep[];
    summary_items: { label: string; value: string }[];
    ai_summary: string | null;
};

// ---- legal matrix (App\Support\LegalMatrix::toArray()) ----

export type LegalMatrixAccess = 'order' | 'court';

export type LegalMatrixRef = {
    law: string;
    article: string | null;
    note: string;
    url: string | null;
};

export type LegalMatrixType = {
    value: DataRequestType;
    label: string;
    agency: string;
    access: LegalMatrixAccess;
    items: string[];
    why: string;
    refs: LegalMatrixRef[];
    verify?: boolean;
    accepted_bases: { value: LegalBasisType; label: string }[];
};

export type LegalMatrixDomain = {
    key: string;
    title: string;
    summary: string;
    types: LegalMatrixType[];
};

export type LegalMatrix = {
    domains: LegalMatrixDomain[];
    out_of_scope: { title: string; why: string }[];
    deadlines: { default_days: number; no_info_notice_days: number; proceedings_work_days: number };
    disclaimer: string;
};

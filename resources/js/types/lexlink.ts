// Shared LexLink domain types. Extend (don't rename) fields; backend serializes snake_case.

export type UserRole = 'advocate' | 'lawyer' | 'admin';

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

export type DataRequestType = 'court_case' | 'salary' | 'property' | 'vehicle';

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

import { RegistryChip } from "@/components/brand/registry-chip";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import type { BasisCheck } from "./types";
import { formatDate } from "./types";

export type SealState = "verified" | "blocked" | "pending" | "idle";

/**
 * Seal-like mark for a legal basis: an official double ring (same drawing as
 * the logo and the RegistryChip glyph) holding a check, a cross or a clock.
 * <BasisSeal state="verified" className="size-12" />
 * Colour comes from the state; size from className (default 48px).
 */
export function BasisSeal({
    state,
    className,
}: {
    state: SealState;
    className?: string;
}) {
    const tone = {
        verified: "text-success",
        blocked: "text-danger",
        pending: "text-warning",
        idle: "text-ink-muted",
    }[state];

    return (
        <svg
            viewBox="0 0 48 48"
            aria-hidden="true"
            className={cn("size-12 shrink-0", tone, className)}
        >
            <circle
                cx="24"
                cy="24"
                r="22"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.6"
            />
            <circle
                cx="24"
                cy="24"
                r="18"
                fill="none"
                stroke="currentColor"
                strokeWidth="1"
                strokeDasharray="1.4 2.2"
                opacity={state === "idle" ? 0.6 : 0.9}
            />
            <circle
                cx="24"
                cy="24"
                r="13"
                fill="currentColor"
                opacity={state === "idle" ? 0.08 : 0.12}
            />
            {state === "verified" && (
                <path
                    d="M18 24.5l4.2 4.2L30.5 20"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            )}
            {state === "blocked" && (
                <path
                    d="M19.5 19.5l9 9M28.5 19.5l-9 9"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                />
            )}
            {state === "pending" && (
                <path
                    d="M24 18.5V24l3.5 2.5"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            )}
            {state === "idle" && (
                <path
                    d="M19 24h10"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                />
            )}
        </svg>
    );
}

/**
 * Rule #1 "Asos bo'lmasa — so'rov yo'q": live result of DataRequestService::checkBasis.
 * The first moment of the product (docs/DESIGN.md): a calm, prominent verdict
 * with a seal, the covering basis as a RegistryChip and its validity, or the
 * exact reason the request is blocked.
 * Props: check (null = not checked yet), loading?, className?
 */
export function BasisCheckPanel({
    check,
    loading = false,
    className,
}: {
    check: BasisCheck | null;
    loading?: boolean;
    className?: string;
}) {
    const heading = (
        <div className="flex items-center justify-between gap-2">
            <span className="text-xs text-ink-secondary">
                Huquqiy asos tekshiruvi
            </span>
            {loading && (
                <span className="inline-flex items-center gap-1.5 text-xs text-ink-muted">
                    <Spinner className="size-3.5" />
                    Tekshirilmoqda
                </span>
            )}
        </div>
    );

    if (!check) {
        return (
            <section
                aria-label="Huquqiy asos tekshiruvi"
                className={cn(
                    "flex flex-col gap-4 rounded-xl border bg-card p-5",
                    className,
                )}
            >
                {heading}
                <div className="flex items-start gap-4">
                    <BasisSeal state="idle" />
                    <div className="flex flex-col gap-1">
                        <p className="text-base font-medium text-ink">
                            Asos hali tekshirilmagan
                        </p>
                        <p className="text-sm text-ink-secondary">
                            Shaxs va so'rov turini tanlang, tizim asos
                            qamrovini tekshiradi
                        </p>
                    </div>
                </div>
            </section>
        );
    }

    if (check.ok && check.basis) {
        const b = check.basis;
        const isCourt = b.type === "court_decision";

        return (
            <section
                role="status"
                aria-label="Huquqiy asos tekshiruvi"
                data-basis="verified"
                className={cn(
                    "flex flex-col gap-4 rounded-xl border border-success-border bg-success-soft p-5 transition-opacity",
                    loading && "opacity-70",
                    className,
                )}
            >
                {heading}
                <div className="flex items-start gap-4">
                    <BasisSeal state="verified" />
                    <div className="flex min-w-0 flex-col gap-2">
                        <p className="text-xl leading-7 font-semibold text-success">
                            Asos qamraydi
                        </p>
                        <p className="text-sm text-ink-secondary">
                            {b.type_label}
                        </p>
                        <RegistryChip
                            value={b.number}
                            kind={isCourt ? "decision" : "order"}
                        />
                    </div>
                </div>
                <dl className="grid gap-2 border-t border-success-border pt-4 text-sm">
                    {b.expires_at && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-ink-secondary">Amal qiladi</dt>
                            <dd className="text-ink tabular-nums">
                                {formatDate(b.expires_at)} gacha
                            </dd>
                        </div>
                    )}
                    {(b.subject_name || b.subject_pinfl_masked) && (
                        <div className="flex justify-between gap-4">
                            <dt className="shrink-0 text-ink-secondary">
                                Subyekt
                            </dt>
                            <dd className="flex min-w-0 flex-col items-end text-right text-ink">
                                {b.subject_name && (
                                    <span>{b.subject_name}</span>
                                )}
                                {b.subject_pinfl_masked && (
                                    <MaskedPinfl
                                        value={b.subject_pinfl_masked}
                                        className="text-ink-secondary"
                                    />
                                )}
                            </dd>
                        </div>
                    )}
                    {b.court_name && (
                        <div className="flex justify-between gap-4">
                            <dt className="shrink-0 text-ink-secondary">Sud</dt>
                            <dd className="text-right text-ink">
                                {b.court_name}
                            </dd>
                        </div>
                    )}
                </dl>
            </section>
        );
    }

    return (
        <section
            role="alert"
            aria-label="Huquqiy asos tekshiruvi"
            data-basis="blocked"
            className={cn(
                "flex flex-col gap-4 rounded-xl border border-danger-border bg-danger-soft p-5 transition-opacity",
                loading && "opacity-70",
                className,
            )}
        >
            {heading}
            <div className="flex items-start gap-4">
                <BasisSeal state="blocked" />
                <div className="flex min-w-0 flex-col gap-2">
                    <p className="text-xl leading-7 font-semibold text-danger">
                        So'rov yuborilmaydi
                    </p>
                    <p className="text-base text-ink">{check.reason}</p>
                </div>
            </div>
        </section>
    );
}

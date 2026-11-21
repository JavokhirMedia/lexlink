import { Head, Link, useForm } from "@inertiajs/react";
import {
    Check,
    ExternalLink,
    FileText,
    History,
    PenLine,
    Save,
    Send,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import type { FormEvent } from "react";
import { MaskedPinfl } from "@/components/brand/masked-pinfl";
import { formatMoney } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import InputError from "@/components/input-error";
import { ACCESS_LABELS } from "@/components/legal/access-badge";
import { BasisCheckPanel } from "@/components/requests/basis-check-panel";
import { jsonRequest } from "@/components/requests/http";
import { REQUEST_TYPE_ICONS } from "@/components/requests/request-type-icon";
import type {
    BasisCheck,
    Quote,
    RequestTypeOption,
} from "@/components/requests/types";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { index as casesIndex, show as caseShow } from "@/routes/cases";
import {
    checkBasis as checkBasisRoute,
    draftText as draftTextRoute,
    store,
} from "@/routes/requests";
import type {
    DataRequestType,
    LegalBasis,
    LegalCase,
    LegalMatrix,
    LegalMatrixType,
} from "@/types/lexlink";

type Subject = {
    basis_id: number;
    name: string | null;
    pinfl_masked: string | null;
    basis_number: string;
    basis_type_label: string;
};

type Props = {
    case: LegalCase;
    bases: LegalBasis[];
    subjects: Subject[];
    types: RequestTypeOption[];
    matrix: LegalMatrix;
    defaults: { type: DataRequestType; period_from: string; period_to: string };
    quote: Quote;
    can_sign: boolean;
    ai_available: boolean;
};

type FormData = {
    case_id: number;
    type: DataRequestType;
    subject_basis_id: number | null;
    subject_pinfl: string;
    subject_name: string;
    params: {
        period_from: string;
        period_to: string;
        cadastral_number: string;
        court_case_number: string;
    };
    purpose: string;
    request_text: string;
    intent: "draft" | "signature" | "submit";
};

const PURPOSE_HINTS: Record<DataRequestType, string> = {
    salary: "Aliment miqdorini aniqlash uchun javobgarning so'nggi oylardagi daromadini tasdiqlash",
    property: "Da'vo ta'minoti sifatida javobgar mulkini aniqlash",
    vehicle: "Nikohda orttirilgan mol-mulkni (avtotransport) aniqlash",
    court_case: "Sud ishining joriy holati va majlis sanalarini aniqlash",
    tax_info:
        "Javobgarning soliq qarzi, YaTT faoliyati va yuridik shaxslardagi ishtirokini aniqlash",
    business_shares:
        "Undiruv qaratish mumkin bo'lgan mol-mulk sifatida yuridik shaxslardagi ulushlarni aniqlash",
    bank_accounts:
        "Aliment qarzini undirish uchun javobgarning bank hisobvaraqlaridagi mablag'larni aniqlash",
    border_crossing:
        "Aliment qarzdori chet elda ekanini va qachondan beri ekanini aniqlash",
};

/** Types with a period: salary requires it; for the others it is optional (months). */
const PERIOD_TYPES: DataRequestType[] = [
    "salary",
    "bank_accounts",
    "border_crossing",
    "tax_info",
];

export default function RequestCreate({
    case: legalCase,
    subjects,
    types,
    matrix,
    defaults,
    quote,
    can_sign,
    ai_available,
}: Props) {
    const form = useForm<FormData>({
        case_id: legalCase.id,
        type: defaults.type,
        subject_basis_id: subjects[0]?.basis_id ?? null,
        subject_pinfl: "",
        subject_name: "",
        params: {
            period_from: defaults.period_from,
            period_to: defaults.period_to,
            cadastral_number: "",
            court_case_number: legalCase.court_case_number ?? "",
        },
        purpose: PURPOSE_HINTS[defaults.type],
        request_text: "",
        intent: "draft",
    });
    const { data, setData, errors, processing } = form;
    const errs = errors as Record<string, string | undefined>;
    const manual = data.subject_basis_id === null;

    const [check, setCheck] = useState<BasisCheck | null>(null);
    const [checking, setChecking] = useState(false);
    const [drafting, setDrafting] = useState(false);
    const [draftSource, setDraftSource] = useState<"ai" | "template" | null>(
        null,
    );
    const purposeTouched = useRef(false);
    const [periodOn, setPeriodOn] = useState(defaults.type === "salary");
    const usesPeriod =
        data.type === "salary" ||
        (PERIOD_TYPES.includes(data.type) && periodOn);
    const params = useMemo(
        () =>
            usesPeriod
                ? data.params
                : { ...data.params, period_from: "", period_to: "" },
        [usesPeriod, data.params],
    );

    const payload = useMemo(
        () => ({
            case_id: data.case_id,
            type: data.type,
            subject_basis_id: data.subject_basis_id,
            subject_pinfl: manual ? data.subject_pinfl : null,
            subject_name: data.subject_name || null,
            params,
            purpose: data.purpose,
        }),
        [
            data.case_id,
            data.type,
            data.subject_basis_id,
            data.subject_pinfl,
            data.subject_name,
            params,
            data.purpose,
            manual,
        ],
    );

    // Live basis check (debounced). PINFL travels in a POST body, never in the URL.
    useEffect(() => {
        if (manual && !/^\d{14}$/.test(data.subject_pinfl)) {
            setCheck(
                data.subject_pinfl
                    ? {
                          ok: false,
                          reason: "JShShIR 14 ta raqamdan iborat bo'lishi kerak",
                          basis: null,
                      }
                    : null,
            );

            return;
        }
        const ctrl = new AbortController();
        const t = window.setTimeout(() => {
            setChecking(true);
            jsonRequest<BasisCheck>(
                "post",
                checkBasisRoute.url(),
                payload,
                ctrl.signal,
            )
                .then(setCheck)
                .catch(() => undefined)
                .finally(() => setChecking(false));
        }, 300);

        return () => {
            ctrl.abort();
            window.clearTimeout(t);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        data.type,
        data.subject_basis_id,
        data.subject_pinfl,
        data.params.period_from,
        data.params.period_to,
        usesPeriod,
    ]);

    const selectType = (t: DataRequestType) => {
        setData((d) => ({
            ...d,
            type: t,
            purpose: purposeTouched.current ? d.purpose : PURPOSE_HINTS[t],
        }));
    };

    const draftWithAi = () => {
        setDrafting(true);
        jsonRequest<{ text: string; source: "ai" | "template" }>(
            "post",
            draftTextRoute.url(),
            payload,
        )
            .then((res) => {
                setData("request_text", res.text);
                setDraftSource(res.source);
            })
            .catch(() => setDraftSource(null))
            .finally(() => setDrafting(false));
    };

    const submit = (intent: FormData["intent"]) => (e?: FormEvent) => {
        e?.preventDefault();
        form.transform((d) => ({
            ...d,
            intent,
            params,
            subject_pinfl: manual ? d.subject_pinfl : null,
        }));
        form.submit(store());
    };

    const selectedType = types.find((t) => t.value === data.type);
    const matrixType = matrix.domains
        .flatMap((d) => d.types)
        .find((t) => t.value === data.type);
    const blockedReason =
        check && !check.ok
            ? check.reason
            : !check
              ? "Huquqiy asos tekshirilmagan"
              : null;
    const textMissing = !data.request_text.trim() || !data.purpose.trim();
    const sendHint = can_sign
        ? (blockedReason ??
          (textMissing ? "So'rov maqsadi va matnini kiriting" : null))
        : textMissing
          ? "So'rov maqsadi va matnini kiriting"
          : null;

    return (
        <>
            <Head title="Yangi so'rov" />
            <form onSubmit={submit("draft")} className="flex flex-col gap-8">
                <PageHeader
                    title="Yangi so'rov"
                    description={
                        <>
                            Ish:{" "}
                            <Link
                                href={caseShow.url(legalCase.id)}
                                className="focus-ring rounded-xs text-ink underline-offset-4 hover:underline"
                            >
                                {legalCase.title}
                            </Link>
                        </>
                    }
                    meta={
                        legalCase.court_case_number ? (
                            <RegistryChip
                                value={legalCase.court_case_number}
                                kind="case"
                            />
                        ) : undefined
                    }
                />

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="flex min-w-0 flex-col gap-10">
                        {/* 1. Type */}
                        <Section
                            title="Ma'lumot turi"
                            description="Har bir tur uchun maxfiylik rejimi va qabul qilinadigan asos turlicha"
                            card
                            contentClassName="flex flex-col gap-8"
                        >
                            <div
                                className="flex flex-col gap-8"
                                role="radiogroup"
                                aria-label="So'rov turi"
                            >
                                {matrix.domains.map((domain) => {
                                    const holdsSelected = domain.types.some(
                                        (t) => t.value === data.type,
                                    );

                                    return (
                                        <section
                                            key={domain.key}
                                            className="flex flex-col gap-3"
                                            aria-labelledby={`domain-${domain.key}`}
                                        >
                                            <div className="flex flex-col gap-0.5">
                                                <h3
                                                    id={`domain-${domain.key}`}
                                                    className="text-base font-medium text-ink"
                                                >
                                                    {domain.title}
                                                </h3>
                                                <p className="text-xs text-ink-secondary">
                                                    {domain.summary}
                                                </p>
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                {domain.types.map((t) => (
                                                    <TypeCard
                                                        key={t.value}
                                                        type={t}
                                                        active={
                                                            data.type ===
                                                            t.value
                                                        }
                                                        onSelect={() =>
                                                            selectType(t.value)
                                                        }
                                                    />
                                                ))}
                                            </div>
                                            {matrixType && holdsSelected && (
                                                <WhatYouGet
                                                    type={matrixType}
                                                    regime={
                                                        selectedType?.secrecy_regime
                                                    }
                                                />
                                            )}
                                        </section>
                                    );
                                })}
                            </div>
                            <InputError message={errs.type} />
                        </Section>

                        {/* 2. Subject + params */}
                        <Section
                            title="Shaxs va davr"
                            description="Shaxs huquqiy asosdagi shaxs bilan bir xil bo'lishi shart"
                            card
                            contentClassName="flex flex-col gap-6"
                        >
                            <div
                                className="flex flex-col gap-2"
                                role="radiogroup"
                                aria-label="So'raladigan shaxs"
                            >
                                {subjects.map((s) => {
                                    const active =
                                        data.subject_basis_id === s.basis_id;

                                    return (
                                        <label
                                            key={s.basis_id}
                                            className={cn(
                                                "flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-surface px-4 py-3 transition-colors hover:border-ink-muted/50 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand",
                                                active &&
                                                    "border-brand bg-brand-soft hover:border-brand",
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="subject"
                                                className="size-4 shrink-0 accent-brand"
                                                checked={active}
                                                onChange={() =>
                                                    setData(
                                                        "subject_basis_id",
                                                        s.basis_id,
                                                    )
                                                }
                                            />
                                            <span className="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                                <span className="flex min-w-0 flex-col">
                                                    <span className="font-medium text-ink">
                                                        {s.name ??
                                                            "Nomaʼlum shaxs"}
                                                    </span>
                                                    <MaskedPinfl
                                                        value={s.pinfl_masked}
                                                        label
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span className="flex flex-wrap items-center gap-2 text-xs text-ink-secondary">
                                                    {s.basis_type_label}
                                                    <RegistryChip
                                                        value={s.basis_number}
                                                        size="sm"
                                                    />
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                                <label
                                    className={cn(
                                        "flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-surface px-4 py-3 transition-colors hover:border-ink-muted/50 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand",
                                        manual &&
                                            "border-brand bg-brand-soft hover:border-brand",
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="subject"
                                        className="size-4 shrink-0 accent-brand"
                                        checked={manual}
                                        onChange={() =>
                                            setData("subject_basis_id", null)
                                        }
                                    />
                                    <span className="text-ink">
                                        JShShIR ni qo'lda kiritish
                                    </span>
                                </label>
                            </div>

                            {manual && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="subject_pinfl">
                                            JShShIR
                                            <span className="ml-1 font-normal text-ink-muted">
                                                14 raqam
                                            </span>
                                        </Label>
                                        <Input
                                            id="subject_pinfl"
                                            inputMode="numeric"
                                            autoComplete="off"
                                            maxLength={14}
                                            className="font-mono tracking-wider"
                                            value={data.subject_pinfl}
                                            onChange={(e) =>
                                                setData(
                                                    "subject_pinfl",
                                                    e.target.value.replace(
                                                        /\D/g,
                                                        "",
                                                    ),
                                                )
                                            }
                                            aria-invalid={!!errs.subject_pinfl}
                                        />
                                        <InputError
                                            message={errs.subject_pinfl}
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="subject_name">
                                            F.I.Sh.
                                            <span className="ml-1 font-normal text-ink-muted">
                                                ixtiyoriy
                                            </span>
                                        </Label>
                                        <Input
                                            id="subject_name"
                                            value={data.subject_name}
                                            onChange={(e) =>
                                                setData(
                                                    "subject_name",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            {PERIOD_TYPES.includes(data.type) &&
                                data.type !== "salary" && (
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="period_on"
                                            className="mt-0.5"
                                            checked={periodOn}
                                            onCheckedChange={(v) =>
                                                setPeriodOn(v === true)
                                            }
                                        />
                                        <Label
                                            htmlFor="period_on"
                                            className="flex flex-col gap-0.5 font-normal"
                                        >
                                            <span className="text-ink">
                                                Davrni oylar bo'yicha cheklash
                                            </span>
                                            {data.type === "bank_accounts" && (
                                                <span className="text-xs text-ink-secondary">
                                                    Aylanma so'nggi 6 oy uchun
                                                    beriladi
                                                </span>
                                            )}
                                        </Label>
                                    </div>
                                )}
                            {usesPeriod && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="period_from">
                                            Davr boshi
                                        </Label>
                                        <Input
                                            id="period_from"
                                            type="month"
                                            value={data.params.period_from}
                                            onChange={(e) =>
                                                setData("params", {
                                                    ...data.params,
                                                    period_from: e.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={errs["params.period_from"]}
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="period_to">
                                            Davr oxiri
                                        </Label>
                                        <Input
                                            id="period_to"
                                            type="month"
                                            value={data.params.period_to}
                                            onChange={(e) =>
                                                setData("params", {
                                                    ...data.params,
                                                    period_to: e.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={errs["params.period_to"]}
                                        />
                                    </div>
                                </div>
                            )}
                            {data.type === "property" && (
                                <div className="flex flex-col gap-2 sm:max-w-sm">
                                    <Label htmlFor="cadastral_number">
                                        Kadastr raqami
                                        <span className="ml-1 font-normal text-ink-muted">
                                            ixtiyoriy
                                        </span>
                                    </Label>
                                    <Input
                                        id="cadastral_number"
                                        className="font-mono"
                                        value={data.params.cadastral_number}
                                        onChange={(e) =>
                                            setData("params", {
                                                ...data.params,
                                                cadastral_number:
                                                    e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            )}
                            {data.type === "court_case" && (
                                <div className="flex flex-col gap-2 sm:max-w-sm">
                                    <Label htmlFor="court_case_number">
                                        Sud ishi raqami
                                    </Label>
                                    <Input
                                        id="court_case_number"
                                        className="font-mono"
                                        value={data.params.court_case_number}
                                        onChange={(e) =>
                                            setData("params", {
                                                ...data.params,
                                                court_case_number:
                                                    e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            )}
                        </Section>

                        {/* 3. Purpose + text */}
                        <Section
                            title="Maqsad va so'rov matni"
                            description="Rasmiy xat idora nomiga, huquqiy asosga havola bilan"
                            actions={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={draftWithAi}
                                    disabled={drafting}
                                >
                                    {drafting ? <Spinner /> : <FileText />}
                                    Matn loyihasi (AI)
                                </Button>
                            }
                            card
                            contentClassName="flex flex-col gap-6"
                        >
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="purpose">So'rov maqsadi</Label>
                                <Textarea
                                    id="purpose"
                                    rows={2}
                                    className="min-h-20"
                                    value={data.purpose}
                                    onChange={(e) => {
                                        purposeTouched.current = true;
                                        setData("purpose", e.target.value);
                                    }}
                                    aria-invalid={!!errs.purpose}
                                />
                                <InputError message={errs.purpose} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="request_text">
                                    So'rov matni
                                </Label>
                                <Textarea
                                    id="request_text"
                                    rows={14}
                                    value={data.request_text}
                                    onChange={(e) =>
                                        setData("request_text", e.target.value)
                                    }
                                    placeholder={`${selectedType?.agency ?? "Idora"} rahbariyatiga…`}
                                    aria-invalid={!!errs.request_text}
                                />
                                {draftSource && (
                                    <p className="text-xs text-ink-secondary">
                                        {draftSource === "ai"
                                            ? "AI loyihasi: shaxs ma'lumotlari maskalangan holda yuborildi. Bu yuridik maslahat emas, yordamchi tahlil. Yakuniy qaror advokat zimmasida"
                                            : ai_available
                                              ? "AI hozir mavjud emas, rasmiy shablon qo'yildi. Matnni tekshirib, tahrirlang"
                                              : "Rasmiy shablon qo'yildi. Matnni tekshirib, tahrirlang"}
                                    </p>
                                )}
                                <InputError message={errs.request_text} />
                            </div>
                        </Section>
                    </div>

                    {/* Side: basis check + actions (inline under the form on mobile) */}
                    <aside className="flex flex-col gap-4 lg:sticky lg:top-[calc(var(--spacing-header)+24px)] lg:self-start">
                        <BasisCheckPanel check={check} loading={checking} />

                        <div className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs">
                            <div className="flex items-baseline justify-between gap-4">
                                <span className="text-sm text-ink-secondary">
                                    Narxi
                                </span>
                                <span className="text-xl leading-7 font-semibold text-ink tabular-nums">
                                    {quote.from_subscription
                                        ? "Obuna doirasida"
                                        : formatMoney(quote.cost)}
                                </span>
                            </div>
                            <p className="-mt-2 text-xs text-ink-muted">
                                Yuborishda yechiladi, rad etilsa yoki texnik
                                xato bo'lsa qaytariladi. Javob uchun alohida
                                to'lov yo'q
                            </p>

                            <div className="flex flex-col gap-2 border-t pt-4">
                                {can_sign ? (
                                    <Button
                                        type="button"
                                        size="lg"
                                        className="w-full"
                                        disabled={
                                            !!blockedReason ||
                                            textMissing ||
                                            processing
                                        }
                                        aria-describedby={
                                            sendHint ? "send-hint" : undefined
                                        }
                                        onClick={() => submit("submit")()}
                                    >
                                        <PenLine />
                                        Imzolash va yuborish
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        size="lg"
                                        className="w-full"
                                        disabled={textMissing || processing}
                                        aria-describedby={
                                            sendHint ? "send-hint" : undefined
                                        }
                                        onClick={() => submit("signature")()}
                                    >
                                        <Send />
                                        Advokat imzosiga yuborish
                                    </Button>
                                )}
                                {sendHint && (
                                    <p
                                        id="send-hint"
                                        className={cn(
                                            "text-xs",
                                            can_sign && blockedReason
                                                ? "text-danger"
                                                : "text-ink-secondary",
                                        )}
                                    >
                                        {can_sign && blockedReason
                                            ? "Asos so'rovni qamramaguncha yuborish bloklangan"
                                            : sendHint}
                                    </p>
                                )}
                                {!can_sign && (
                                    <p className="text-xs text-ink-secondary">
                                        So'rovni faqat faol litsenziyali advokat
                                        ERI bilan imzolaydi va yuboradi
                                    </p>
                                )}

                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Save />
                                    Loyiha sifatida saqlash
                                </Button>
                                <InputError
                                    message={errs.case_id ?? errs.billing}
                                />
                            </div>
                        </div>

                        <p className="flex items-start gap-2 px-1 text-xs text-ink-muted">
                            <History
                                className="mt-0.5 size-4 shrink-0"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                            Imzolash, yuborish va javobni har bir ochish audit
                            jurnaliga yoziladi
                        </p>
                    </aside>
                </div>
            </form>
        </>
    );
}

/** One selectable data type: quiet by default, brand outline + soft fill when selected. */
function TypeCard({
    type,
    active,
    onSelect,
}: {
    type: LegalMatrixType;
    active: boolean;
    onSelect: () => void;
}) {
    const Icon = REQUEST_TYPE_ICONS[type.value];

    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            onClick={onSelect}
            className={cn(
                "focus-ring flex items-start gap-3 rounded-lg border bg-surface p-4 text-left transition-colors hover:border-ink-muted/50",
                active && "border-brand bg-brand-soft hover:border-brand",
            )}
        >
            <span
                className={cn(
                    "flex size-9 shrink-0 items-center justify-center rounded-md",
                    active
                        ? "bg-surface text-brand"
                        : "bg-raised text-ink-secondary",
                )}
            >
                <Icon className="size-[18px]" strokeWidth={1.5} aria-hidden />
            </span>
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="text-base font-medium text-ink">
                    {type.label}
                </span>
                <span className="text-sm text-ink-secondary">
                    {type.agency}
                </span>
                <span
                    data-access={type.access}
                    className="mt-1 text-xs text-ink-muted"
                >
                    {ACCESS_LABELS[type.access]}
                </span>
            </span>
        </button>
    );
}

/** "Nima olinadi" — items + law refs (lex.uz) for the selected type, from LegalMatrix. */
function WhatYouGet({
    type,
    regime,
}: {
    type: LegalMatrixType;
    regime?: string;
}) {
    return (
        <div className="flex flex-col gap-4 rounded-lg bg-raised p-5">
            <div className="flex flex-col gap-2">
                <p className="text-sm font-medium text-ink">
                    Nima olinadi: {type.label.toLowerCase()}
                </p>
                <ul className="grid gap-x-6 gap-y-1.5 text-sm text-ink sm:grid-cols-2">
                    {type.items.map((item) => (
                        <li key={item} className="flex gap-2">
                            <Check
                                className="mt-0.5 size-4 shrink-0 text-ink-muted"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                            {item}
                        </li>
                    ))}
                </ul>
            </div>
            <div className="flex flex-col gap-1 text-sm text-ink-secondary">
                <p>{type.why}</p>
                {regime && <p>{regime}</p>}
            </div>
            <ul className="flex flex-col gap-1.5 border-t border-hairline pt-3 text-xs">
                {type.refs.map((ref) => {
                    const title = `${ref.law}${ref.article ? `, ${ref.article}-modda` : ""}`;

                    return (
                        <li
                            key={`${ref.law}-${ref.article}`}
                            className="text-ink-secondary"
                        >
                            {ref.url ? (
                                <a
                                    href={ref.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="focus-ring inline-flex items-center gap-1 rounded-xs text-ink underline decoration-ink-muted/40 underline-offset-4 transition-colors hover:decoration-ink"
                                >
                                    {title}
                                    <ExternalLink
                                        className="size-3.5 text-ink-muted"
                                        strokeWidth={1.5}
                                        aria-hidden
                                    />
                                </a>
                            ) : (
                                <span className="text-ink">{title}</span>
                            )}{" "}
                            · {ref.note}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

RequestCreate.layout = (props: Props) => ({
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: props.case.title, href: caseShow(props.case.id) },
        { title: "Yangi so'rov", href: "#" },
    ],
});

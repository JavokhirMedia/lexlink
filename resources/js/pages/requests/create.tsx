import { Head, Link, useForm } from "@inertiajs/react";
import {
    Car,
    FileText,
    Gavel,
    Home,
    Landmark,
    PenLine,
    Save,
    Send,
    Sparkles,
    Wallet,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import type { FormEvent } from "react";
import { formatMoney } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import InputError from "@/components/input-error";
import { BasisCheckPanel } from "@/components/requests/basis-check-panel";
import { jsonRequest } from "@/components/requests/http";
import type {
    BasisCheck,
    Quote,
    RequestTypeOption,
} from "@/components/requests/types";
import { textareaClass } from "@/components/requests/types";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { index as casesIndex, show as caseShow } from "@/routes/cases";
import {
    checkBasis as checkBasisRoute,
    draftText as draftTextRoute,
    store,
} from "@/routes/requests";
import type { DataRequestType, LegalBasis, LegalCase } from "@/types/lexlink";

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

const TYPE_ICONS: Record<DataRequestType, LucideIcon> = {
    salary: Wallet,
    property: Home,
    vehicle: Car,
    court_case: Gavel,
};

const PURPOSE_HINTS: Record<DataRequestType, string> = {
    salary: "Aliment miqdorini aniqlash uchun javobgarning so'nggi oylardagi daromadini tasdiqlash",
    property: "Da'vo ta'minoti sifatida javobgar mulkini aniqlash",
    vehicle: "Nikohda orttirilgan mol-mulkni (avtotransport) aniqlash",
    court_case: "Sud ishining joriy holati va majlis sanalarini aniqlash",
};

export default function RequestCreate({
    case: legalCase,
    subjects,
    types,
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

    const payload = useMemo(
        () => ({
            case_id: data.case_id,
            type: data.type,
            subject_basis_id: data.subject_basis_id,
            subject_pinfl: manual ? data.subject_pinfl : null,
            subject_name: data.subject_name || null,
            params: data.params,
            purpose: data.purpose,
        }),
        [
            data.case_id,
            data.type,
            data.subject_basis_id,
            data.subject_pinfl,
            data.subject_name,
            data.params,
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
            subject_pinfl: manual ? d.subject_pinfl : null,
        }));
        form.submit(store());
    };

    const selectedType = types.find((t) => t.value === data.type);
    const blockedReason =
        check && !check.ok
            ? check.reason
            : !check
              ? "Huquqiy asos tekshirilmagan"
              : null;
    const textMissing = !data.request_text.trim() || !data.purpose.trim();

    return (
        <>
            <Head title="Yangi so'rov" />
            <form
                onSubmit={submit("draft")}
                className="flex flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title="Yangi so'rov"
                    description={
                        <>
                            Ish:{" "}
                            <Link
                                href={caseShow.url(legalCase.id)}
                                className="font-medium text-foreground hover:underline"
                            >
                                {legalCase.title}
                            </Link>
                            {legalCase.court_case_number
                                ? ` · ${legalCase.court_case_number}`
                                : ""}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="space-y-6">
                        {/* 1. Type */}
                        <Card>
                            <CardHeader>
                                <CardTitle>1. Ma'lumot turi</CardTitle>
                                <CardDescription>
                                    Har bir tur uchun maxfiylik rejimi va qabul
                                    qilinadigan asos turlicha.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div
                                    className="grid gap-3 sm:grid-cols-2"
                                    role="radiogroup"
                                    aria-label="So'rov turi"
                                >
                                    {types.map((t) => {
                                        const Icon = TYPE_ICONS[t.value];
                                        const active = data.type === t.value;

                                        return (
                                            <button
                                                key={t.value}
                                                type="button"
                                                role="radio"
                                                aria-checked={active}
                                                onClick={() =>
                                                    selectType(t.value)
                                                }
                                                className={cn(
                                                    "flex items-start gap-3 rounded-md border p-3 text-left transition-colors hover:bg-muted/50",
                                                    active &&
                                                        "border-primary bg-primary/5 ring-2 ring-primary/20",
                                                )}
                                            >
                                                <Icon
                                                    className={cn(
                                                        "mt-0.5 size-5 shrink-0",
                                                        active
                                                            ? "text-primary"
                                                            : "text-muted-foreground",
                                                    )}
                                                    aria-hidden
                                                />
                                                <span className="min-w-0 space-y-0.5">
                                                    <span className="block text-sm font-medium">
                                                        {t.label}
                                                    </span>
                                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                        <Landmark
                                                            className="size-3"
                                                            aria-hidden
                                                        />
                                                        {t.agency}
                                                    </span>
                                                    <span className="block text-xs text-foreground/75">
                                                        {t.secrecy_regime}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                <InputError
                                    message={errs.type}
                                    className="mt-2"
                                />
                            </CardContent>
                        </Card>

                        {/* 2. Subject + params */}
                        <Card>
                            <CardHeader>
                                <CardTitle>2. Shaxs va parametrlar</CardTitle>
                                <CardDescription>
                                    Shaxs huquqiy asosdagi shaxs bilan bir xil
                                    bo'lishi shart.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    {subjects.map((s) => (
                                        <label
                                            key={s.basis_id}
                                            className={cn(
                                                "flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm transition-colors hover:bg-muted/50",
                                                data.subject_basis_id ===
                                                    s.basis_id &&
                                                    "border-primary bg-primary/5",
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="subject"
                                                className="accent-primary"
                                                checked={
                                                    data.subject_basis_id ===
                                                    s.basis_id
                                                }
                                                onChange={() =>
                                                    setData(
                                                        "subject_basis_id",
                                                        s.basis_id,
                                                    )
                                                }
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-medium">
                                                    {s.name ?? "Nomaʼlum shaxs"}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    <span className="font-mono">
                                                        {s.pinfl_masked}
                                                    </span>{" "}
                                                    · {s.basis_type_label} №{" "}
                                                    {s.basis_number}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                    <label
                                        className={cn(
                                            "flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm transition-colors hover:bg-muted/50",
                                            manual &&
                                                "border-primary bg-primary/5",
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="subject"
                                            className="accent-primary"
                                            checked={manual}
                                            onChange={() =>
                                                setData(
                                                    "subject_basis_id",
                                                    null,
                                                )
                                            }
                                        />
                                        <span>JShShIR ni qo'lda kiritish</span>
                                    </label>
                                </div>

                                {manual && (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="subject_pinfl">
                                                JShShIR (14 raqam)
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
                                                aria-invalid={
                                                    !!errs.subject_pinfl
                                                }
                                            />
                                            <InputError
                                                message={errs.subject_pinfl}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="subject_name">
                                                F.I.Sh. (ixtiyoriy)
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

                                {data.type === "salary" && (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
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
                                                        period_from:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errs["params.period_from"]
                                                }
                                            />
                                        </div>
                                        <div className="space-y-1.5">
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
                                                        period_to:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errs["params.period_to"]
                                                }
                                            />
                                        </div>
                                    </div>
                                )}
                                {data.type === "property" && (
                                    <div className="space-y-1.5 sm:max-w-sm">
                                        <Label htmlFor="cadastral_number">
                                            Kadastr raqami (ixtiyoriy)
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
                                    <div className="space-y-1.5 sm:max-w-sm">
                                        <Label htmlFor="court_case_number">
                                            Sud ishi raqami
                                        </Label>
                                        <Input
                                            id="court_case_number"
                                            className="font-mono"
                                            value={
                                                data.params.court_case_number
                                            }
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
                            </CardContent>
                        </Card>

                        {/* 3. Purpose + text */}
                        <Card>
                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1.5">
                                    <CardTitle>
                                        3. Maqsad va so'rov matni
                                    </CardTitle>
                                    <CardDescription>
                                        Rasmiy xat idora nomiga, huquqiy asosga
                                        havola bilan.
                                    </CardDescription>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={draftWithAi}
                                    disabled={drafting}
                                >
                                    {drafting ? <Spinner /> : <Sparkles />}
                                    AI yordamida yozish
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="purpose">
                                        So'rov maqsadi
                                    </Label>
                                    <textarea
                                        id="purpose"
                                        rows={2}
                                        className={textareaClass}
                                        value={data.purpose}
                                        onChange={(e) => {
                                            purposeTouched.current = true;
                                            setData("purpose", e.target.value);
                                        }}
                                        aria-invalid={!!errs.purpose}
                                    />
                                    <InputError message={errs.purpose} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="request_text">
                                        So'rov matni
                                    </Label>
                                    <textarea
                                        id="request_text"
                                        rows={14}
                                        className={cn(
                                            textareaClass,
                                            "font-serif leading-relaxed",
                                        )}
                                        value={data.request_text}
                                        onChange={(e) =>
                                            setData(
                                                "request_text",
                                                e.target.value,
                                            )
                                        }
                                        placeholder={`${selectedType?.agency ?? "Idora"} rahbariyatiga…`}
                                        aria-invalid={!!errs.request_text}
                                    />
                                    {draftSource && (
                                        <p className="text-xs text-muted-foreground">
                                            {draftSource === "ai"
                                                ? "AI loyihasi — shaxs ma'lumotlari maskalangan holda yuborildi. Bu yuridik maslahat emas — yordamchi tahlil. Yakuniy qaror advokat zimmasida."
                                                : ai_available
                                                  ? "AI hozir mavjud emas — rasmiy shablon qo'yildi. Matnni tekshirib, tahrirlang."
                                                  : "Rasmiy shablon qo'yildi. Matnni tekshirib, tahrirlang."}
                                        </p>
                                    )}
                                    <InputError message={errs.request_text} />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Side: basis check + actions */}
                    <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                        <BasisCheckPanel check={check} loading={checking} />

                        <Card>
                            <CardContent className="space-y-3 pt-6">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Narxi
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {quote.from_subscription
                                            ? "Obuna doirasida"
                                            : formatMoney(quote.cost)}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    To'lov yuborishda yechiladi; rad etilsa yoki
                                    texnik xato bo'lsa qaytariladi. Javob uchun
                                    alohida to'lov yo'q.
                                </p>

                                {can_sign ? (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <span
                                                className="block"
                                                tabIndex={
                                                    blockedReason ? 0 : -1
                                                }
                                            >
                                                <Button
                                                    type="button"
                                                    className="w-full"
                                                    disabled={
                                                        !!blockedReason ||
                                                        textMissing ||
                                                        processing
                                                    }
                                                    onClick={() =>
                                                        submit("submit")()
                                                    }
                                                >
                                                    <Send />
                                                    Imzolash va yuborish
                                                </Button>
                                            </span>
                                        </TooltipTrigger>
                                        {(blockedReason || textMissing) && (
                                            <TooltipContent>
                                                {blockedReason ??
                                                    "So'rov maqsadi va matnini kiriting"}
                                            </TooltipContent>
                                        )}
                                    </Tooltip>
                                ) : (
                                    <Button
                                        type="button"
                                        className="w-full"
                                        disabled={textMissing || processing}
                                        onClick={() => submit("signature")()}
                                    >
                                        <PenLine />
                                        Advokat imzosiga yuborish
                                    </Button>
                                )}
                                {can_sign && blockedReason && (
                                    <p className="text-xs text-danger">
                                        Yuborish bloklangan: {blockedReason}
                                    </p>
                                )}
                                {!can_sign && (
                                    <p className="text-xs text-muted-foreground">
                                        So'rovni faqat faol litsenziyali advokat
                                        ERI bilan imzolaydi va yuboradi.
                                    </p>
                                )}

                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Save />
                                    Loyiha sifatida saqlash
                                </Button>
                                <InputError
                                    message={errs.case_id ?? errs.billing}
                                />
                            </CardContent>
                        </Card>

                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <FileText
                                className="mt-0.5 size-3.5 shrink-0"
                                aria-hidden
                            />
                            Imzolash, yuborish va javobni har bir ochish audit
                            jurnaliga yoziladi.
                        </p>
                    </aside>
                </div>
            </form>
        </>
    );
}

RequestCreate.layout = (props: Props) => ({
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: props.case.title, href: caseShow(props.case.id) },
        { title: "Yangi so'rov", href: "#" },
    ],
});

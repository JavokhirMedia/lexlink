import { Braces, Download, Eye, Table2 } from "lucide-react";
import { useState } from "react";
import type { ReactNode } from "react";
import { formatMoney } from "@/components/brand/money";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import {
    Table as UiTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { payload as payloadRoute, pdf as pdfRoute } from "@/routes/requests";
import type { DataRequestType } from "@/types/lexlink";
import { jsonRequest } from "./http";
import { formatCurrency, formatMonth } from "./types";

type Row = Record<string, unknown>;
type Payload = {
    subject?: { full_name?: string | null; pinfl?: string };
    data?: Record<string, unknown>;
} & Row;

function str(v: unknown): string {
    if (v === null || v === undefined || v === "") {
        return "—";
    }

    return typeof v === "object" ? JSON.stringify(v) : String(v);
}

const mono = (v: unknown) => (
    <span className="font-mono text-meta whitespace-nowrap">{str(v)}</span>
);

const money = (v: unknown) => formatMoney(Number(v ?? 0));

/** A header label ending in "!" marks a numeric column (right-aligned); the "!" is not shown. */
function Table({
    caption,
    head,
    rows,
}: {
    caption?: string;
    head: string[];
    rows: ReactNode[][];
}) {
    const cols = head.map((h) => ({
        label: h.replace(/!$/, ""),
        num: h.endsWith("!"),
    }));

    return (
        <div className="flex flex-col gap-2">
            {caption && (
                <h3 className="text-sm font-medium text-ink">{caption}</h3>
            )}
            <div className="overflow-hidden rounded-lg border bg-card">
                <UiTable className="min-w-[600px]">
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            {cols.map((c) => (
                                <TableHead
                                    key={c.label}
                                    className={cn(c.num && "text-right")}
                                >
                                    {c.label}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length === 0 ? (
                            <TableRow className="hover:bg-transparent">
                                <TableCell
                                    colSpan={cols.length}
                                    className="text-ink-secondary"
                                >
                                    Ma'lumot topilmadi
                                </TableCell>
                            </TableRow>
                        ) : (
                            rows.map((r, i) => (
                                <TableRow key={i}>
                                    {r.map((c, j) => (
                                        <TableCell
                                            key={j}
                                            className={cn(
                                                j === 0 && "whitespace-nowrap",
                                                cols[j]?.num &&
                                                    "text-right whitespace-nowrap",
                                            )}
                                        >
                                            {c}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </UiTable>
            </div>
        </div>
    );
}

function TypedTable({
    type,
    data,
}: {
    type: DataRequestType;
    data: Record<string, unknown>;
}) {
    const list = (k: string) => (data[k] as Row[] | undefined) ?? [];

    if (type === "salary") {
        return (
            <Table
                head={["Davr", "Ish beruvchi", "Ish haqi!", "Pensiya badali!"]}
                rows={list("months").map((m) => [
                    formatMonth(m.period as string),
                    str(m.employer),
                    money(m.amount),
                    money(m.pension_contribution),
                ])}
            />
        );
    }

    if (type === "property") {
        return (
            <Table
                head={[
                    "Kadastr raqami",
                    "Turi",
                    "Manzil",
                    "Maydon!",
                    "Ulush",
                    "Ro'yxatga olingan",
                    "Cheklovlar",
                ]}
                rows={list("objects").map((o) => [
                    mono(o.cadastral_number),
                    str(o.type),
                    str(o.address),
                    `${str(o.area)} m²`,
                    str(o.share),
                    str(o.registered_at),
                    ((o.encumbrances as Row[] | undefined) ?? [])
                        .map((e) => `${str(e.type)} (${str(e.holder)})`)
                        .join("; ") || "Yo'q",
                ])}
            />
        );
    }

    if (type === "vehicle") {
        return (
            <Table
                head={[
                    "Rusumi",
                    "Yili!",
                    "Davlat raqami",
                    "Rangi",
                    "VIN",
                    "Garov",
                ]}
                rows={list("vehicles").map((v) => [
                    str(v.model),
                    str(v.year),
                    mono(v.plate),
                    str(v.color),
                    mono(v.vin),
                    v.pledge ? str((v.pledge as Row).holder) : "Yo'q",
                ])}
            />
        );
    }

    if (type === "tax_info") {
        const ie = (data.individual_entrepreneur as Row | undefined) ?? {};

        return (
            <div className="flex flex-col gap-6">
                <Table
                    caption="Soliq profili"
                    head={[
                        "STIR",
                        "Soliq rejimi",
                        "QQS",
                        "YaTT",
                        "Soliq qarzi!",
                        "O'tgan yili to'langan!",
                    ]}
                    rows={[
                        [
                            mono(data.tin),
                            str(data.tax_regime),
                            data.vat_payer ? "Ha" : "Yo'q",
                            `${str(ie.status)}${ie.activity ? ` — ${str(ie.activity)}` : ""}`,
                            money(data.tax_debt),
                            money(data.taxes_paid_last_year),
                        ],
                    ]}
                />
                <Table
                    caption="Yuridik shaxslarda ishtirok"
                    head={["Yuridik shaxs", "STIR", "Ulush!", "Holati"]}
                    rows={list("participations").map((p) => [
                        str(p.name),
                        mono(p.tin),
                        `${str(p.share_percent)}%`,
                        str(p.status),
                    ])}
                />
            </div>
        );
    }

    if (type === "business_shares") {
        return (
            <Table
                head={[
                    "Yuridik shaxs",
                    "STIR",
                    "Ulush!",
                    "Ustav fondi!",
                    "Ulush qiymati!",
                    "Ta'sischi bo'lgan",
                    "Holati",
                ]}
                rows={list("companies").map((c) => [
                    str(c.name),
                    mono(c.tin),
                    `${str(c.share_percent)}%`,
                    money(c.charter_capital),
                    money(c.share_value),
                    str(c.since),
                    str(c.status),
                ])}
            />
        );
    }

    if (type === "bank_accounts") {
        return (
            <Table
                head={[
                    "Bank",
                    "Hisobvaraq",
                    "Valyuta",
                    "Qoldiq!",
                    "Kirim (6 oy)!",
                    "Chiqim (6 oy)!",
                    "Ochilgan",
                    "Cheklovlar",
                ]}
                rows={list("accounts").map((a) => {
                    const cur = str(a.currency);

                    return [
                        str(a.bank),
                        mono(a.account),
                        cur,
                        formatCurrency(Number(a.balance ?? 0), cur),
                        formatCurrency(Number(a.turnover_in ?? 0), cur),
                        formatCurrency(Number(a.turnover_out ?? 0), cur),
                        str(a.opened_at),
                        ((a.restrictions as Row[] | undefined) ?? [])
                            .map((x) => `${str(x.type)} (${str(x.by)})`)
                            .join("; ") || "Yo'q",
                    ];
                })}
            />
        );
    }

    if (type === "border_crossing") {
        return (
            <div className="flex flex-col gap-4">
                {data.currently_abroad === true && (
                    <p className="rounded-lg border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger">
                        Shaxs hozir chet elda: so'nggi chiqish{" "}
                        {str((data.last_exit as Row | undefined)?.date)} ·{" "}
                        {str((data.last_exit as Row | undefined)?.country)}
                    </p>
                )}
                <Table
                    head={[
                        "Yo'nalish",
                        "Sana va vaqt",
                        "Davlat",
                        "O'tish punkti",
                    ]}
                    rows={list("crossings").map((c) => [
                        c.direction === "exit" ? "Chiqish" : "Kirish",
                        <span className="whitespace-nowrap">{str(c.date)}</span>,
                        str(c.country),
                        str(c.checkpoint),
                    ])}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <Table
                caption="Sud ishi"
                head={["Ish raqami", "Sud", "Sudya", "Toifa", "Holati"]}
                rows={
                    data.case_number
                        ? [
                              [
                                  <RegistryChip
                                      value={str(data.case_number)}
                                      kind="case"
                                      size="sm"
                                  />,
                                  str(data.court),
                                  str(data.judge),
                                  str(data.category),
                                  str(data.status),
                              ],
                          ]
                        : []
                }
            />
            <Table
                caption="Taraflar"
                head={["Taraf", "F.I.Sh.", "JShShIR"]}
                rows={list("parties").map((p) => [
                    str(p.role),
                    str(p.name),
                    mono(p.pinfl),
                ])}
            />
            <Table
                caption="Sud majlislari"
                head={["Sana", "Vaqt", "Xona", "Izoh"]}
                rows={list("hearings").map((h) => [
                    str(h.date),
                    str(h.time),
                    str(h.room),
                    str(h.note),
                ])}
            />
            <Table
                caption="Qarorlar"
                head={["Qaror", "Sana", "Turi", "Mazmuni"]}
                rows={list("decisions").map((d) => [
                    mono(d.number),
                    str(d.date),
                    str(d.type),
                    str(d.summary),
                ])}
            />
        </div>
    );
}

/**
 * Full payload behind LegalCasePolicy::viewResponses. Loads on demand (audited ViewPayload),
 * PDF download audited DownloadPdf. Props: requestId, type
 */
export function ResponseFullView({
    requestId,
    type,
}: {
    requestId: number;
    type: DataRequestType;
}) {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<Payload | null>(null);
    const [raw, setRaw] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const open = () => {
        setError(null);
        setLoading(true);
        jsonRequest<{ type: DataRequestType; payload: Payload }>(
            "get",
            payloadRoute.url(requestId),
        )
            .then((res) => setData(res.payload))
            .catch(() =>
                setError(
                    "Javobni ochib bo'lmadi — ruxsat yo'q yoki saqlash muddati tugagan",
                ),
            )
            .finally(() => setLoading(false));
    };

    return (
        <Section
            title="To'liq javob"
            description="Har bir ochish va yuklab olish audit jurnaliga yoziladi"
            actions={
                <>
                    {data && (
                        <div
                            role="group"
                            aria-label="Ko'rinish"
                            className="inline-flex h-8 rounded-md border border-input bg-surface p-0.5"
                        >
                            {(
                                [
                                    [false, "Jadval", Table2],
                                    [true, "JSON", Braces],
                                ] as const
                            ).map(([value, label, Icon]) => (
                                <button
                                    key={label}
                                    type="button"
                                    aria-pressed={raw === value}
                                    onClick={() => setRaw(value)}
                                    className={cn(
                                        "focus-ring inline-flex items-center gap-1.5 rounded-sm px-2.5 text-sm transition-colors",
                                        raw === value
                                            ? "bg-raised font-medium text-ink"
                                            : "text-ink-secondary hover:text-ink",
                                    )}
                                >
                                    <Icon
                                        className="size-4"
                                        strokeWidth={1.5}
                                        aria-hidden
                                    />
                                    {label}
                                </button>
                            ))}
                        </div>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <a href={pdfRoute.url(requestId)}>
                            <Download />
                            PDF (suv belgili)
                        </a>
                    </Button>
                </>
            }
        >
            {!data ? (
                <div className="flex flex-col gap-4 rounded-lg border bg-card p-6 shadow-xs sm:flex-row sm:items-center">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-raised text-ink-secondary">
                        <Eye
                            className="size-[18px]"
                            strokeWidth={1.5}
                            aria-hidden
                        />
                    </span>
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <p className="text-base font-medium text-ink">
                            Javob mazmuni yopiq
                        </p>
                        <p className="text-sm text-ink-secondary">
                            Ochilganda idora yuborgan barcha yozuvlar jadvalda
                            ko'rsatiladi
                        </p>
                        {error && (
                            <p className="text-sm text-danger" role="alert">
                                {error}
                            </p>
                        )}
                    </div>
                    <Button onClick={open} disabled={loading}>
                        {loading ? <Spinner /> : <Eye />}
                        To'liq javobni ochish
                    </Button>
                </div>
            ) : raw ? (
                <pre className="max-h-[480px] overflow-auto rounded-lg border bg-raised p-4 font-mono text-meta text-ink">
                    {JSON.stringify(data, null, 2)}
                </pre>
            ) : (
                <div className="flex flex-col gap-4">
                    {data.subject && (
                        <p className="flex flex-wrap items-center gap-2 text-sm text-ink-secondary">
                            Shaxs
                            <span className="font-medium text-ink">
                                {data.subject.full_name ?? "—"}
                            </span>
                            {data.subject.pinfl && (
                                <RegistryChip
                                    value={data.subject.pinfl}
                                    kind="pinfl"
                                    size="sm"
                                />
                            )}
                        </p>
                    )}
                    <TypedTable type={type} data={data.data ?? {}} />
                </div>
            )}
        </Section>
    );
}

import { Braces, Download, Eye, Table2 } from "lucide-react";
import { useState } from "react";
import { formatMoney } from "@/components/brand/money";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { payload as payloadRoute, pdf as pdfRoute } from "@/routes/requests";
import type { DataRequestType } from "@/types/lexlink";
import { jsonRequest } from "./http";
import { formatMonth } from "./types";

type Row = Record<string, unknown>;
type Payload = {
    subject?: { full_name?: string | null; pinfl?: string };
    data?: Record<string, unknown>;
} & Row;

const th =
    "border-b px-3 py-2 text-left text-xs font-medium text-muted-foreground";
const td = "border-b px-3 py-2 align-top";

function str(v: unknown): string {
    if (v === null || v === undefined || v === "") {
        return "—";
    }

    return typeof v === "object" ? JSON.stringify(v) : String(v);
}

function Table({ head, rows }: { head: string[]; rows: React.ReactNode[][] }) {
    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50">
                    <tr>
                        {head.map((h) => (
                            <th key={h} className={th}>
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td className={td} colSpan={head.length}>
                                Ma'lumot topilmadi
                            </td>
                        </tr>
                    ) : (
                        rows.map((r, i) => (
                            <tr key={i}>
                                {r.map((c, j) => (
                                    <td key={j} className={td}>
                                        {c}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
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
                head={["Davr", "Ish beruvchi", "Ish haqi", "Pensiya badali"]}
                rows={list("months").map((m) => [
                    formatMonth(m.period as string),
                    str(m.employer),
                    <span className="tabular-nums">
                        {formatMoney(Number(m.amount ?? 0))}
                    </span>,
                    <span className="tabular-nums">
                        {formatMoney(Number(m.pension_contribution ?? 0))}
                    </span>,
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
                    "Maydon",
                    "Ulush",
                    "Ro'yxatga olingan",
                    "Cheklovlar",
                ]}
                rows={list("objects").map((o) => [
                    <span className="font-mono text-xs">
                        {str(o.cadastral_number)}
                    </span>,
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
                    "Yili",
                    "Davlat raqami",
                    "Rangi",
                    "VIN",
                    "Garov",
                ]}
                rows={list("vehicles").map((v) => [
                    str(v.model),
                    str(v.year),
                    <span className="font-mono text-xs">{str(v.plate)}</span>,
                    str(v.color),
                    <span className="font-mono text-xs">{str(v.vin)}</span>,
                    v.pledge ? str((v.pledge as Row).holder) : "Yo'q",
                ])}
            />
        );
    }

    return (
        <div className="space-y-4">
            <Table
                head={["Ish raqami", "Sud", "Sudya", "Toifa", "Holati"]}
                rows={
                    data.case_number
                        ? [
                              [
                                  str(data.case_number),
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
                head={["Taraf", "F.I.Sh.", "JShShIR"]}
                rows={list("parties").map((p) => [
                    str(p.role),
                    str(p.name),
                    <span className="font-mono text-xs">{str(p.pinfl)}</span>,
                ])}
            />
            <Table
                head={["Sana", "Vaqt", "Xona", "Izoh"]}
                rows={list("hearings").map((h) => [
                    str(h.date),
                    str(h.time),
                    str(h.room),
                    str(h.note),
                ])}
            />
            <Table
                head={["Qaror", "Sana", "Turi", "Mazmuni"]}
                rows={list("decisions").map((d) => [
                    str(d.number),
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
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle>To'liq javob</CardTitle>
                    <CardDescription>
                        Har bir ochish va yuklab olish audit jurnaliga yoziladi.
                    </CardDescription>
                </div>
                <div className="flex flex-wrap gap-2">
                    {data && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setRaw((v) => !v)}
                        >
                            {raw ? <Table2 /> : <Braces />}
                            {raw ? "Jadval" : "JSON"}
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <a href={pdfRoute.url(requestId)}>
                            <Download />
                            PDF (suv belgili)
                        </a>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <div className="flex flex-col items-start gap-2">
                        <Button onClick={open} disabled={loading}>
                            {loading ? <Spinner /> : <Eye />}
                            To'liq javobni ochish
                        </Button>
                        {error && (
                            <p className="text-sm text-danger">{error}</p>
                        )}
                    </div>
                ) : raw ? (
                    <pre className="max-h-[480px] overflow-auto rounded-md border bg-muted/40 p-3 font-mono text-xs leading-relaxed">
                        {JSON.stringify(data, null, 2)}
                    </pre>
                ) : (
                    <div className="space-y-3">
                        {data.subject && (
                            <p className="text-sm">
                                <span className="text-muted-foreground">
                                    Shaxs:{" "}
                                </span>
                                {data.subject.full_name ?? "—"}{" "}
                                <span className="font-mono text-xs">
                                    ({data.subject.pinfl})
                                </span>
                            </p>
                        )}
                        <TypedTable type={type} data={data.data ?? {}} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

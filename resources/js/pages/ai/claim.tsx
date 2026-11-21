import { Head, Link, useForm } from "@inertiajs/react";
import { Check, Copy, Eye, FileText, Pencil, Printer } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import type { FormEvent, ReactNode } from "react";
import { AiDisclaimer } from "@/components/ai/ai-disclaimer";
import { AiSentPayload } from "@/components/ai/ai-sent-payload";
import { AlimonyTable } from "@/components/ai/alimony-table";
import { CitationChips } from "@/components/ai/citation-chips";
import { formatSum, modelLabel } from "@/components/ai/format";
import { AiMarkdown } from "@/components/ai/markdown";
import { formatDate, formatDateTime } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { useClipboard } from "@/hooks/use-clipboard";
import { store } from "@/routes/ai/claim";
import { index as casesIndex, show as casesShow } from "@/routes/cases";
import { show as requestShow } from "@/routes/requests";
import type {
    AiAnalysis,
    AlimonyCalculation,
    DataRequestType,
} from "@/types/lexlink";

type Props = {
    case: {
        id: number;
        title: string;
        court_case_number: string | null;
        client_name: string;
        category: string | null;
    };
    responses: {
        id: number;
        type: DataRequestType;
        type_label: string;
        agency: string;
        completed_at: string | null;
    }[];
    has_salary: boolean;
    children_detected: number | null;
    alimony_preview: AlimonyCalculation | null;
    draft: AiAnalysis | null;
    price: number;
};

function Panel({
    title,
    description,
    children,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-4 rounded-lg border bg-card p-6 shadow-xs">
            <div className="flex flex-col gap-1">
                <h2 className="text-base font-medium text-ink">{title}</h2>
                {description && (
                    <p className="text-sm text-ink-secondary">{description}</p>
                )}
            </div>
            {children}
        </section>
    );
}

export default function ClaimPage({
    case: legalCase,
    responses,
    has_salary,
    children_detected,
    alimony_preview,
    draft,
    price,
}: Props) {
    const form = useForm<{ children: string; court: string }>({
        children: children_detected ? String(children_detected) : "",
        court: "",
    });
    const [text, setText] = useState(draft?.output ?? "");
    const [editing, setEditing] = useState(false);
    const [copied, copy] = useClipboard();
    const printRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setText(draft?.output ?? "");
        setEditing(false);
    }, [draft?.id, draft?.output]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            children: d.children ? Number(d.children) : null,
            court: d.court || null,
        }));
        form.post(store.url(legalCase.id), { preserveScroll: true });
    };

    const print = () => {
        const html = editing
            ? `<pre style="white-space:pre-wrap;font:inherit">${text.replace(/[&<>]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" })[c] as string)}</pre>`
            : (printRef.current?.innerHTML ?? "");
        const w = window.open("", "_blank", "width=900,height=1000");
        if (!w) {
            return;
        }
        w.document.write(
            `<!doctype html><html lang="uz"><head><meta charset="utf-8"><title>Da'vo arizasi — ${legalCase.title}</title>` +
                '<style>body{font-family:"Times New Roman",serif;font-size:14px;line-height:1.55;max-width:720px;margin:40px auto;color:#000}' +
                "h2,h3,h4{text-align:center;margin:18px 0 8px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #999;padding:4px 8px}" +
                "a{color:inherit;text-decoration:none}@page{margin:20mm}</style></head><body>" +
                html +
                "</body></html>",
        );
        w.document.close();
        w.focus();
        w.print();
    };

    return (
        <>
            <Head title="Da'vo arizasi loyihasi" />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title="Da'vo arizasi loyihasi"
                    description={
                        <>
                            Aliment undirish to'g'risida · mijoz:{" "}
                            {legalCase.client_name}
                        </>
                    }
                    meta={
                        <>
                            <Link
                                href={casesShow.url(legalCase.id)}
                                className="focus-ring rounded-xs text-ink underline decoration-hairline underline-offset-4 transition-colors hover:decoration-ink"
                            >
                                Ish: {legalCase.title}
                            </Link>
                            {legalCase.court_case_number && (
                                <RegistryChip
                                    value={legalCase.court_case_number}
                                    kind="case"
                                />
                            )}
                        </>
                    }
                />

                <div className="grid gap-8 lg:grid-cols-[400px_minmax(0,1fr)] lg:items-start">
                    <div className="flex flex-col gap-6">
                        <Panel
                            title="Parametrlar"
                            description="AI'ga faqat egasizlantirilgan ma'lumot yuboriladi (ShMQ 16-modda), summalar PHP'da hisoblanadi"
                        >
                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-5"
                            >
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="children">
                                        Voyaga yetmagan bolalar soni
                                    </Label>
                                    <Input
                                        id="children"
                                        type="number"
                                        min={1}
                                        max={15}
                                        value={form.data.children}
                                        onChange={(e) =>
                                            form.setData(
                                                "children",
                                                e.target.value,
                                            )
                                        }
                                        placeholder="masalan, 2"
                                        aria-invalid={!!form.errors.children}
                                    />
                                    {children_detected && (
                                        <p className="text-meta text-ink-secondary">
                                            Ish tavsifidan aniqlandi:{" "}
                                            {children_detected}
                                        </p>
                                    )}
                                    {form.errors.children && (
                                        <p className="text-meta text-danger">
                                            {form.errors.children}
                                        </p>
                                    )}
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="court">Sud nomi</Label>
                                    <Input
                                        id="court"
                                        value={form.data.court}
                                        onChange={(e) =>
                                            form.setData(
                                                "court",
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Yunusobod tumanlararo fuqarolik ishlari bo'yicha sudi"
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : (
                                            <FileText aria-hidden />
                                        )}
                                        {draft
                                            ? "Qayta yaratish"
                                            : "Loyihani yaratish"}
                                    </Button>
                                    <p className="text-meta text-ink-secondary">
                                        Tarif doirasida bepul, limitdan keyin{" "}
                                        <span className="tabular-nums">
                                            {formatSum(price)}
                                        </span>
                                    </p>
                                </div>
                            </form>
                        </Panel>

                        {alimony_preview && (
                            <Panel title="Aliment hisob-kitobi">
                                <AlimonyTable calc={alimony_preview} />
                            </Panel>
                        )}

                        <Panel title="Ishlatiladigan javoblar">
                            {responses.length === 0 ? (
                                <p className="text-sm text-ink-secondary">
                                    Hali javob olingan so'rov yo'q. Loyiha bo'sh
                                    joylar bilan tuziladi.
                                </p>
                            ) : (
                                <ul className="-my-2 flex flex-col divide-y divide-hairline">
                                    {responses.map((r) => (
                                        <li key={r.id}>
                                            <Link
                                                href={requestShow.url(r.id)}
                                                className="focus-ring flex items-center justify-between gap-3 rounded-xs py-2.5"
                                            >
                                                <span className="flex min-w-0 flex-col">
                                                    <span className="truncate text-sm font-medium text-ink">
                                                        {r.type_label}
                                                    </span>
                                                    <span className="truncate text-meta text-ink-secondary">
                                                        {r.agency}
                                                        {r.completed_at &&
                                                            ` · ${formatDate(r.completed_at)}`}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 font-mono text-meta text-ink-muted tabular-nums">
                                                    #{r.id}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {!has_salary && responses.length > 0 && (
                                <p className="rounded-md bg-warning-soft px-3 py-2 text-meta text-warning">
                                    Ish haqi javobi yo'q, aliment summasi
                                    hisoblanmaydi
                                </p>
                            )}
                        </Panel>
                    </div>

                    <section
                        aria-labelledby="draft-title"
                        className="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-card shadow-xs"
                    >
                        <header className="flex flex-wrap items-center justify-between gap-3 border-b px-6 py-4">
                            <div className="flex min-w-0 flex-col gap-0.5">
                                <h2
                                    id="draft-title"
                                    className="text-base font-medium text-ink"
                                >
                                    Loyiha matni
                                </h2>
                                {draft && (
                                    <p className="text-meta text-ink-secondary tabular-nums">
                                        {formatDateTime(draft.created_at)}
                                        {draft.model &&
                                            ` · ${modelLabel(draft.model)}`}
                                    </p>
                                )}
                            </div>
                            {draft && (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        aria-pressed={editing}
                                        onClick={() => setEditing((v) => !v)}
                                    >
                                        {editing ? (
                                            <Eye aria-hidden />
                                        ) : (
                                            <Pencil aria-hidden />
                                        )}
                                        {editing ? "Ko'rinish" : "Tahrirlash"}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => void copy(text)}
                                    >
                                        {copied === text ? (
                                            <Check aria-hidden />
                                        ) : (
                                            <Copy aria-hidden />
                                        )}
                                        {copied === text
                                            ? "Nusxa olindi"
                                            : "Nusxa olish"}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={print}
                                    >
                                        <Printer aria-hidden />
                                        Chop etish / PDF
                                    </Button>
                                </div>
                            )}
                        </header>

                        <div className="bg-page p-4 sm:p-8">
                            {!draft && (
                                <div className="mx-auto flex max-w-[68ch] flex-col items-center gap-3 rounded-lg border border-dashed bg-card px-6 py-16 text-center">
                                    <span className="flex size-10 items-center justify-center rounded-md bg-raised text-ink-muted">
                                        <FileText
                                            className="size-[18px]"
                                            strokeWidth={1.5}
                                            aria-hidden
                                        />
                                    </span>
                                    <p className="text-base font-medium text-ink">
                                        Loyiha hali yaratilmagan
                                    </p>
                                    <p className="text-sm text-ink-secondary">
                                        Parametrlarni tekshiring va "Loyihani
                                        yaratish" tugmasini bosing
                                    </p>
                                </div>
                            )}
                            {draft && editing && (
                                <div className="mx-auto flex max-w-[76ch] flex-col gap-2">
                                    <Textarea
                                        value={text}
                                        onChange={(e) =>
                                            setText(e.target.value)
                                        }
                                        className="min-h-[640px] p-6 leading-[26px] sm:p-10"
                                        aria-label="Da'vo arizasi matni"
                                    />
                                    <p className="text-meta text-ink-secondary">
                                        Tahrirlar serverda saqlanmaydi — nusxa
                                        oling yoki chop eting
                                    </p>
                                </div>
                            )}
                            {draft && !editing && (
                                <div
                                    ref={printRef}
                                    className="mx-auto max-w-[76ch] rounded-md border bg-surface px-6 py-8 shadow-xs sm:px-12 sm:py-12"
                                >
                                    <AiMarkdown citations={draft.citations}>
                                        {text}
                                    </AiMarkdown>
                                </div>
                            )}
                        </div>

                        {draft && (
                            <footer className="flex flex-col gap-5 border-t px-6 py-5">
                                <CitationChips citations={draft.citations} />
                                <AiSentPayload
                                    report={draft.anonymization}
                                    sentPayload={draft.sent_payload}
                                />
                                <AiDisclaimer text={draft.disclaimer} />
                            </footer>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

ClaimPage.layout = (props: Props) => ({
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: props.case.title, href: casesShow(props.case.id) },
        { title: "Da'vo arizasi", href: "#" },
    ],
});

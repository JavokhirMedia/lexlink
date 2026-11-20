import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Copy,
    FileText,
    Pencil,
    Printer,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AiDisclaimer } from '@/components/ai/ai-disclaimer';
import { AlimonyTable } from '@/components/ai/alimony-table';
import { CitationChips } from '@/components/ai/citation-chips';
import { formatSum, modelLabel } from '@/components/ai/format';
import { AiMarkdown } from '@/components/ai/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { store } from '@/routes/ai/claim';
import { index as casesIndex, show as casesShow } from '@/routes/cases';
import type {
    AiAnalysis,
    AlimonyCalculation,
    DataRequestType,
} from '@/types/lexlink';

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
        children: children_detected ? String(children_detected) : '',
        court: '',
    });
    const [text, setText] = useState(draft?.output ?? '');
    const [editing, setEditing] = useState(false);
    const [copied, copy] = useClipboard();
    const printRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setText(draft?.output ?? '');
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
            ? `<pre style="white-space:pre-wrap;font:inherit">${text.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c] as string)}</pre>`
            : (printRef.current?.innerHTML ?? '');
        const w = window.open('', '_blank', 'width=900,height=1000');
        if (!w) {
            return;
        }
        w.document.write(
            `<!doctype html><html lang="uz"><head><meta charset="utf-8"><title>Da'vo arizasi — ${legalCase.title}</title>` +
                '<style>body{font-family:"Times New Roman",serif;font-size:14px;line-height:1.55;max-width:720px;margin:40px auto;color:#000}' +
                'h2,h3,h4{text-align:center;margin:18px 0 8px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #999;padding:4px 8px}' +
                'a{color:inherit;text-decoration:none}@page{margin:20mm}</style></head><body>' +
                html +
                '</body></html>',
        );
        w.document.close();
        w.focus();
        w.print();
    };

    return (
        <>
            <Head title="Da'vo arizasi loyihasi" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <Link
                            href={casesShow.url(legalCase.id)}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden />{' '}
                            {legalCase.title}
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Da'vo arizasi loyihasi
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Aliment undirish to'g'risida · mijoz:{' '}
                            {legalCase.client_name}
                            {legalCase.court_case_number &&
                                ` · ish № ${legalCase.court_case_number}`}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Parametrlar
                                </CardTitle>
                                <CardDescription>
                                    Shaxsiy ma'lumotlar AI ga maxfiylashtirilgan
                                    holda yuboriladi; summalar PHP da
                                    hisoblanadi.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit} className="space-y-4">
                                    <div className="space-y-1.5">
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
                                                    'children',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="masalan, 2"
                                        />
                                        {children_detected && (
                                            <p className="text-xs text-muted-foreground">
                                                Ish tavsifidan aniqlandi:{' '}
                                                {children_detected}
                                            </p>
                                        )}
                                        {form.errors.children && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.children}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="court">Sud nomi</Label>
                                        <Input
                                            id="court"
                                            value={form.data.court}
                                            onChange={(e) =>
                                                form.setData(
                                                    'court',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Yunusobod tumanlararo fuqarolik ishlari bo'yicha sudi"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={form.processing}
                                    >
                                        {form.processing ? (
                                            <RefreshCw
                                                className="size-4 animate-spin"
                                                aria-hidden
                                            />
                                        ) : (
                                            <FileText
                                                className="size-4"
                                                aria-hidden
                                            />
                                        )}
                                        {draft
                                            ? 'Qayta yaratish'
                                            : 'Loyihani yaratish'}
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        Tarif doirasida bepul, limitdan keyin{' '}
                                        {formatSum(price)}.
                                    </p>
                                </form>
                            </CardContent>
                        </Card>

                        <Card className="gap-3">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Ishlatiladigan javoblar
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {responses.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Hali javob olingan so'rov yo'q. Loyiha
                                        bo'sh joylar bilan tuziladi.
                                    </p>
                                )}
                                {responses.map((r) => (
                                    <div
                                        key={r.id}
                                        className="flex items-center justify-between gap-2 text-sm"
                                    >
                                        <span>
                                            {r.type_label}
                                            <span className="block text-xs text-muted-foreground">
                                                {r.agency}
                                            </span>
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="shrink-0"
                                        >
                                            № {r.id}
                                        </Badge>
                                    </div>
                                ))}
                                {!has_salary && responses.length > 0 && (
                                    <p className="text-xs text-amber-700 dark:text-amber-400">
                                        Ish haqi javobi yo'q — aliment summasi
                                        hisoblanmaydi.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        {alimony_preview && (
                            <Card className="gap-4">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Aliment hisob-kitobi
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <AlimonyTable calc={alimony_preview} />
                                </CardContent>
                            </Card>
                        )}

                        <Card className="gap-4">
                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        Loyiha matni
                                    </CardTitle>
                                    {draft && (
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                draft.created_at,
                                            ).toLocaleString('uz-UZ')}
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
                                            onClick={() =>
                                                setEditing((v) => !v)
                                            }
                                        >
                                            <Pencil
                                                className="size-4"
                                                aria-hidden
                                            />
                                            {editing
                                                ? "Ko'rinish"
                                                : 'Tahrirlash'}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => void copy(text)}
                                        >
                                            {copied === text ? (
                                                <Check
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            ) : (
                                                <Copy
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            )}
                                            Nusxa olish
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={print}
                                        >
                                            <Printer
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Chop etish / PDF
                                        </Button>
                                    </div>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!draft && (
                                    <p className="text-sm text-muted-foreground">
                                        Loyiha hali yaratilmagan. Parametrlarni
                                        tekshiring va "Loyihani yaratish"
                                        tugmasini bosing.
                                    </p>
                                )}
                                {draft && editing && (
                                    <textarea
                                        value={text}
                                        onChange={(e) =>
                                            setText(e.target.value)
                                        }
                                        className="min-h-[560px] w-full rounded-md border bg-background p-3 font-mono text-sm leading-relaxed shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        aria-label="Da'vo arizasi matni"
                                    />
                                )}
                                {draft && !editing && (
                                    <div
                                        ref={printRef}
                                        className="rounded-md border bg-background p-5 md:p-8"
                                    >
                                        <AiMarkdown citations={draft.citations}>
                                            {text}
                                        </AiMarkdown>
                                    </div>
                                )}
                                {draft && editing && (
                                    <p className="text-xs text-muted-foreground">
                                        Tahrirlar serverda saqlanmaydi — nusxa
                                        oling yoki chop eting.
                                    </p>
                                )}
                                {draft && (
                                    <CitationChips
                                        citations={draft.citations}
                                    />
                                )}
                                {draft && (
                                    <AiDisclaimer text={draft.disclaimer} />
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

ClaimPage.layout = {
    breadcrumbs: [
        { title: 'Ishlar', href: casesIndex() },
        { title: "Da'vo arizasi", href: '#' },
    ],
};

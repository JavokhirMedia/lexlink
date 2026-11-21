import { Head, useHttp } from "@inertiajs/react";
import { CornerDownLeft, ExternalLink, ShieldCheck } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import type { FormEvent, KeyboardEvent } from "react";
import { AiDisclaimer } from "@/components/ai/ai-disclaimer";
import { AiSentPayload } from "@/components/ai/ai-sent-payload";
import { CitationChips } from "@/components/ai/citation-chips";
import { modelLabel } from "@/components/ai/format";
import { AiMarkdown } from "@/components/ai/markdown";
import { formatNumber } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { assistant } from "@/routes/ai";
import { ask } from "@/routes/ai/assistant";
import type { LawAnswer } from "@/types/lexlink";

type Law = {
    slug: string;
    title: string;
    short_title: string;
    url: string | null;
    chunks: number;
};

type Props = { laws: Law[]; ai_enabled: boolean; examples: string[] };

type Message = {
    id: number;
    question: string;
    answer?: LawAnswer;
    error?: string;
};

export default function Assistant({ laws, ai_enabled, examples }: Props) {
    const [messages, setMessages] = useState<Message[]>([]);
    const http = useHttp<{ question: string }, LawAnswer>({ question: "" });
    const bottom = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (messages.length > 0) {
            bottom.current?.scrollIntoView({ behavior: "smooth", block: "end" });
        }
    }, [messages]);

    const send = (question: string) => {
        const q = question.trim();
        if (q.length < 3 || http.processing) {
            return;
        }
        const id = Date.now();
        setMessages((m) => [...m, { id, question: q }]);
        http.setData("question", q);
        http.transform(() => ({ question: q }));
        http.post(ask.url(), {
            onSuccess: (answer) => {
                setMessages((m) =>
                    m.map((x) => (x.id === id ? { ...x, answer } : x)),
                );
                http.setData("question", "");
            },
            onError: () =>
                setMessages((m) =>
                    m.map((x) =>
                        x.id === id
                            ? { ...x, error: "Javob olib bo'lmadi" }
                            : x,
                    ),
                ),
        }).catch(() => {
            setMessages((m) =>
                m.map((x) =>
                    x.id === id && !x.answer
                        ? { ...x, error: "Javob olib bo'lmadi" }
                        : x,
                ),
            );
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        send(http.data.question);
    };

    const onKey = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            send(http.data.question);
        }
    };

    return (
        <>
            <Head title="Huquqiy yordamchi" />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title="Huquqiy yordamchi"
                    description="Savollarga lex.uz dagi rasmiy qonun matnlari asosida javob, har bir javobda manba moddalar"
                    meta={
                        <span className="text-ink-secondary">
                            Rejim:{" "}
                            <span className="text-ink">
                                {ai_enabled
                                    ? "Gemini + lex.uz"
                                    : "Qidiruv rejimi"}
                            </span>
                        </span>
                    }
                />

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
                    <section
                        aria-label="Suhbat"
                        className="flex min-h-[60vh] min-w-0 flex-col rounded-xl border bg-card shadow-xs"
                    >
                        <div className="flex flex-1 flex-col gap-8 px-4 py-6 sm:px-6">
                            {messages.length === 0 && (
                                <div className="flex flex-col gap-3">
                                    <p className="text-sm text-ink-secondary">
                                        Namuna savollar
                                    </p>
                                    <ul className="flex flex-col gap-2">
                                        {examples.map((ex) => (
                                            <li key={ex}>
                                                <button
                                                    type="button"
                                                    onClick={() => send(ex)}
                                                    className="focus-ring w-full rounded-md border bg-surface px-4 py-2.5 text-left text-sm text-ink transition-colors hover:border-brand-border hover:bg-brand-soft"
                                                >
                                                    {ex}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {messages.map((m) => (
                                <div key={m.id} className="flex flex-col gap-4">
                                    <div className="flex flex-col items-end gap-1">
                                        <span className="text-meta text-ink-muted">
                                            Savol
                                        </span>
                                        <p className="w-fit max-w-[85%] rounded-lg rounded-tr-xs bg-brand-soft px-4 py-2.5 text-base whitespace-pre-wrap text-ink">
                                            {m.question}
                                        </p>
                                    </div>

                                    <div className="flex max-w-[72ch] flex-col gap-4">
                                        <span className="text-meta text-ink-muted">
                                            Javob
                                            {m.answer?.model &&
                                                ` · ${modelLabel(m.answer.model)}`}
                                        </span>
                                        {!m.answer && !m.error && (
                                            <p className="flex items-center gap-2 text-sm text-ink-secondary">
                                                <Spinner />
                                                Qonunlar qidirilmoqda…
                                            </p>
                                        )}
                                        {m.error && (
                                            <p
                                                className="text-sm text-danger"
                                                role="alert"
                                            >
                                                {m.error}
                                            </p>
                                        )}
                                        {m.answer && (
                                            <>
                                                {m.answer.pii_removed && (
                                                    <p className="flex items-start gap-2 rounded-md border border-success-border bg-success-soft px-3 py-2 text-meta text-success">
                                                        <ShieldCheck
                                                            className="mt-px size-4 shrink-0"
                                                            strokeWidth={1.5}
                                                            aria-hidden
                                                        />
                                                        Savoldagi shaxsiy
                                                        ma'lumotlar AI'ga
                                                        yuborishdan oldin olib
                                                        tashlandi
                                                    </p>
                                                )}
                                                <AiMarkdown
                                                    citations={
                                                        m.answer.citations
                                                    }
                                                >
                                                    {m.answer.answer}
                                                </AiMarkdown>
                                                <CitationChips
                                                    citations={
                                                        m.answer.citations
                                                    }
                                                />
                                                <AiSentPayload
                                                    report={
                                                        m.answer.anonymization
                                                    }
                                                    sentPayload={
                                                        m.answer.sent_payload
                                                    }
                                                />
                                                <AiDisclaimer
                                                    text={m.answer.disclaimer}
                                                />
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                            <div ref={bottom} />
                        </div>

                        <form
                            onSubmit={submit}
                            className="sticky bottom-0 flex flex-col gap-2 rounded-b-xl border-t bg-card p-4"
                        >
                            <div className="flex items-end gap-2 rounded-md border border-input bg-surface p-2 transition-[border-color,box-shadow] focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20">
                                <textarea
                                    value={http.data.question}
                                    onChange={(e) =>
                                        http.setData("question", e.target.value)
                                    }
                                    onKeyDown={onKey}
                                    rows={2}
                                    maxLength={2000}
                                    placeholder="Masalan: aliment qaysi daromadlardan ushlab qolinadi?"
                                    className="min-w-0 flex-1 resize-none bg-transparent px-2 py-1 text-base text-ink outline-none placeholder:text-ink-muted"
                                    aria-label="Savol"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={
                                        http.processing ||
                                        http.data.question.trim().length < 3
                                    }
                                >
                                    <CornerDownLeft aria-hidden />
                                    Yuborish
                                </Button>
                            </div>
                            {http.errors.question && (
                                <p className="text-meta text-danger">
                                    {http.errors.question}
                                </p>
                            )}
                            <p className="text-meta text-ink-muted">
                                Shaxsiy ma'lumotlar (F.I.Sh., JShShIR, telefon,
                                pasport, manzil) AI'ga yuborishdan oldin
                                egasizlantiriladi (ShMQ 16-modda)
                            </p>
                        </form>
                    </section>

                    <aside
                        aria-labelledby="kb-title"
                        className="flex flex-col gap-3 lg:sticky lg:top-[96px]"
                    >
                        <h2
                            id="kb-title"
                            className="text-base font-medium text-ink"
                        >
                            Bilimlar bazasi
                        </h2>
                        {laws.length === 0 ? (
                            <p className="text-sm text-ink-secondary">
                                Qonunlar import qilinmagan:{" "}
                                <code className="font-mono text-meta">
                                    php artisan lex:import --from-snapshot
                                </code>
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y divide-hairline rounded-lg border bg-card">
                                {laws.map((l) => (
                                    <li key={l.slug}>
                                        <a
                                            href={l.url ?? "https://lex.uz"}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="focus-ring group flex items-start justify-between gap-3 px-4 py-3 transition-colors first:rounded-t-lg last:rounded-b-lg hover:bg-page"
                                        >
                                            <span className="flex min-w-0 flex-col gap-0.5">
                                                <span className="text-sm font-medium text-ink">
                                                    {l.short_title}
                                                </span>
                                                <span className="text-meta text-ink-secondary">
                                                    {l.title}
                                                </span>
                                                <span className="text-meta text-ink-muted tabular-nums">
                                                    {formatNumber(l.chunks)} ta
                                                    bo'lak
                                                </span>
                                            </span>
                                            <ExternalLink
                                                className="mt-0.5 size-4 shrink-0 text-ink-muted group-hover:text-brand"
                                                strokeWidth={1.5}
                                                aria-hidden
                                            />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

Assistant.layout = {
    breadcrumbs: [{ title: "Huquqiy yordamchi", href: assistant() }],
};

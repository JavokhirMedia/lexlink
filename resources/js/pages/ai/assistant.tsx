import { Head, useHttp } from '@inertiajs/react';
import { BookOpen, CornerDownLeft, Scale } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { AiDisclaimer } from '@/components/ai/ai-disclaimer';
import { CitationChips } from '@/components/ai/citation-chips';
import { modelLabel } from '@/components/ai/format';
import { AiMarkdown } from '@/components/ai/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { assistant } from '@/routes/ai';
import { ask } from '@/routes/ai/assistant';
import type { LawAnswer } from '@/types/lexlink';

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
    const http = useHttp<{ question: string }, LawAnswer>({ question: '' });
    const bottom = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages]);

    const send = (question: string) => {
        const q = question.trim();
        if (q.length < 3 || http.processing) {
            return;
        }
        const id = Date.now();
        setMessages((m) => [...m, { id, question: q }]);
        http.setData('question', q);
        http.transform(() => ({ question: q }));
        http.post(ask.url(), {
            onSuccess: (answer) => {
                setMessages((m) =>
                    m.map((x) => (x.id === id ? { ...x, answer } : x)),
                );
                http.setData('question', '');
            },
            onError: () =>
                setMessages((m) =>
                    m.map((x) =>
                        x.id === id
                            ? { ...x, error: "Javob olib bo'lmadi." }
                            : x,
                    ),
                ),
        }).catch(() => {
            setMessages((m) =>
                m.map((x) =>
                    x.id === id && !x.answer
                        ? { ...x, error: "Javob olib bo'lmadi." }
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
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send(http.data.question);
        }
    };

    return (
        <>
            <Head title="Huquqiy yordamchi" />
            <div className="mx-auto grid w-full max-w-6xl flex-1 gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div className="flex min-h-[70vh] flex-col rounded-xl border bg-card">
                    <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                        <div>
                            <h1 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                                <Scale
                                    className="size-5 text-primary"
                                    aria-hidden
                                />
                                Huquqiy yordamchi
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Savollarga lex.uz dagi rasmiy qonun matnlari
                                asosida javob, har bir javobda manba moddalar.
                            </p>
                        </div>
                        <Badge variant="outline">
                            {ai_enabled ? 'Gemini + lex.uz' : 'Qidiruv rejimi'}
                        </Badge>
                    </div>

                    <div className="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    Masalan:
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {examples.map((ex) => (
                                        <button
                                            key={ex}
                                            type="button"
                                            onClick={() => send(ex)}
                                            className="rounded-md border px-3 py-1.5 text-left text-sm transition-colors hover:border-primary/50 hover:bg-primary/5"
                                        >
                                            {ex}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {messages.map((m) => (
                            <div key={m.id} className="space-y-3">
                                <div className="ml-auto w-fit max-w-[85%] rounded-lg bg-primary px-4 py-2.5 text-sm text-primary-foreground">
                                    {m.question}
                                </div>
                                <div className="max-w-[92%] space-y-3 rounded-lg border bg-background px-4 py-3">
                                    {!m.answer && !m.error && (
                                        <p className="text-sm text-muted-foreground">
                                            Qonunlar qidirilmoqda…
                                        </p>
                                    )}
                                    {m.error && (
                                        <p className="text-sm text-destructive">
                                            {m.error}
                                        </p>
                                    )}
                                    {m.answer && (
                                        <>
                                            <AiMarkdown
                                                citations={m.answer.citations}
                                            >
                                                {m.answer.answer}
                                            </AiMarkdown>
                                            <CitationChips
                                                citations={m.answer.citations}
                                            />
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <AiDisclaimer
                                                    text={m.answer.disclaimer}
                                                    className="flex-1"
                                                />
                                                <span className="text-[11px] text-muted-foreground">
                                                    {modelLabel(m.answer.model)}
                                                </span>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                        <div ref={bottom} />
                    </div>

                    <form onSubmit={submit} className="border-t p-4">
                        <div className="flex items-end gap-2 rounded-lg border bg-background p-2 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                            <textarea
                                value={http.data.question}
                                onChange={(e) =>
                                    http.setData('question', e.target.value)
                                }
                                onKeyDown={onKey}
                                rows={2}
                                maxLength={2000}
                                placeholder="Savolingizni yozing… (masalan: aliment qaysi daromadlardan ushlab qolinadi?)"
                                className="flex-1 resize-none bg-transparent px-2 py-1 text-sm outline-none placeholder:text-muted-foreground"
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
                                <CornerDownLeft
                                    className="size-4"
                                    aria-hidden
                                />
                                Yuborish
                            </Button>
                        </div>
                        {http.errors.question && (
                            <p className="mt-1 text-xs text-destructive">
                                {http.errors.question}
                            </p>
                        )}
                        <p className="mt-2 text-xs text-muted-foreground">
                            Shaxsiy ma'lumotlar (JShShIR, telefon, pasport)
                            yuborishdan oldin maxfiylashtiriladi.
                        </p>
                    </form>
                </div>

                <aside className="space-y-3">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <BookOpen className="size-4 text-primary" aria-hidden />
                        Bilimlar bazasi
                    </h2>
                    {laws.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Qonunlar import qilinmagan:{' '}
                            <code>php artisan lex:import --from-snapshot</code>
                        </p>
                    )}
                    <ul className="space-y-2">
                        {laws.map((l) => (
                            <li key={l.slug}>
                                <a
                                    href={l.url ?? 'https://lex.uz'}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block rounded-md border bg-card px-3 py-2 text-sm transition-colors hover:border-primary/50"
                                >
                                    <span className="font-medium">
                                        {l.short_title}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        {l.title}
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </aside>
            </div>
        </>
    );
}

Assistant.layout = {
    breadcrumbs: [{ title: 'Huquqiy yordamchi', href: assistant() }],
};

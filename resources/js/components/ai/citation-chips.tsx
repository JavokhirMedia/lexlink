import { BookOpen, ExternalLink } from 'lucide-react';
import type { Citation } from '@/types/lexlink';

export function CitationChips({
    citations,
    numbered = true,
}: {
    citations: Citation[] | null | undefined;
    numbered?: boolean;
}) {
    if (!citations?.length) {
        return null;
    }

    return (
        <div className="space-y-2">
            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <BookOpen className="size-3.5" aria-hidden />
                Huquqiy manbalar (lex.uz)
            </p>
            <ul className="flex flex-wrap gap-1.5">
                {citations.map((c, i) => (
                    <li key={`${c.law}-${c.article}-${i}`}>
                        <a
                            href={c.url ?? 'https://lex.uz'}
                            target="_blank"
                            rel="noreferrer"
                            title={c.title ?? undefined}
                            className="inline-flex max-w-full items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs transition-colors hover:border-primary/50 hover:bg-primary/5"
                        >
                            {numbered && (
                                <span className="font-semibold text-primary tabular-nums">
                                    {i + 1}
                                </span>
                            )}
                            <span className="truncate">
                                {c.law}
                                {c.article ? `, ${c.article}` : ''}
                            </span>
                            <ExternalLink
                                className="size-3 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

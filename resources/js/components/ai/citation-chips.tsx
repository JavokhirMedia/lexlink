import { ExternalLink } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/lexlink';

/**
 * Quiet numbered source list under an AI answer. The numbers match the [n]
 * references in the text; every item links to the article on lex.uz.
 */
export function CitationChips({
    citations,
    numbered = true,
    className,
}: {
    citations: Citation[] | null | undefined;
    numbered?: boolean;
    className?: string;
}) {
    if (!citations?.length) {
        return null;
    }

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <p className="text-meta font-medium text-ink-secondary">
                Manbalar (lex.uz)
            </p>
            <ol className="flex flex-col gap-1">
                {citations.map((c, i) => (
                    <li
                        key={`${c.law}-${c.article}-${i}`}
                        className="flex min-w-0 items-baseline gap-2 text-sm"
                    >
                        {numbered && (
                            <span className="w-6 shrink-0 font-mono text-meta text-ink-muted tabular-nums">
                                [{i + 1}]
                            </span>
                        )}
                        <a
                            href={c.url ?? 'https://lex.uz'}
                            target="_blank"
                            rel="noreferrer"
                            title={c.title ?? undefined}
                            className="focus-ring group inline-flex min-w-0 items-baseline gap-1.5 rounded-xs text-ink-secondary transition-colors hover:text-brand"
                        >
                            <span className="min-w-0 underline decoration-hairline underline-offset-4 group-hover:decoration-current">
                                {c.law}
                                {c.article ? `, ${c.article}` : ''}
                            </span>
                            <ExternalLink
                                className="size-3.5 shrink-0 self-center text-ink-muted"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                        </a>
                    </li>
                ))}
            </ol>
        </div>
    );
}

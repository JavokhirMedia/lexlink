import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/lexlink';

const components: Components = {
    h1: ({ children }) => (
        <h2 className="mt-5 mb-2 text-lg font-semibold tracking-tight first:mt-0">
            {children}
        </h2>
    ),
    h2: ({ children }) => (
        <h3 className="mt-5 mb-2 text-base font-semibold tracking-tight first:mt-0">
            {children}
        </h3>
    ),
    h3: ({ children }) => (
        <h4 className="mt-4 mb-1.5 text-sm font-semibold first:mt-0">
            {children}
        </h4>
    ),
    p: ({ children }) => (
        <p className="my-2 leading-relaxed first:mt-0 last:mb-0">{children}</p>
    ),
    ul: ({ children }) => (
        <ul className="my-2 list-disc space-y-1 pl-5">{children}</ul>
    ),
    ol: ({ children }) => (
        <ol className="my-2 list-decimal space-y-1 pl-5">{children}</ol>
    ),
    blockquote: ({ children }) => (
        <blockquote className="my-3 border-l-2 border-primary/40 pl-3 text-muted-foreground">
            {children}
        </blockquote>
    ),
    table: ({ children }) => (
        <div className="my-3 overflow-x-auto rounded-md border">
            <table className="w-full text-sm">{children}</table>
        </div>
    ),
    thead: ({ children }) => (
        <thead className="bg-muted/50 text-left">{children}</thead>
    ),
    th: ({ children }) => <th className="px-3 py-2 font-medium">{children}</th>,
    td: ({ children }) => (
        <td className="border-t px-3 py-2 tabular-nums">{children}</td>
    ),
    a: ({ href, children }) => {
        const isRef = typeof children === 'string' && /^\d+$/.test(children);

        return (
            <a
                href={href}
                target="_blank"
                rel="noreferrer"
                className={cn(
                    'text-primary underline-offset-2 hover:underline',
                    isRef &&
                        'mx-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded bg-primary/10 px-1 align-super text-[10px] font-semibold no-underline',
                )}
                title={isRef ? 'lex.uz manbasi' : undefined}
            >
                {children}
            </a>
        );
    },
};

/** Turns "[2]" source references into links to the matching lex.uz article. */
function linkRefs(
    text: string,
    citations: Citation[] | null | undefined,
): string {
    if (!citations?.length) {
        return text;
    }

    return text.replace(/\[(\d{1,2})\](?!\()/g, (match, n: string) => {
        const c = citations[Number(n) - 1];

        return c?.url ? `[${n}](${c.url})` : match;
    });
}

export function AiMarkdown({
    children,
    citations,
    className,
}: {
    children: string;
    citations?: Citation[] | null;
    className?: string;
}) {
    return (
        <div className={cn('text-sm text-foreground', className)}>
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {linkRefs(children, citations)}
            </ReactMarkdown>
        </div>
    );
}

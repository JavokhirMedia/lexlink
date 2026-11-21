import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn, normalizeApostrophes } from '@/lib/utils';
import type { Citation } from '@/types/lexlink';

/**
 * Reading typography for AI output: body 16/26, headings by size not weight,
 * tables with tabular figures, [n] references as quiet superscript links.
 * Masked values (3***********12) and placeholders left unrestored ([SHAXS_1])
 * are set in mono so a reader sees at once that they are masked.
 */
const components: Components = {
    h1: ({ children }) => (
        <h2 className="mt-8 mb-3 text-xl leading-7 font-semibold text-ink first:mt-0">
            {children}
        </h2>
    ),
    h2: ({ children }) => (
        <h3 className="mt-7 mb-2 text-lg leading-7 font-semibold text-ink first:mt-0">
            {children}
        </h3>
    ),
    h3: ({ children }) => (
        <h4 className="mt-6 mb-2 text-base font-semibold text-ink first:mt-0">
            {children}
        </h4>
    ),
    h4: ({ children }) => (
        <h5 className="mt-5 mb-1 text-base font-medium text-ink first:mt-0">
            {children}
        </h5>
    ),
    p: ({ children }) => (
        <p className="my-3 first:mt-0 last:mb-0">{children}</p>
    ),
    ul: ({ children }) => (
        <ul className="my-3 list-disc space-y-1.5 pl-6 marker:text-ink-muted">
            {children}
        </ul>
    ),
    ol: ({ children }) => (
        <ol className="my-3 list-decimal space-y-1.5 pl-6 marker:text-ink-muted marker:tabular-nums">
            {children}
        </ol>
    ),
    strong: ({ children }) => (
        <strong className="font-semibold text-ink">{children}</strong>
    ),
    em: ({ children }) => <span>{children}</span>,
    hr: () => <hr className="my-6 border-hairline" />,
    blockquote: ({ children }) => (
        <blockquote className="my-4 border-l-2 border-hairline pl-4 text-ink-secondary">
            {children}
        </blockquote>
    ),
    code: ({ children }) => (
        <code className="rounded-xs bg-raised px-1 py-px font-mono text-[0.875em] text-ink">
            {children}
        </code>
    ),
    pre: ({ children }) => (
        <pre className="my-4 overflow-x-auto rounded-lg bg-raised p-4 font-mono text-meta [&_code]:bg-transparent [&_code]:p-0">
            {children}
        </pre>
    ),
    table: ({ children }) => (
        <div className="my-4 overflow-x-auto rounded-lg border">
            <table className="w-full text-sm tabular-nums">{children}</table>
        </div>
    ),
    thead: ({ children }) => (
        <thead className="bg-raised text-left">{children}</thead>
    ),
    th: ({ children, style }) => (
        <th
            style={style}
            className="h-10 px-4 text-meta font-medium whitespace-nowrap text-ink-secondary"
        >
            {children}
        </th>
    ),
    td: ({ children, style }) => (
        <td style={style} className="h-11 border-t px-4 py-2.5 align-top">
            {children}
        </td>
    ),
    a: ({ href, children }) => {
        const isRef = typeof children === 'string' && /^\d+$/.test(children);

        return (
            <a
                href={href}
                target="_blank"
                rel="noreferrer"
                className={cn(
                    'focus-ring rounded-xs text-brand underline-offset-4 hover:underline',
                    isRef &&
                        'ml-0.5 align-super font-mono text-[13px] leading-none no-underline hover:underline',
                )}
                title={isRef ? `lex.uz manbasi ${children}` : undefined}
            >
                {isRef ? `[${children}]` : children}
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

/** Wraps masked identifiers and leftover placeholders in inline code (mono). */
function markMasked(text: string): string {
    return text
        .replace(/(?<![`\w])\[([A-Z][A-Z_]*_\d+)\](?!\()/g, '`[$1]`')
        .replace(/(?<![`\w*])(\d\*{4,}\d{1,4})(?![`\w*])/g, '`$1`');
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
        <div
            className={cn(
                'text-base leading-[26px] break-words text-ink',
                className,
            )}
        >
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {linkRefs(markMasked(normalizeApostrophes(children)), citations)}
            </ReactMarkdown>
        </div>
    );
}

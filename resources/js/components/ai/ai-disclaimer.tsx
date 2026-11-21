import { usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Small muted line with an info icon: "AI xulosasi yuridik maslahat emas…" */
export function AiDisclaimer({
    text,
    className,
}: {
    text?: string | null;
    className?: string;
}) {
    const shared = usePage<{ ai_disclaimer?: string }>().props.ai_disclaimer;
    const body = text || shared;

    if (!body) {
        return null;
    }

    return (
        <p
            className={cn(
                'flex items-start gap-2 text-meta text-ink-muted',
                className,
            )}
        >
            <Info
                className="mt-px size-4 shrink-0"
                strokeWidth={1.5}
                aria-hidden
            />
            <span>{body}</span>
        </p>
    );
}

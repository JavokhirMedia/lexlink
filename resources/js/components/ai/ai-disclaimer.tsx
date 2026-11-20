import { usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { cn } from '@/lib/utils';

export function AiDisclaimer({
    text,
    className,
}: {
    text?: string | null;
    className?: string;
}) {
    const shared = usePage<{ ai_disclaimer?: string }>().props.ai_disclaimer;

    return (
        <p
            className={cn(
                'flex items-start gap-2 rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground',
                className,
            )}
        >
            <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden />
            <span>{text || shared}</span>
        </p>
    );
}

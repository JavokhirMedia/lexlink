/**
 * LexLink brand mark + wordmark.
 *
 * <LogoMark />  — the seal: a double ring (official seal) holding two
 *                 interlocked links (the "link" between advocate and state).
 *   props: SVG attributes. Strokes use `currentColor`; the inner ring uses
 *          `accent` (default: currentColor) so it can be tinted gold.
 *   e.g. <LogoMark className="size-8 text-primary" accent="var(--gold)" />
 *
 * <Logo />      — mark + "LexLink" wordmark.
 *   props: className?, markClassName?, accent?, subtitle? (string|false; default
 *          "Advokat so'rovlari platformasi"), inverted? (light-on-dark)
 */
import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

export function LogoMark({
    accent = 'currentColor',
    ...props
}: SVGAttributes<SVGElement> & { accent?: string }) {
    return (
        <svg
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <circle
                cx="16"
                cy="16"
                r="14.6"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
            />
            <circle
                cx="16"
                cy="16"
                r="11.6"
                fill="none"
                stroke={accent}
                strokeWidth="0.9"
                strokeDasharray="1.2 1.6"
            />
            <g
                transform="rotate(-45 16 16)"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.1"
                strokeLinecap="round"
            >
                <rect x="7.6" y="12.9" width="10.4" height="6.2" rx="3.1" />
                <rect x="14" y="12.9" width="10.4" height="6.2" rx="3.1" />
            </g>
        </svg>
    );
}

export function Logo({
    className,
    markClassName,
    accent = 'var(--gold)',
    subtitle = "Advokat so'rovlari platformasi",
    inverted = false,
}: {
    className?: string;
    markClassName?: string;
    accent?: string;
    subtitle?: string | false;
    inverted?: boolean;
}) {
    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <LogoMark
                accent={accent}
                className={cn(
                    'size-8 shrink-0',
                    inverted ? 'text-white' : 'text-primary',
                    markClassName,
                )}
            />
            <span className="grid leading-none">
                <span
                    className={cn(
                        'text-[17px] font-semibold tracking-tight',
                        inverted ? 'text-white' : 'text-foreground',
                    )}
                >
                    Lex
                    <span className={inverted ? 'text-gold' : 'text-primary'}>
                        Link
                    </span>
                </span>
                {subtitle && (
                    <span
                        className={cn(
                            'mt-1 text-[11px]',
                            inverted
                                ? 'text-white/65'
                                : 'text-muted-foreground',
                        )}
                    >
                        {subtitle}
                    </span>
                )}
            </span>
        </span>
    );
}

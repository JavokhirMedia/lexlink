/**
 * LexLink brand mark + wordmark.
 *
 * <LogoMark />  — the seal: a double ring (official seal) holding two
 *                 interlocked links (the "link" between advocate and state).
 *   props: SVG attributes. Strokes use `currentColor`; the inner ring uses
 *          `accent` (default: currentColor) (keep it the same: one flat colour).
 *   e.g. <LogoMark className="size-8 text-brand" />
 *
 * <Logo />      — see below (header lockup / compact).
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

export const INSTITUTION_NAME = "Advokat so'rovlari yagona axborot tizimi";

/**
 * <Logo />            — header lockup: mark + two lines (institution name in
 *                       small uppercase, then "LexLink" 20/600).
 * <Logo compact />    — mark + "LexLink" only (footers, tight spaces).
 * <Logo subtitle="Mijoz kabineti" /> — custom second line under "LexLink"
 *                       (legacy; prefer the default lockup).
 * Everything is one flat colour: the mark is brand blue, text is ink.
 */
export function Logo({
    className,
    markClassName,
    compact = false,
    subtitle,
    inverted = false,
}: {
    className?: string;
    markClassName?: string;
    compact?: boolean;
    /** @deprecated accent colours are gone; the mark is a single flat colour. */
    accent?: string;
    subtitle?: string | false;
    inverted?: boolean;
}) {
    const mark = (
        <LogoMark
            className={cn(
                'size-10 shrink-0',
                inverted ? 'text-white' : 'text-brand',
                markClassName,
            )}
        />
    );

    if (compact || subtitle === false) {
        return (
            <span className={cn('inline-flex items-center gap-2.5', className)}>
                {mark}
                <span
                    className={cn(
                        'text-xl leading-none font-semibold',
                        inverted ? 'text-white' : 'text-ink',
                    )}
                >
                    LexLink
                </span>
            </span>
        );
    }

    return (
        <span className={cn('inline-flex min-w-0 items-center gap-3', className)}>
            {mark}
            <span className="flex min-w-0 flex-col gap-1">
                {subtitle === undefined && (
                    <span
                        className={cn(
                            'hidden truncate text-xs leading-none uppercase sm:block',
                            inverted ? 'text-white/75' : 'text-ink-secondary',
                        )}
                    >
                        {INSTITUTION_NAME}
                    </span>
                )}
                <span
                    className={cn(
                        'text-xl leading-6 font-semibold',
                        inverted ? 'text-white' : 'text-ink',
                    )}
                >
                    LexLink
                </span>
                {subtitle && (
                    <span
                        className={cn(
                            'truncate text-xs leading-none',
                            inverted ? 'text-white/75' : 'text-ink-secondary',
                        )}
                    >
                        {subtitle}
                    </span>
                )}
            </span>
        </span>
    );
}

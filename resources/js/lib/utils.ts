import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// Custom type-scale utilities from app.css are font sizes, not colors — tell tailwind-merge so
// cn('text-meta', 'text-ink-secondary') keeps both.
const twMerge = extendTailwindMerge({
    extend: { classGroups: { 'font-size': [{ text: ['meta', 'page-title', 'section-title'] }] } },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/** Uzbek modifier-letter apostrophes (ʻ ʼ ‘ ’) render with wide side-bearings in IBM Plex; show the plain ASCII apostrophe used across the UI. */
export function normalizeApostrophes(text: string): string {
    return text.replace(/[ʻʼ‘’]/g, "'");
}

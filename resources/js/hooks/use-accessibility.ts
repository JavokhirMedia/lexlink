import { useSyncExternalStore } from 'react';

/**
 * "Maxsus imkoniyatlar" (accessibility) settings.
 * Persisted in localStorage under `lexlink.a11y`; applied as classes on <html>:
 *   a11y-text-112 / a11y-text-125, a11y-contrast, a11y-grayscale.
 * The inline script in resources/views/app.blade.php applies the same classes
 * before first paint, so keep the key and shape in sync with it.
 */
export type TextSize = 100 | 112 | 125;

export type A11ySettings = {
    textSize: TextSize;
    contrast: boolean;
    grayscale: boolean;
};

const STORAGE_KEY = 'lexlink.a11y';
const DEFAULTS: A11ySettings = { textSize: 100, contrast: false, grayscale: false };

const listeners = new Set<() => void>();
let current: A11ySettings = read();

function read(): A11ySettings {
    if (typeof window === 'undefined') {
        return DEFAULTS;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return DEFAULTS;
        }

        const parsed = JSON.parse(raw) as Partial<A11ySettings>;
        const size = parsed.textSize;

        return {
            textSize: size === 112 || size === 125 ? size : 100,
            contrast: parsed.contrast === true,
            grayscale: parsed.grayscale === true,
        };
    } catch {
        return DEFAULTS;
    }
}

function apply(s: A11ySettings): void {
    if (typeof document === 'undefined') {
        return;
    }

    const c = document.documentElement.classList;
    c.toggle('a11y-text-112', s.textSize === 112);
    c.toggle('a11y-text-125', s.textSize === 125);
    c.toggle('a11y-contrast', s.contrast);
    c.toggle('a11y-grayscale', s.grayscale);
}

function persist(s: A11ySettings): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(s));
    } catch {
        // storage blocked (private mode); settings still apply for this page
    }
}

export function updateA11y(patch: Partial<A11ySettings>): void {
    current = { ...current, ...patch };
    apply(current);
    persist(current);
    listeners.forEach((l) => l());
}

export function resetA11y(): void {
    updateA11y(DEFAULTS);
}

export function isA11yDefault(s: A11ySettings): boolean {
    return s.textSize === 100 && !s.contrast && !s.grayscale;
}

export function useAccessibility(): A11ySettings {
    return useSyncExternalStore(
        (cb) => {
            listeners.add(cb);

            return () => listeners.delete(cb);
        },
        () => current,
        () => DEFAULTS,
    );
}

/** Re-apply on boot (covers pages rendered before the inline script ran). */
export function initializeAccessibility(): void {
    current = read();
    apply(current);
}

const money = new Intl.NumberFormat('uz-UZ', { maximumFractionDigits: 0 });

export function formatSum(value: number | null | undefined): string {
    return value == null ? '—' : `${money.format(value)} so'm`;
}

/** Client-side fallback: restore [SHAXS_1]-style placeholders from structured._pii (names / masked ids only). */
export function restorePii(
    text: string | null | undefined,
    structured: Record<string, unknown> | null | undefined,
): string {
    const map = (structured?._pii ?? null) as Record<string, string> | null;
    if (!text || !map) {
        return text ?? '';
    }

    return Object.entries(map).reduce(
        (acc, [ph, value]) => acc.split(ph).join(value),
        text,
    );
}

export function modelLabel(model: string | null | undefined): string {
    if (!model) {
        return '';
    }

    return model.startsWith('fallback') ? 'Shablon (AI kalitsiz)' : model;
}

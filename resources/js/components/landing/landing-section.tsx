/**
 * Landing building blocks.
 *
 * <Container>…</Container> — the 1280 content column with page padding (16 / 32).
 *
 * <LandingSection id="muammo" tone="surface" title="…" description="…" actions={…}>
 *   …content
 * </LandingSection>
 *
 * Props (LandingSection):
 *   id?:          string — anchor target for the header nav
 *   tone?:        'page' | 'surface' | 'brand' — background step (default 'page';
 *                 'brand' is the single brand-soft band, used once)
 *   title:        ReactNode — h2, 32/40 600 (26/34 on mobile)
 *   description?: ReactNode — 18/28 secondary, max 40rem
 *   actions?:     ReactNode — right of the heading on desktop
 *   className?:   string
 */
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Container({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('mx-auto w-full max-w-content px-4 lg:px-8', className)}>
            {children}
        </div>
    );
}

const TONES = {
    page: 'bg-page',
    surface: 'border-y border-hairline bg-surface',
    brand: 'bg-brand-soft',
} as const;

export function LandingSection({
    id,
    tone = 'page',
    title,
    description,
    actions,
    className,
    children,
}: {
    id?: string;
    tone?: keyof typeof TONES;
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    className?: string;
    children?: ReactNode;
}) {
    const headingId = id ? `${id}-title` : undefined;

    return (
        <section
            id={id}
            aria-labelledby={headingId}
            className={cn('scroll-mt-header', TONES[tone], className)}
        >
            <Container className="flex flex-col gap-10 py-16 lg:gap-12 lg:py-24">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex max-w-2xl flex-col gap-3">
                        <h2
                            id={headingId}
                            className="text-[26px] leading-[34px] font-semibold text-ink lg:text-[32px] lg:leading-10"
                        >
                            {title}
                        </h2>
                        {description && (
                            <p className="max-w-[40rem] text-lg text-ink-secondary">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && <div className="shrink-0">{actions}</div>}
                </div>
                {children}
            </Container>
        </section>
    );
}

/**
 * <SiteFooter /> — public pages footer: product line, Huquqiy xarita, Yordam,
 * hackathon/demo line.
 */
import { Link } from '@inertiajs/react';
import { LogoMark } from '@/components/brand/logo';
import { help, legalMap } from '@/routes';

export function SiteFooter() {
    return (
        <footer className="border-t border-hairline bg-surface print:hidden">
            <div className="mx-auto flex w-full max-w-content flex-col gap-4 px-4 py-8 text-sm text-ink-secondary sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div className="flex items-center gap-3">
                    <LogoMark className="size-6 shrink-0 text-brand" />
                    <span>
                        LexLink · advokat so'rovlari yagona axborot tizimi
                    </span>
                </div>
                <nav
                    aria-label="Pastki menyu"
                    className="flex flex-wrap items-center gap-x-6 gap-y-2"
                >
                    <Link
                        href={legalMap()}
                        className="focus-ring rounded-sm transition-colors hover:text-ink"
                    >
                        Huquqiy xarita
                    </Link>
                    <Link
                        href={help()}
                        className="focus-ring rounded-sm transition-colors hover:text-ink"
                    >
                        Yordam
                    </Link>
                    <span className="text-ink-muted">
                        Lex Hackathon 2026 · demo
                    </span>
                </nav>
            </div>
        </footer>
    );
}

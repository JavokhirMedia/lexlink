/**
 * Public shell: utility bar + header (no sidebar) + footer. Used by welcome,
 * guest legal-map, help, auth/*, client/*, invites/*, onboarding/*.
 *
 * Props (pass via `Page.layout = { ... }`):
 *   navLinks?:  { label, href }[] — in-page anchors shown in the header (landing)
 *   container?: boolean — wrap children in the 1280 content container with page
 *               padding (default true). Set false for full-bleed pages (landing).
 */
import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/shell/site-footer';
import { SiteHeader } from '@/components/shell/site-header';
import type { PublicNavLink } from '@/components/shell/site-header';
import { UtilityBar } from '@/components/shell/utility-bar';

export default function PublicLayout({
    navLinks = [],
    container = true,
    children,
}: {
    navLinks?: PublicNavLink[];
    container?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-svh flex-col bg-page">
            <a
                href="#main"
                className="sr-only z-50 rounded-md bg-surface px-4 py-2 text-sm font-medium text-brand focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >
                Asosiy mazmunga o'tish
            </a>
            <UtilityBar contained />
            <SiteHeader variant="public" navLinks={navLinks} />
            <main id="main" tabIndex={-1} className="flex flex-1 flex-col">
                {container ? (
                    <div className="mx-auto flex w-full max-w-content flex-1 flex-col px-4 py-8 lg:px-8 lg:py-12">
                        {children}
                    </div>
                ) : (
                    children
                )}
            </main>
            <SiteFooter />
        </div>
    );
}

/**
 * App shell (docs/DESIGN.md "Shell"): utility bar, sticky 72px header, a
 * 264px white sidebar (full height, nav sticky under the header) on >=1024px,
 * and the content column (max 1280, padding 32 desktop / 16 mobile) with
 * breadcrumbs above the page.
 *
 * Pages render only their body: <PageHeader/> first, then <Section/>s.
 * Do not add page-level padding or max-width wrappers.
 */
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarNav } from '@/components/shell/sidebar-nav';
import { SiteHeader } from '@/components/shell/site-header';
import { UtilityBar } from '@/components/shell/utility-bar';
import type { BreadcrumbItem } from '@/types';

export default function AppShellLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
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
            <UtilityBar />
            <SiteHeader variant="app" />
            <div className="flex flex-1">
                <aside className="hidden w-sidebar shrink-0 border-r border-hairline bg-surface lg:block print:hidden">
                    <div className="sticky top-header max-h-[calc(100svh-var(--spacing-header))] overflow-y-auto px-3 py-6">
                        <SidebarNav />
                    </div>
                </aside>
                <main
                    id="main"
                    className="min-w-0 flex-1 overflow-x-clip"
                    tabIndex={-1}
                >
                    <div className="mx-auto flex w-full max-w-content flex-col gap-4 px-4 py-6 lg:px-8 lg:py-8">
                        {breadcrumbs.length > 1 && (
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        )}
                        <div className="flex min-w-0 flex-col">{children}</div>
                    </div>
                </main>
            </div>
        </div>
    );
}

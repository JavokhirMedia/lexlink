import { Breadcrumbs } from '@/components/breadcrumbs';
import { DemoBadge } from '@/components/brand/demo-badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur-sm supports-[backdrop-filter]:bg-background/80 md:px-6">
            <SidebarTrigger
                className="-ml-1 text-muted-foreground hover:text-foreground"
                aria-label="Menyuni ochish/yopish"
            />
            <span aria-hidden="true" className="h-4 w-px bg-border" />
            <div className="min-w-0 flex-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <DemoBadge className="hidden sm:inline-flex" />
            <DemoBadge compact className="sm:hidden" />
        </header>
    );
}

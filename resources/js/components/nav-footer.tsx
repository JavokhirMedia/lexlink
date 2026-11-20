import { Link, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import type { ComponentPropsWithoutRef } from 'react';
import { DemoBadge } from '@/components/brand/demo-badge';
import { formatMoney } from '@/components/brand/money';
import { LicenseStatus } from '@/components/brand/role-badge';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

/**
 * Sidebar footer: licence status + wallet balance (expanded), wallet icon
 * (collapsed), and the demo marker.
 */
export function NavFooter({
    className,
    ...props
}: ComponentPropsWithoutRef<typeof SidebarGroup>) {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    const license = auth.user.license;
    const balance = auth.wallet_balance;

    return (
        <SidebarGroup {...props} className={cn('gap-2 p-0', className)}>
            <div className="mx-2 rounded-md border border-sidebar-border bg-sidebar-accent/40 p-3 text-sidebar-foreground group-data-[collapsible=icon]:hidden">
                {license && (
                    <div className="mb-2.5 border-b border-sidebar-border pb-2.5">
                        <div className="mb-1 text-[11px] text-sidebar-muted">
                            Advokatlik litsenziyasi
                        </div>
                        <LicenseStatus license={license} variant="dark" />
                    </div>
                )}
                <Link
                    href="/billing"
                    className="group/wallet flex items-end justify-between gap-2"
                >
                    <div className="min-w-0">
                        <div className="text-[11px] text-sidebar-muted">
                            Hisob balansi
                        </div>
                        <div className="mt-0.5 truncate text-[15px] font-semibold tabular-nums">
                            {formatMoney(balance ?? 0)}
                        </div>
                    </div>
                    <span className="shrink-0 text-[11px] text-sidebar-primary group-hover/wallet:underline">
                        To'ldirish
                    </span>
                </Link>
            </div>

            <SidebarMenu className="hidden group-data-[collapsible=icon]:flex">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        asChild
                        tooltip={{
                            children: `Balans: ${formatMoney(balance ?? 0)}`,
                        }}
                    >
                        <Link href="/billing">
                            <Wallet />
                            <span>Balans</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div className="px-2 group-data-[collapsible=icon]:hidden">
                <DemoBadge variant="dark" />
            </div>
        </SidebarGroup>
    );
}

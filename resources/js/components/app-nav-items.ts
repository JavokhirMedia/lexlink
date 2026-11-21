import {
    BookOpenCheck,
    FileSearch,
    FolderOpen,
    History,
    LayoutDashboard,
    Map as MapIcon,
    Users,
    Wallet,
} from "lucide-react";
import { dashboard, legalMap } from "@/routes";
import { index as clientIndex } from "@/routes/client";
import type { NavItem, UserRole } from "@/types";

type AppNavItem = NavItem & { roles?: UserRole[] };

/** Clients (ishonch bildiruvchi) only see their own cases. */
export const CLIENT_NAV_ITEMS: NavItem[] = [
    { title: "Mening ishlarim", href: clientIndex(), icon: FolderOpen },
];

/** Sidebar / header navigation (ARCHITECTURE.md). Hrefs are plain strings where routes are owned by other modules. */
export const APP_NAV_ITEMS: AppNavItem[] = [
    { title: "Bosh sahifa", href: dashboard(), icon: LayoutDashboard },
    { title: "Ishlar", href: "/cases", icon: FolderOpen },
    { title: "So'rovlar", href: "/requests", icon: FileSearch },
    { title: "Huquqiy xarita", href: legalMap(), icon: MapIcon },
    { title: "Jamoa", href: "/team", icon: Users, roles: ["advocate"] },
    { title: "Audit jurnali", href: "/audit", icon: History },
    { title: "Tarif va balans", href: "/billing", icon: Wallet },
    { title: "Huquqiy yordamchi", href: "/ai/assistant", icon: BookOpenCheck },
];

export function navItemsFor(role: UserRole | null | undefined): NavItem[] {
    if (role === "client") {
        return CLIENT_NAV_ITEMS;
    }

    return APP_NAV_ITEMS.filter(
        (item) => !item.roles || (role ? item.roles.includes(role) : false),
    ).map((item) => ({ title: item.title, href: item.href, icon: item.icon }));
}

import { LogoMark } from '@/components/brand/logo';

/** Sidebar / header brand lockup (sits inside a SidebarMenuButton size="lg"). */
export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center">
                <LogoMark
                    accent="var(--sidebar-primary)"
                    className="size-8 text-current"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-none">
                <span className="truncate text-[15px] font-semibold tracking-tight">
                    Lex<span className="text-sidebar-primary">Link</span>
                </span>
                <span className="mt-1 truncate text-[11px] text-sidebar-muted">
                    Advokat so'rovlari platformasi
                </span>
            </div>
        </>
    );
}

/**
 * <SiteHeader variant="app" />     — app pages: mobile menu button (opens the
 *                                    nav in a Sheet below 1024px), logo lockup,
 *                                    wallet (advocates) + user menu.
 * <SiteHeader variant="public" />  — public pages: logo lockup, optional
 *                                    in-page links, "OneID orqali kirish" for
 *                                    guests or "Kabinetga o'tish" + user menu.
 *
 * 72px, white, hairline bottom; sticky at the top of the viewport.
 */
import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import { Logo } from '@/components/brand/logo';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';
import { index as clientIndex } from '@/routes/client';
import { SidebarNav } from './sidebar-nav';
import { UserMenu } from './user-menu';
import { WalletChip } from './wallet-chip';

export type PublicNavLink = { label: string; href: string };

export function SiteHeader({
    variant = 'app',
    navLinks = [],
    className,
}: {
    variant?: 'app' | 'public';
    navLinks?: PublicNavLink[];
    className?: string;
}) {
    const page = usePage();
    const user = page.props.auth?.user;
    const [open, setOpen] = useState(false);

    const cabinetHref = user?.role === 'client' ? clientIndex() : dashboard();
    const onLoginPage = page.component.startsWith('auth/');

    return (
        <header
            className={cn(
                'sticky top-0 z-40 h-header border-b border-hairline bg-surface print:static',
                className,
            )}
        >
            <div
                className={cn(
                    'flex h-full items-center gap-3 px-4 lg:gap-6 lg:px-8',
                    variant === 'public' && 'mx-auto w-full max-w-content',
                )}
            >
                {variant === 'app' && (
                    <Sheet open={open} onOpenChange={setOpen}>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="-ml-2 lg:hidden"
                            aria-label="Menyuni ochish"
                            onClick={() => setOpen(true)}
                        >
                            <Menu className="size-5" strokeWidth={1.5} />
                        </Button>
                        <SheetContent
                            side="left"
                            className="w-[300px] max-w-[85vw] gap-0 p-0"
                        >
                            <div className="flex h-header items-center border-b border-hairline px-4">
                                <SheetTitle asChild>
                                    <span>
                                        <Logo compact />
                                    </span>
                                </SheetTitle>
                                <SheetDescription className="sr-only">
                                    Asosiy menyu
                                </SheetDescription>
                            </div>
                            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-3">
                                <SidebarNav onNavigate={() => setOpen(false)} />
                                <WalletChip className="items-start border border-hairline text-left md:hidden" />
                            </div>
                        </SheetContent>
                    </Sheet>
                )}

                <Link
                    href={variant === 'app' && user ? cabinetHref : home()}
                    className="focus-ring min-w-0 rounded-md"
                    aria-label="LexLink bosh sahifa"
                >
                    <Logo />
                </Link>

                {variant === 'public' && navLinks.length > 0 && (
                    <nav
                        aria-label="Sahifa bo'limlari"
                        className="ml-4 hidden items-center gap-1 lg:flex"
                    >
                        {navLinks.map((l) => (
                            <a
                                key={l.href}
                                href={l.href}
                                className="focus-ring rounded-md px-3 py-2 text-sm text-ink-secondary transition-colors hover:bg-raised hover:text-ink"
                            >
                                {l.label}
                            </a>
                        ))}
                    </nav>
                )}

                <div className="ml-auto flex shrink-0 items-center gap-2">
                    {variant === 'app' && (
                        <WalletChip className="hidden md:flex" />
                    )}
                    {variant === 'public' && user && (
                        <Button asChild variant="outline" className="hidden sm:inline-flex">
                            <Link href={cabinetHref}>Kabinetga o'tish</Link>
                        </Button>
                    )}
                    {variant === 'public' && !user && !onLoginPage && (
                        <Button asChild>
                            <Link href="/login">
                                <span className="sm:hidden">Kirish</span>
                                <span className="hidden sm:inline">
                                    OneID orqali kirish
                                </span>
                            </Link>
                        </Button>
                    )}
                    <UserMenu />
                </div>
            </div>
        </header>
    );
}

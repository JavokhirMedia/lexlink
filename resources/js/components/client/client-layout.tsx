import { Link, router, usePage } from "@inertiajs/react";
import { LogOut } from "lucide-react";
import type { ReactNode } from "react";
import { Logo } from "@/components/brand/logo";
import { Button } from "@/components/ui/button";
import { logout } from "@/routes";
import { index as clientIndex } from "@/routes/client";
import type { SharedAuthUser } from "@/types/lexlink";

/** Simple shell for the client (ishonch bildiruvchi) portal — no advocate sidebar. */
export default function ClientLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<{ auth: { user: SharedAuthUser | null } }>().props;
    const user = auth?.user;

    return (
        <div className="min-h-svh bg-background">
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-3">
                    <Link
                        href={user?.role === "client" ? clientIndex() : "/"}
                        className="text-foreground"
                    >
                        <Logo
                            subtitle="Mijoz kabineti"
                            markClassName="size-8 text-primary"
                        />
                    </Link>
                    {user && (
                        <div className="flex items-center gap-3 text-sm">
                            <span className="hidden text-muted-foreground sm:inline">
                                {user.name}
                                {user.role_label && ` · ${user.role_label}`}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => router.post(logout.url())}
                            >
                                <LogOut /> Chiqish
                            </Button>
                        </div>
                    )}
                </div>
            </header>
            <main className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-6">
                {children}
            </main>
            <footer className="mx-auto max-w-4xl px-4 pb-8 text-xs text-muted-foreground">
                Ma'lumotlar advokatingiz tomonidan advokat orderi asosida
                ko'rsatiladi. Barcha ko'rishlar audit jurnalida qayd etiladi.
                Demo: barcha shaxslar va raqamlar uydirma.
            </footer>
        </div>
    );
}

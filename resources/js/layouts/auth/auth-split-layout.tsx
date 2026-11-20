import { Link } from '@inertiajs/react';
import { FileLock2, ScrollText, ShieldCheck } from 'lucide-react';
import { DemoBadge } from '@/components/brand/demo-badge';
import { Logo, LogoMark } from '@/components/brand/logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const GUARANTEES = [
    {
        icon: ShieldCheck,
        title: "Asos bo'lmasa — so'rov yo'q",
        text: "Har bir so'rov sud qarori yoki advokat orderi bilan tekshiriladi.",
    },
    {
        icon: FileLock2,
        title: 'Shifrlangan javoblar',
        text: 'JShShIR va javob mazmuni shifrlangan holda saqlanadi.',
    },
    {
        icon: ScrollText,
        title: "Har bir ko'rish auditda",
        text: "Kim, qachon, nimani ko'rgani o'zgarmas jurnalga yoziladi.",
    },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <aside className="relative hidden overflow-hidden bg-sidebar text-white lg:flex lg:flex-col lg:justify-between lg:p-10 xl:p-12">
                <LogoMark
                    aria-hidden="true"
                    accent="var(--sidebar-primary)"
                    className="pointer-events-none absolute -right-28 -bottom-28 size-[420px] text-white/[0.05]"
                />
                <Link href={home()} className="relative w-fit">
                    <Logo inverted />
                </Link>

                <div className="relative max-w-md">
                    <p className="font-display text-[28px] leading-snug font-medium text-white">
                        Davlat ma'lumotlariga huquqiy asos bilan, bitta oynada.
                    </p>
                    <ul className="mt-8 space-y-5">
                        {GUARANTEES.map((g) => (
                            <li key={g.title} className="flex gap-3">
                                <g.icon
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-sidebar-primary"
                                />
                                <div>
                                    <div className="text-sm font-medium text-white">
                                        {g.title}
                                    </div>
                                    <div className="mt-0.5 text-sm text-white/65">
                                        {g.text}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative flex items-center justify-between gap-4 text-xs text-white/55">
                    <span>Lex Hackathon 2026 · demo</span>
                    <DemoBadge variant="dark" />
                </div>
            </aside>

            <main className="flex flex-col">
                <div className="flex items-center justify-between border-b px-6 py-4 lg:hidden">
                    <Link href={home()}>
                        <Logo subtitle={false} />
                    </Link>
                    <DemoBadge compact />
                </div>
                <div className="flex flex-1 items-center justify-center px-6 py-10 sm:px-10">
                    <div className="w-full max-w-[400px]">
                        {(title || description) && (
                            <div className="mb-8 space-y-2">
                                {title && (
                                    <h1 className="font-display text-[26px] leading-tight font-semibold">
                                        {title}
                                    </h1>
                                )}
                                {description && (
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                        )}
                        {children}
                    </div>
                </div>
                <div className="px-6 pb-6 text-center text-xs text-muted-foreground sm:px-10 lg:text-left">
                    Kirish orqali siz advokat sirini saqlash va shaxsga doir
                    ma'lumotlarni himoya qilish qoidalariga rozilik bildirasiz.
                </div>
            </main>
        </div>
    );
}

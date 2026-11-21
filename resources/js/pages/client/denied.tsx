import { Head, Link, usePage } from "@inertiajs/react";
import { ShieldX } from "lucide-react";
import ClientLayout from "@/components/client/client-layout";
import { Button } from "@/components/ui/button";
import { dashboard } from "@/routes";
import type { SharedAuthUser } from "@/types/lexlink";

type Props = { reason: string; title: string; message: string };

export default function ClientDenied({ title, message }: Props) {
    const { auth } = usePage<{ auth: { user: SharedAuthUser | null } }>().props;

    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex max-w-lg flex-col items-center gap-4 rounded-lg border bg-card px-6 py-12 text-center shadow-xs">
                <span className="flex size-10 items-center justify-center rounded-md bg-danger-soft text-danger">
                    <ShieldX className="size-[18px]" strokeWidth={1.5} aria-hidden />
                </span>
                <div className="flex flex-col gap-2">
                    <p className="text-meta text-ink-secondary">
                        403 · Ruxsat berilmadi
                    </p>
                    <h1 className="text-section-title text-ink">{title}</h1>
                    <p className="text-base text-ink-secondary">{message}</p>
                </div>
                <p className="text-meta text-ink-muted">
                    Mijozga faqat advokat orderida ko'rsatilgan ishonch
                    bildiruvchi sifatida, OneID orqali tasdiqlangan JShShIR
                    bilan kirish beriladi (Advokatura to'g'risidagi Qonun,
                    advokat siri).
                </p>
                {auth?.user?.role && auth.user.role !== "client" && (
                    <Button asChild variant="outline">
                        <Link href={dashboard()}>Bosh sahifaga qaytish</Link>
                    </Button>
                )}
            </div>
        </>
    );
}

ClientDenied.layout = ClientLayout;

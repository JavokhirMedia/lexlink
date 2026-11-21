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
            <div className="mx-auto flex max-w-lg flex-col items-center gap-4 rounded-lg border bg-card px-6 py-12 text-center">
                <ShieldX className="size-10 text-danger" aria-hidden />
                <div className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        403 · Ruxsat berilmadi
                    </p>
                    <h1 className="text-lg font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">{message}</p>
                </div>
                <p className="text-xs text-muted-foreground">
                    Mijozga faqat advokat orderida ko'rsatilgan ishonch
                    bildiruvchi sifatida, OneID orqali tasdiqlangan JShShIR
                    bilan kirish beriladi (Advokatura to'g'risidagi Qonun,
                    advokat siri).
                </p>
                {auth?.user?.role && auth.user.role !== "client" && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={dashboard()}>Bosh sahifaga qaytish</Link>
                    </Button>
                )}
            </div>
        </>
    );
}

ClientDenied.layout = ClientLayout;

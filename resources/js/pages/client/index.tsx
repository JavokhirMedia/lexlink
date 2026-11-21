import { Head, Link } from "@inertiajs/react";
import { Briefcase, ChevronRight, Lock } from "lucide-react";
import ClientLayout from "@/components/client/client-layout";
import { show as clientCaseShow } from "@/routes/client/cases";
import type { ClientCaseInfo } from "@/types/lexlink";

type Props = {
    cases: (ClientCaseInfo & {
        requests_count: number;
        granted_at: string | null;
    })[];
    note: string;
};

export default function ClientIndex({ cases, note }: Props) {
    return (
        <>
            <Head title="Mening ishlarim" />
            <div>
                <h1 className="text-xl font-semibold tracking-tight">
                    Mening ishlarim
                </h1>
                <p className="text-sm text-muted-foreground">
                    Advokatingiz advokat orderi asosida sizga ochgan ishlar.
                </p>
            </div>

            {cases.length === 0 ? (
                <div className="rounded-lg border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
                    Hozircha sizga ochilgan ish yo'q. Advokatingiz yuborgan
                    kirish havolasini oching.
                </div>
            ) : (
                <ul className="divide-y rounded-lg border bg-card">
                    {cases.map((c) => (
                        <li key={c.id}>
                            <Link
                                href={clientCaseShow.url(c.id)}
                                className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-muted/50"
                            >
                                <div className="flex min-w-0 items-start gap-3">
                                    <Briefcase className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {c.title}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            Advokat: {c.advocate.name}
                                            {c.advocate.license_number &&
                                                ` (${c.advocate.license_number})`}{" "}
                                            · Order{" "}
                                            <span className="font-mono">
                                                {c.order_number}
                                            </span>{" "}
                                            · {c.requests_count} ta so'rov
                                        </p>
                                    </div>
                                </div>
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <p className="flex items-start gap-2 text-xs text-muted-foreground">
                <Lock className="mt-0.5 size-3.5 shrink-0" /> {note}
            </p>
        </>
    );
}

ClientIndex.layout = ClientLayout;

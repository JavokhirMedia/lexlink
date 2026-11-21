import { Head } from "@inertiajs/react";
import { ChevronRight, Fingerprint, KeyRound, ScrollText } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { AuthCard } from "@/layouts/auth/auth-card";
import { redirect } from "@/routes/oneid";

type Persona = {
    pinfl: string;
    name: string;
    label: string;
    hint: string;
    group: string;
};

type Props = {
    personas: Persona[];
    error?: string | null;
};

/** Demo groups in the order a reviewer is likely to try them. */
const GROUPS: { key: string; title: string }[] = [
    { key: "advocate_active", title: "Advokat" },
    { key: "lawyer", title: "Yurist" },
    { key: "advocate_revoked", title: "Litsenziyasi to'xtatilgan advokat" },
    { key: "client", title: "Mijoz" },
    { key: "admin", title: "Administrator" },
    { key: "new", title: "Yangi foydalanuvchi" },
];

const CHECKS: { icon: LucideIcon; text: string }[] = [
    {
        icon: Fingerprint,
        text: "Shaxsingiz yagona identifikatsiya tizimida tasdiqlanadi",
    },
    {
        icon: KeyRound,
        text: "LexLink parol so'ramaydi va saqlamaydi",
    },
    {
        icon: ScrollText,
        text: "Advokatlik litsenziyasi alohida, advokatlar reestrida tekshiriladi",
    },
];

export default function Login({ personas, error }: Props) {
    // Full page navigation (not an Inertia visit): the flow leaves the SPA for the OneID authorize page.
    const oneIdUrl = redirect.url();

    const groups = GROUPS.map((g) => ({
        ...g,
        people: personas.filter((p) => p.group === g.key),
    })).filter((g) => g.people.length > 0);

    return (
        <>
            <Head title="Tizimga kirish" />

            <AuthCard
                title="Tizimga kirish"
                description="Advokat va yuristlar uchun davlat idoralariga so'rovlar tizimi"
            >
                <div className="flex flex-col gap-6">
                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    <Button asChild size="lg" className="h-12 w-full text-base">
                        <a href={oneIdUrl} data-test="oneid-login-button">
                            OneID orqali kirish
                        </a>
                    </Button>

                    <div className="flex flex-col gap-3 border-t pt-6">
                        <p className="text-sm font-medium text-ink">
                            OneID orqali kirishda nima tekshiriladi
                        </p>
                        <ul className="flex flex-col gap-3">
                            {CHECKS.map(({ icon: Icon, text }) => (
                                <li
                                    key={text}
                                    className="flex items-start gap-3 text-sm text-ink-secondary"
                                >
                                    <Icon
                                        aria-hidden="true"
                                        strokeWidth={1.5}
                                        className="mt-0.5 size-4 shrink-0 text-ink-muted"
                                    />
                                    {text}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </AuthCard>

            {groups.length > 0 && (
                <section
                    aria-labelledby="demo-title"
                    className="relative w-full max-w-[440px]"
                >
                    <div className="mb-4 flex flex-col gap-1 px-1">
                        <h2
                            id="demo-title"
                            className="text-base font-semibold text-ink"
                        >
                            Demo rejimi
                        </h2>
                        <p className="text-sm text-ink-secondary">
                            Uydirma shaxs sifatida OneID sinov sahifasi orqali
                            kiring. Barcha ma'lumotlar to'qima.
                        </p>
                    </div>

                    <div className="flex flex-col gap-4">
                        {groups.map((g) => (
                            <div key={g.key} className="flex flex-col gap-2">
                                <h3 className="px-1 text-meta text-ink-muted">
                                    {g.title}
                                </h3>
                                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                                    {g.people.map((p) => (
                                        <li key={p.pinfl}>
                                            <a
                                                href={redirect.url({
                                                    query: {
                                                        login_hint: p.pinfl,
                                                    },
                                                })}
                                                data-test="demo-login-button"
                                                className="focus-ring flex min-h-11 items-center gap-3 px-4 py-2 transition-colors hover:bg-page"
                                            >
                                                <span className="flex min-w-0 flex-1 flex-col">
                                                    <span className="truncate text-sm font-medium text-ink">
                                                        {p.name}
                                                    </span>
                                                    {p.label.includes(
                                                        "yangi foydalanuvchi",
                                                    ) &&
                                                        g.key !== "new" && (
                                                            <span className="text-meta text-ink-muted">
                                                                Hali ro'yxatdan
                                                                o'tmagan
                                                            </span>
                                                        )}
                                                </span>
                                                <RegistryChip
                                                    value={p.hint}
                                                    kind="pinfl"
                                                    size="sm"
                                                />
                                                <ChevronRight
                                                    aria-hidden="true"
                                                    strokeWidth={1.5}
                                                    className="size-4 shrink-0 text-ink-muted"
                                                />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}

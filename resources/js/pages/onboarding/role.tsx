import { Form, Head } from "@inertiajs/react";
import { BadgeCheck } from "lucide-react";
import type { ReactNode } from "react";
import { useState } from "react";
import InputError from "@/components/input-error";
import { OnboardingShell } from "@/components/onboarding/onboarding-shell";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import { store } from "@/routes/onboarding/role";

type Role = "advocate" | "lawyer";

const OPTIONS: {
    value: Role;
    title: string;
    subtitle: string;
    points: string[];
}[] = [
    {
        value: "advocate",
        title: "Advokat",
        subtitle: "Amaldagi litsenziyaga ega",
        points: [
            "Davlat idoralariga advokat so'rovini imzolaydi va yuboradi",
            "Ishlar, huquqiy asoslar va jamoani boshqaradi",
            "Keyingi qadamda litsenziya advokatlar reestrida tekshiriladi",
        ],
    },
    {
        value: "lawyer",
        title: "Yurist",
        subtitle: "Litsenziyasiz, advokat jamoasida",
        points: [
            "So'rov loyihalarini tayyorlaydi, imzolamaydi",
            "Advokat taklifi bilan uning jamoasida ishlaydi",
            "Javoblarni advokat ruxsati va sir saqlash majburiyatidan so'ng ko'radi",
        ],
    },
];

export default function OnboardingRole({ name }: { name: string }) {
    const [role, setRole] = useState<Role | null>(null);

    return (
        <>
            <Head title="Rolni tanlash" />

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <p className="flex items-center gap-2 text-sm text-ink-secondary">
                            <BadgeCheck
                                aria-hidden="true"
                                strokeWidth={1.5}
                                className="size-[18px] shrink-0 text-success"
                            />
                            <span>
                                OneID orqali tasdiqlandi:{" "}
                                <span className="font-medium text-ink">
                                    {name}
                                </span>
                            </span>
                        </p>

                        <fieldset className="flex flex-col gap-3">
                            <legend className="mb-3 text-base font-medium text-ink">
                                Platformada qanday maqomda ishlaysiz
                            </legend>
                            <div className="grid gap-4 md:grid-cols-2">
                                {OPTIONS.map((o) => {
                                    const checked = role === o.value;

                                    return (
                                        <label
                                            key={o.value}
                                            data-test={`role-${o.value}`}
                                            className={cn(
                                                "relative flex cursor-pointer flex-col gap-4 rounded-lg border bg-card p-5 transition-colors",
                                                "has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand",
                                                checked
                                                    ? "border-brand ring-1 ring-brand"
                                                    : "hover:border-ink-muted/50",
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="role"
                                                value={o.value}
                                                checked={checked}
                                                onChange={() =>
                                                    setRole(o.value)
                                                }
                                                className="sr-only"
                                            />
                                            <span className="flex items-start justify-between gap-3">
                                                <span className="flex flex-col gap-1">
                                                    <span className="text-lg font-semibold text-ink">
                                                        {o.title}
                                                    </span>
                                                    <span className="text-sm text-ink-secondary">
                                                        {o.subtitle}
                                                    </span>
                                                </span>
                                                <span
                                                    aria-hidden="true"
                                                    className={cn(
                                                        "mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors",
                                                        checked
                                                            ? "border-brand"
                                                            : "border-input",
                                                    )}
                                                >
                                                    {checked && (
                                                        <span className="size-2.5 rounded-full bg-brand" />
                                                    )}
                                                </span>
                                            </span>
                                            <ul className="flex flex-col gap-2 border-t pt-4 text-sm text-ink-secondary">
                                                {o.points.map((p) => (
                                                    <li
                                                        key={p}
                                                        className="flex gap-2"
                                                    >
                                                        <span
                                                            aria-hidden="true"
                                                            className="mt-2 size-1 shrink-0 rounded-full bg-ink-muted"
                                                        />
                                                        {p}
                                                    </li>
                                                ))}
                                            </ul>
                                        </label>
                                    );
                                })}
                            </div>
                        </fieldset>

                        <aside className="flex flex-col gap-2 rounded-lg bg-raised p-4">
                            <p className="text-sm font-medium text-ink">
                                Nima uchun farq bor
                            </p>
                            <p className="text-sm text-ink-secondary">
                                “Advokatura to'g'risida”gi Qonunga muvofiq
                                advokatlik faoliyatini faqat litsenziyaga ega
                                advokat amalga oshiradi. Shu sababli advokat
                                so'rovini faqat amaldagi litsenziyaga ega
                                advokat imzolaydi va idoraga yuboradi. Yurist
                                advokatga yordamchi sifatida loyiha tayyorlaydi,
                                lekin imzo va yuborish huquqiga ega emas.
                            </p>
                        </aside>

                        <InputError message={errors.role} />

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full sm:w-auto sm:self-end"
                            disabled={!role || processing}
                        >
                            {processing && <Spinner />}
                            Davom etish
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

OnboardingRole.layout = (page: ReactNode) => (
    <OnboardingShell
        step={[1, 2]}
        title="Rolingizni tanlang"
        description="Rol huquqlaringizni belgilaydi. Keyinchalik litsenziya olsangiz, advokat maqomiga o'tishingiz mumkin."
    >
        {page}
    </OnboardingShell>
);

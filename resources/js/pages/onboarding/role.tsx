import { Form, Head } from '@inertiajs/react';
import { Briefcase, Scale } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { OnboardingShell } from '@/components/onboarding/onboarding-shell';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/onboarding/role';

type Role = 'advocate' | 'lawyer';

const OPTIONS: {
    value: Role;
    title: string;
    subtitle: string;
    icon: typeof Scale;
    points: string[];
}[] = [
    {
        value: 'advocate',
        title: 'Advokat',
        subtitle: 'Litsenziyaga ega',
        icon: Scale,
        points: [
            "Davlat idoralariga so'rovlarni imzolaydi va yuboradi",
            'Ishlar, huquqiy asoslar va jamoani boshqaradi',
            'Litsenziya advokatlar reestrida tekshiriladi',
        ],
    },
    {
        value: 'lawyer',
        title: 'Yurist',
        subtitle: 'Litsenziyasiz',
        icon: Briefcase,
        points: [
            "So'rov loyihalarini tayyorlaydi",
            'Advokat taklifi bilan uning jamoasida ishlaydi',
            "Javoblarni faqat advokat ruxsati va sir saqlash majburiyatidan so'ng ko'radi",
        ],
    },
];

export default function OnboardingRole({ name }: { name: string }) {
    const [role, setRole] = useState<Role | null>(null);

    return (
        <>
            <Head title="Rolni tanlash" />

            <Form {...store.form()} className="space-y-6">
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="role" value={role ?? ''} />

                        <p className="text-sm">
                            Assalomu alaykum, <strong>{name}</strong>. Platformada
                            qanday maqomda ishlaysiz?
                        </p>

                        <div className="grid gap-4 md:grid-cols-2" role="radiogroup">
                            {OPTIONS.map((o) => (
                                <button
                                    key={o.value}
                                    type="button"
                                    role="radio"
                                    aria-checked={role === o.value}
                                    onClick={() => setRole(o.value)}
                                    className={cn(
                                        'flex flex-col gap-3 rounded-lg border p-5 text-left transition-colors hover:border-primary/60',
                                        role === o.value &&
                                            'border-primary ring-2 ring-primary/20',
                                    )}
                                    data-test={`role-${o.value}`}
                                >
                                    <div className="flex items-center gap-3">
                                        <o.icon className="size-5 text-primary" />
                                        <div>
                                            <div className="font-semibold">
                                                {o.title}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {o.subtitle}
                                            </div>
                                        </div>
                                    </div>
                                    <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                        {o.points.map((p) => (
                                            <li key={p}>{p}</li>
                                        ))}
                                    </ul>
                                </button>
                            ))}
                        </div>

                        <div className="rounded-md bg-muted p-4 text-xs leading-relaxed text-muted-foreground">
                            <strong className="text-foreground">Nima uchun farq bor?</strong>{' '}
                            “Advokatura to‘g‘risida”gi Qonunga muvofiq advokatlik
                            faoliyatini faqat litsenziyaga ega advokat amalga
                            oshiradi. Shu sababli advokat so‘rovini faqat amaldagi
                            litsenziyaga ega advokat imzolaydi va idoraga
                            yuboradi. Yurist advokatga yordamchi sifatida loyiha
                            tayyorlaydi, lekin imzo va yuborish huquqiga ega emas.
                        </div>

                        <InputError message={errors.role} />

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
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
        step="1-qadam / 2"
        title="Rolingizni tanlang"
        description="Rol huquqlaringizni belgilaydi. Keyinchalik litsenziya olsangiz, advokat maqomiga o‘tishingiz mumkin."
    >
        {page}
    </OnboardingShell>
);

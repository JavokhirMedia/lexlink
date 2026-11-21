import { Head, Link, usePage } from "@inertiajs/react";
import {
    ArrowRight,
    Banknote,
    Building2,
    Check,
    Clock3,
    Eye,
    FileLock2,
    Gavel,
    Landmark,
    Lightbulb,
    MailX,
    Plane,
    ScrollText,
    ShieldCheck,
    Timer,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { LegalBasisChip } from "@/components/brand/legal-basis-chip";
import { formatMoney, Money } from "@/components/brand/money";
import { RegistryChip } from "@/components/brand/registry-chip";
import { GirihPattern } from "@/components/landing/girih-pattern";
import { Container, LandingSection } from "@/components/landing/landing-section";
import { ResponsePreview } from "@/components/landing/response-preview";
import { AccessBadge } from "@/components/legal/access-badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { legalMap } from "@/routes";
import type { LegalMatrix } from "@/types/legal-map";

const PROBLEMS: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: MailX,
        title: "Qog'oz so'rovnomalar",
        text: "Advokat har bir idoraga alohida xat yozadi, muhrlaydi va kuryer yoki pochta orqali yuboradi.",
    },
    {
        icon: Timer,
        title: "Noma'lum kutish",
        text: "Javob qachon kelishi noma'lum. Sud majlisi yaqinlashadi, dalillar esa hali yo'lda.",
    },
    {
        icon: Eye,
        title: "Nazoratsiz kirish",
        text: "Kim, qaysi asos bilan, kimning ma'lumotini so'ragani hech qayerda yagona tartibda qayd etilmaydi.",
    },
];

const STEPS: { title: string; text: string }[] = [
    {
        title: "So'rov loyihasi",
        text: "Ish ichida subyekt, so'rov turi va maqsad ko'rsatiladi. Yurist loyihani tayyorlab qo'yishi mumkin.",
    },
    {
        title: "Huquqiy asos tekshiruvi",
        text: "Sud qarori yoki advokat orderi reyestr bilan solishtiriladi: subyekt, muddat va ruxsat etilgan turlar.",
    },
    {
        title: "Imzo va yuborish",
        text: "Faol litsenziyali advokat elektron imzo bilan tasdiqlaydi va so'rov tegishli idoraga yuboriladi.",
    },
    {
        title: "Javob va audit",
        text: "Javob bitta oynada, shifrlangan holda. Har bir ko'rish va yuklab olish jurnalga yoziladi.",
    },
];

const GUARANTEES: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: ShieldCheck,
        title: "Asos bo'lmasa, so'rov yo'q",
        text: "Tasdiqlangan, muddati o'tmagan va subyektga mos asos bo'lmasa, so'rov yuborilmaydi. Soliq siri faqat sud qarori bilan.",
    },
    {
        icon: FileLock2,
        title: "Shifrlash",
        text: "JShShIR va javob mazmuni bazada shifrlangan. Ro'yxatlarda faqat maskalangan identifikator ko'rinadi.",
    },
    {
        icon: Clock3,
        title: "30 kunlik saqlash",
        text: "To'liq javob 30 kundan so'ng avtomatik o'chiriladi. Qisqa xulosa va audit yozuvlari qoladi.",
    },
    {
        icon: Eye,
        title: "Har bir ko'rish auditda",
        text: "Ko'rish, PDF yuklab olish, imzo va yuborish o'zgarmas jurnalga yoziladi. Yurist javobni faqat advokat ruxsati bilan ko'radi.",
    },
    {
        icon: Lightbulb,
        title: "AI maslahat bermaydi",
        text: "Yordamchi F.I.Sh. va JShShIRni yashirib ishlaydi, har bir natija belgilanadi. Yakuniy qaror advokat zimmasida.",
    },
];

/** Same values as database/seeders/PlansSeeder.php. */
const PLANS: {
    name: string;
    audience: string;
    price: number;
    quota?: string;
    features: string[];
    featured?: boolean;
}[] = [
    {
        name: "Start",
        audience: "Sinab ko'rish uchun",
        price: 0,
        features: [
            "Ishlar va hujjatlarni yuritish",
            "Huquqiy asosni tekshirish",
            "3 ta AI tahlil",
            "Audit jurnali",
        ],
    },
    {
        name: "Advokat",
        audience: "Yakka tartibdagi advokatlar",
        price: 149_000,
        quota: `Oyiga 10 ta so'rov, qo'shimchasi ${formatMoney(15_000)}`,
        featured: true,
        features: [
            "Yuristlarni jamoaga qo'shish",
            "AI tahlil va da'vo arizasi loyihasi",
            "PDF javoblar va audit jurnali",
        ],
    },
    {
        name: "Firma",
        audience: "Advokatlik firmalari va byurolari",
        price: 590_000,
        quota: `Oyiga 60 ta so'rov, qo'shimchasi ${formatMoney(10_000)}`,
        features: [
            "Cheksiz jamoa a'zolari",
            "Kengaytirilgan AI tahlil",
            "Ustuvor texnik yordam",
        ],
    },
];

const DOMAIN_ICONS: Record<string, LucideIcon> = {
    court: Gavel,
    tax: Landmark,
    property: Building2,
    bank: Banknote,
    border: Plane,
};

function Hero({ ctaHref, ctaLabel }: { ctaHref: string; ctaLabel: string }) {
    return (
        <section className="relative isolate overflow-hidden border-b border-hairline bg-page">
            {/* Girih lattice, limited to the hero and faded away from the headline */}
            <GirihPattern className="absolute inset-0 -z-10 size-full [mask-image:linear-gradient(to_bottom,#000_55%,transparent)] lg:[mask-image:linear-gradient(to_right,transparent_30%,#000_60%)]" />
            <Container className="grid items-center gap-12 py-12 lg:min-h-[min(calc(100svh-108px),760px)] lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:gap-16 lg:py-20">
                <div className="flex flex-col gap-6">
                    <h1 className="text-[36px] leading-[1.08] font-semibold text-ink sm:text-[48px] xl:text-[56px]">
                        Advokat so'rovi — huquqiy asos bilan, bitta oynada
                    </h1>
                    <p className="max-w-[36rem] text-lg text-ink-secondary sm:text-xl sm:leading-[30px]">
                        Sud ishlari, daromad, kadastr va transport bo'yicha
                        davlat idoralariga advokat orderi yoki sud qarori
                        asosida so'rov yuboring. Javob shifrlangan holda
                        keladi, har bir ko'rish auditda qayd etiladi.
                    </p>
                    <div className="flex flex-wrap items-center gap-3 pt-2">
                        <Button asChild size="lg">
                            <Link href={ctaHref}>
                                {ctaLabel}
                                <ArrowRight aria-hidden="true" strokeWidth={1.5} />
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href={legalMap()}>Huquqiy xarita</Link>
                        </Button>
                    </div>
                </div>
                <ResponsePreview />
            </Container>
        </section>
    );
}

function Problems() {
    return (
        <LandingSection
            id="muammo"
            tone="surface"
            title="Advokat so'rovi bugun qog'ozda yashaydi"
            description="Qonun advokatga ma'lumot so'rash huquqini beradi. Amalda esa bu huquq xat, muhr va kutish bilan cheklangan."
        >
            <div className="grid gap-8 md:grid-cols-3 md:gap-10">
                {PROBLEMS.map((p) => (
                    <div
                        key={p.title}
                        className="flex flex-col gap-2 border-t border-hairline pt-6"
                    >
                        <h3 className="flex items-center gap-2.5 text-lg font-semibold text-ink">
                            <p.icon
                                aria-hidden="true"
                                strokeWidth={1.5}
                                className="size-[18px] shrink-0 text-ink-secondary"
                            />
                            {p.title}
                        </h3>
                        <p className="text-base text-ink-secondary">{p.text}</p>
                    </div>
                ))}
            </div>
        </LandingSection>
    );
}

function Steps() {
    return (
        <LandingSection
            id="jarayon"
            title="To'rt qadam: loyihadan javobgacha"
            description="Har bir qadam qonuniy tartibni takrorlaydi, faqat qog'ozsiz va kuzatiladigan shaklda."
        >
            <ol className="grid gap-8 md:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                {STEPS.map((s, i) => (
                    <li key={s.title} className="flex flex-col gap-3">
                        <div className="flex items-center gap-3">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-md border border-hairline bg-surface font-mono text-sm text-ink tabular-nums">
                                {i + 1}
                            </span>
                            {i < STEPS.length - 1 && (
                                <span
                                    aria-hidden="true"
                                    className="hidden h-px flex-1 bg-hairline lg:block"
                                />
                            )}
                        </div>
                        <h3 className="text-lg font-semibold text-ink">{s.title}</h3>
                        <p className="text-base text-ink-secondary">{s.text}</p>
                    </li>
                ))}
            </ol>
        </LandingSection>
    );
}

/** "Advokat nimalar ola oladi" — 5 domain tiles from App\Support\LegalMatrix. */
function LegalMapTeaser({ matrix }: { matrix: LegalMatrix }) {
    return (
        <LandingSection
            id="huquqiy-xarita"
            tone="surface"
            title="Advokat nimalar ola oladi"
            description="Order bilan olinadigan ma'lumotlar va faqat sud qarori bilan beriladigan sir rejimidagi ma'lumotlar, sohalar bo'yicha."
            actions={
                <Link
                    href={legalMap()}
                    className="focus-ring inline-flex items-center gap-2 rounded-sm text-base font-medium text-brand hover:underline hover:underline-offset-4"
                >
                    To'liq huquqiy xarita
                    <ArrowRight aria-hidden="true" strokeWidth={1.5} className="size-[18px]" />
                </Link>
            }
        >
            <div className="flex flex-col gap-4">
                <div className="grid gap-px overflow-hidden rounded-lg border border-hairline bg-hairline sm:grid-cols-2 lg:grid-cols-5">
                    {matrix.domains.map((d, i) => {
                        const Icon = DOMAIN_ICONS[d.key] ?? ScrollText;

                        return (
                            <Link
                                key={d.key}
                                href={`${legalMap().url}#soha-${d.key}`}
                                className={cn(
                                    "group focus-ring flex flex-col gap-4 bg-surface p-6 transition-colors duration-150 hover:bg-page",
                                    i === matrix.domains.length - 1 &&
                                        "sm:col-span-2 lg:col-span-1",
                                )}
                            >
                                <h3 className="flex items-center gap-2.5 text-base font-semibold text-ink group-hover:text-brand">
                                    <Icon
                                        aria-hidden="true"
                                        strokeWidth={1.5}
                                        className="size-[18px] shrink-0 text-ink-secondary"
                                    />
                                    {d.title}
                                </h3>
                                <ul className="flex flex-col gap-4">
                                    {d.types.map((t) => (
                                        <li key={t.value} className="flex flex-col gap-1.5">
                                            <span className="text-sm text-ink">{t.label}</span>
                                            <AccessBadge access={t.access} size="sm" />
                                        </li>
                                    ))}
                                </ul>
                            </Link>
                        );
                    })}
                </div>
                <p className="text-meta text-ink-secondary">
                    Order bilan: Advokatura to'g'risidagi Qonun, 7¹-modda. Sud
                    qarori bilan: soliq siri, bank siri va maxsus toifadagi
                    ma'lumotlar.
                </p>
            </div>
        </LandingSection>
    );
}

function Guarantees() {
    return (
        <section id="kafolatlar" aria-labelledby="kafolatlar-title" className="scroll-mt-header bg-page">
            <Container className="grid gap-10 py-16 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-16 lg:py-24">
                <div className="flex flex-col gap-6 lg:sticky lg:top-[calc(var(--spacing-header)+48px)] lg:self-start">
                    <div className="flex flex-col gap-3">
                        <h2
                            id="kafolatlar-title"
                            className="text-[26px] leading-[34px] font-semibold text-ink lg:text-[32px] lg:leading-10"
                        >
                            Qoidalar interfeysda emas, tizimning o'zida
                        </h2>
                        <p className="text-lg text-ink-secondary">
                            Har bir cheklov serverda tekshiriladi. Tugmani
                            chetlab o'tib bo'lmaydi.
                        </p>
                    </div>
                    <div className="flex flex-col gap-3 rounded-lg border border-hairline bg-surface p-6 shadow-xs">
                        <span className="text-sm text-ink-secondary">
                            Asos tekshiruvi natijasi
                        </span>
                        <div className="flex flex-col gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <LegalBasisChip state="verified" />
                                <RegistryChip value="2-1001-2611/0457" kind="decision" />
                            </div>
                            <LegalBasisChip state="blocked" />
                        </div>
                    </div>
                </div>
                <ul className="flex flex-col">
                    {GUARANTEES.map((g) => (
                        <li
                            key={g.title}
                            className="grid grid-cols-[18px_minmax(0,1fr)] gap-x-3 gap-y-1 border-t border-hairline py-6 first:border-t-0 first:pt-0 last:pb-0"
                        >
                            <g.icon
                                aria-hidden="true"
                                strokeWidth={1.5}
                                className="mt-1 size-[18px] text-ink-secondary"
                            />
                            <h3 className="text-lg font-semibold text-ink">{g.title}</h3>
                            <p className="col-start-2 text-base text-ink-secondary">
                                {g.text}
                            </p>
                        </li>
                    ))}
                </ul>
            </Container>
        </section>
    );
}

function Plans() {
    return (
        <LandingSection
            id="tariflar"
            tone="surface"
            title="Javob hech qachon pullik devor ortida emas"
            description="Javobni ko'rish uchun pul olinmaydi. To'lov so'rov yuborilganda olinadi, idora rad etsa yoki texnik xato bo'lsa mablag' qaytariladi."
        >
            <div className="flex flex-col gap-4">
                <div className="grid gap-4 md:grid-cols-3">
                    {PLANS.map((plan) => (
                        <div
                            key={plan.name}
                            className={cn(
                                "flex flex-col gap-6 rounded-lg border bg-surface p-6",
                                plan.featured
                                    ? "border-brand shadow-xs"
                                    : "border-hairline",
                            )}
                        >
                            <div className="flex flex-col gap-1">
                                <h3 className="text-xl font-semibold text-ink">
                                    {plan.name}
                                </h3>
                                <span className="text-sm text-ink-secondary">
                                    {plan.audience}
                                </span>
                            </div>
                            <div className="flex flex-col gap-1">
                                <div className="flex items-baseline gap-2">
                                    {plan.price === 0 ? (
                                        <span className="text-[32px] leading-10 font-semibold text-ink">
                                            Bepul
                                        </span>
                                    ) : (
                                        <>
                                            <Money
                                                amount={plan.price}
                                                currency={false}
                                                className="text-[32px] leading-10 font-semibold text-ink"
                                            />
                                            <span className="text-base text-ink-secondary">
                                                so'm / oy
                                            </span>
                                        </>
                                    )}
                                </div>
                                <span className="text-sm text-ink-secondary">
                                    {plan.quota ?? "Idoraviy so'rovlarsiz"}
                                </span>
                            </div>
                            <ul className="flex flex-col gap-3 border-t border-hairline pt-6">
                                {plan.features.map((f) => (
                                    <li key={f} className="flex items-start gap-2.5 text-base text-ink">
                                        <Check
                                            aria-hidden="true"
                                            strokeWidth={1.5}
                                            className="mt-[3px] size-[18px] shrink-0 text-ink-secondary"
                                        />
                                        {f}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
                <p className="text-meta text-ink-secondary">
                    To'lov Click yoki Payme orqali, demo rejimda simulyatsiya
                    qilinadi
                </p>
            </div>
        </LandingSection>
    );
}

function FinalCta({ ctaHref, ctaLabel }: { ctaHref: string; ctaLabel: string }) {
    return (
        <section className="bg-brand-soft">
            <Container className="flex flex-col items-start justify-between gap-6 py-12 sm:flex-row sm:items-center lg:py-16">
                <div className="flex flex-col gap-2">
                    <h2 className="text-2xl font-semibold text-ink">
                        Birinchi so'rovni bugun yuboring
                    </h2>
                    <p className="text-base text-ink-secondary">
                        OneID orqali kiring, litsenziyangiz reyestrdan
                        avtomatik tekshiriladi
                    </p>
                </div>
                <Button asChild size="lg" className="shrink-0">
                    <Link href={ctaHref}>
                        {ctaLabel}
                        <ArrowRight aria-hidden="true" strokeWidth={1.5} />
                    </Link>
                </Button>
            </Container>
        </section>
    );
}

export default function Welcome({ matrix }: { matrix?: LegalMatrix }) {
    const { auth } = usePage().props;
    const loggedIn = Boolean(auth?.user);
    const ctaHref = loggedIn ? "/dashboard" : "/login";
    const ctaLabel = loggedIn ? "Kabinetga o'tish" : "OneID orqali kirish";

    return (
        <>
            <Head title="Advokat so'rovlari platformasi" />
            <Hero ctaHref={ctaHref} ctaLabel={ctaLabel} />
            <Problems />
            <Steps />
            {matrix && <LegalMapTeaser matrix={matrix} />}
            <Guarantees />
            <Plans />
            <FinalCta ctaHref={ctaHref} ctaLabel={ctaLabel} />
        </>
    );
}

Welcome.layout = {
    container: false,
    navLinks: [
        { label: "Muammo", href: "#muammo" },
        { label: "Qanday ishlaydi", href: "#jarayon" },
        { label: "Huquqiy xarita", href: "#huquqiy-xarita" },
        { label: "Kafolatlar", href: "#kafolatlar" },
        { label: "Tariflar", href: "#tariflar" },
    ],
};

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    Building2,
    Car,
    Check,
    Clock3,
    Eye,
    FileLock2,
    FileText,
    Gavel,
    Lightbulb,
    MailX,
    PenLine,
    ScanSearch,
    ShieldCheck,
    Timer,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { DemoBadge } from '@/components/brand/demo-badge';
import { LegalBasisChip } from '@/components/brand/legal-basis-chip';
import { Logo, LogoMark } from '@/components/brand/logo';
import { MaskedPinfl } from '@/components/brand/masked-pinfl';
import { Money } from '@/components/brand/money';
import { StatusBadge } from '@/components/brand/status-badge';
import { cn } from '@/lib/utils';

const DATA_TYPES: { icon: LucideIcon; label: string }[] = [
    { icon: Gavel, label: 'Sud ishlari' },
    { icon: Banknote, label: 'Daromad va ish haqi' },
    { icon: Building2, label: "Ko'chmas mulk (kadastr)" },
    { icon: Car, label: 'Transport vositalari' },
];

const PROBLEMS: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: MailX,
        title: "Qog'oz so'rovnomalar",
        text: 'Advokat har bir idoraga alohida xat yozadi, muhrlaydi va kuryer yoki pochta orqali yuboradi.',
    },
    {
        icon: Timer,
        title: 'Haftalab kutish',
        text: "Javob kelish muddati noma'lum. Sud majlisi yaqinlashadi, dalillar esa hali yo'lda.",
    },
    {
        icon: Eye,
        title: 'Nazoratsiz kirish',
        text: "Kim, qaysi asos bilan, kimning ma'lumotini so'ragani hech qayerda yagona tartibda qayd etilmaydi.",
    },
];

const STEPS: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: FileText,
        title: "So'rov loyihasi",
        text: "Ish ichida subyekt, so'rov turi va maqsad ko'rsatiladi. Yurist loyihani tayyorlab qo'yishi mumkin.",
    },
    {
        icon: ScanSearch,
        title: 'Huquqiy asos tekshiruvi',
        text: 'Sud qarori yoki advokat orderi reyestr bilan solishtiriladi: subyekt, muddat va ruxsat etilgan turlar.',
    },
    {
        icon: PenLine,
        title: 'Imzo va yuborish',
        text: "Faol litsenziyali advokat elektron imzo bilan tasdiqlaydi. So'rov idoraga darhol yetkaziladi.",
    },
    {
        icon: ShieldCheck,
        title: 'Javob va audit',
        text: "Javob bitta oynada, shifrlangan holda. Har bir ko'rish va yuklab olish jurnalga yoziladi.",
    },
];

const GUARANTEES: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: ShieldCheck,
        title: "Asos bo'lmasa — so'rov yo'q",
        text: "Tasdiqlangan, muddati o'tmagan va subyektga mos asos bo'lmasa, yuborish tugmasi ishlamaydi. Soliq siri faqat sud qarori bilan.",
    },
    {
        icon: FileLock2,
        title: 'Shifrlash',
        text: "JShShIR va javob mazmuni bazada shifrlangan. Ro'yxatlarda faqat maskalangan identifikator ko'rinadi.",
    },
    {
        icon: Clock3,
        title: '30 kunlik saqlash',
        text: "To'liq javob 30 kundan so'ng avtomatik o'chiriladi. Qisqa xulosa va audit yozuvlari qoladi.",
    },
    {
        icon: Eye,
        title: "Har bir ko'rish auditda",
        text: "Ko'rish, PDF yuklab olish, imzo va yuborish o'zgarmas jurnalga yoziladi. Yurist javobni faqat advokat ruxsati bilan ko'radi.",
    },
    {
        icon: Lightbulb,
        title: 'AI — maslahat emas',
        text: 'Yordamchi tahlil F.I.Sh. va JShShIRni yashirib ishlaydi, har bir natija belgilanadi. Yakuniy qaror advokat zimmasida.',
    },
];

const PLANS: {
    name: string;
    audience: string;
    price: number;
    features: string[];
    featured?: boolean;
}[] = [
    {
        name: 'Start',
        audience: 'Tanishish uchun',
        price: 0,
        features: [
            "Ishlar va so'rov loyihalari",
            "Har bir so'rov uchun alohida to'lov",
            'Audit jurnali',
        ],
    },
    {
        name: 'Advokat',
        audience: 'Yakka amaliyot',
        price: 149000,
        featured: true,
        features: [
            "Oylik so'rovlar paketi",
            'Huquqiy yordamchi (AI)',
            "Javoblarni PDF ko'rinishida yuklash",
        ],
    },
    {
        name: 'Firma',
        audience: 'Advokatlik byurosi',
        price: 590000,
        features: [
            'Advokat va yuristlar jamoasi',
            "Ish bo'yicha kirish ruxsatlari",
            'Umumiy audit va hisobotlar',
        ],
    },
];

function Container({
    className,
    children,
}: {
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={cn('mx-auto w-full max-w-6xl px-5 sm:px-8', className)}>
            {children}
        </div>
    );
}

function SectionTitle({
    kicker,
    title,
    text,
    inverted = false,
}: {
    kicker: string;
    title: string;
    text?: string;
    inverted?: boolean;
}) {
    return (
        <div className="max-w-2xl">
            <div
                className={cn(
                    'text-sm font-medium',
                    inverted ? 'text-sidebar-primary' : 'text-gold',
                )}
            >
                {kicker}
            </div>
            <h2
                className={cn(
                    'mt-2 font-display text-[30px] leading-tight font-semibold sm:text-[34px]',
                    inverted ? 'text-white' : 'text-foreground',
                )}
            >
                {title}
            </h2>
            {text && (
                <p
                    className={cn(
                        'mt-3 text-[15px] leading-relaxed',
                        inverted ? 'text-white/70' : 'text-muted-foreground',
                    )}
                >
                    {text}
                </p>
            )}
        </div>
    );
}

/** Product preview: a request card as it appears inside LexLink (fictional data). */
function RequestPreview() {
    const timeline = [
        { label: 'Loyiha tuzildi', time: '09:12', done: true },
        { label: 'Asos tasdiqlandi', time: '09:12', done: true },
        { label: 'Imzolandi va yuborildi', time: '09:14', done: true },
        { label: 'Javob olindi', time: '09:21', done: true },
    ];

    return (
        <div className="relative">
            <div className="rounded-lg border bg-card">
                <div className="flex items-center justify-between gap-3 border-b px-5 py-3.5">
                    <div>
                        <div className="text-xs text-muted-foreground">
                            So'rov № 2026-00418
                        </div>
                        <div className="mt-0.5 text-sm font-semibold">
                            Daromad to'g'risida ma'lumot
                        </div>
                    </div>
                    <StatusBadge status="completed" />
                </div>

                <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2.5 px-5 py-4 text-sm">
                    <dt className="text-muted-foreground">Ish</dt>
                    <dd className="truncate">Aliment undirish to'g'risida</dd>
                    <dt className="text-muted-foreground">Subyekt</dt>
                    <dd>
                        <MaskedPinfl value="3***********07" />
                    </dd>
                    <dt className="text-muted-foreground">Idora</dt>
                    <dd>Soliq qo'mitasi</dd>
                    <dt className="text-muted-foreground">Asos</dt>
                    <dd>
                        <LegalBasisChip
                            state="verified"
                            size="sm"
                            detail="Sud qarori 2-1104/2026"
                        />
                    </dd>
                </dl>

                <ol className="border-t px-5 py-4">
                    {timeline.map((step, i) => (
                        <li
                            key={step.label}
                            className="relative flex items-center gap-3 pb-3 last:pb-0"
                        >
                            {i < timeline.length - 1 && (
                                <span
                                    aria-hidden="true"
                                    className="absolute top-5 left-[9px] h-[calc(100%-12px)] w-px bg-success-border"
                                />
                            )}
                            <span className="relative flex size-[19px] items-center justify-center rounded-full border border-success-border bg-success-soft text-success">
                                <Check className="size-3" strokeWidth={3} />
                            </span>
                            <span className="flex-1 text-sm">{step.label}</span>
                            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                {step.time}
                            </span>
                        </li>
                    ))}
                </ol>

                <div className="flex items-center justify-between gap-3 border-t bg-muted/60 px-5 py-3 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <Eye className="size-3.5" />3 ta ko'rish auditda qayd
                        etildi
                    </span>
                    <Money amount={25000} className="text-foreground" />
                </div>
            </div>

            <div className="mt-3 flex items-center justify-between gap-3 rounded-lg border border-danger-border bg-card px-4 py-3 sm:absolute sm:-bottom-8 sm:-left-10 sm:mt-0 sm:w-[300px]">
                <div className="min-w-0">
                    <div className="text-xs text-muted-foreground">
                        Ko'chmas mulk so'rovi
                    </div>
                    <LegalBasisChip
                        state="blocked"
                        size="sm"
                        className="mt-1"
                    />
                </div>
                <span className="shrink-0 font-mono text-[11px] text-danger">
                    422
                </span>
            </div>
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;
    const loggedIn = Boolean(auth?.user);
    const ctaHref = loggedIn ? '/dashboard' : '/login';
    const ctaLabel = loggedIn ? "Kabinetga o'tish" : 'OneID orqali kirish';

    return (
        <>
            <Head title="Advokat so'rovlari platformasi" />
            <div className="min-h-screen bg-background text-foreground">
                {/* Top bar */}
                <header className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur-sm supports-[backdrop-filter]:bg-background/85">
                    <Container className="flex h-16 items-center gap-6">
                        <Link href="/" aria-label="LexLink bosh sahifa">
                            <Logo subtitle={false} />
                        </Link>
                        <nav className="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                            <a href="#muammo" className="hover:text-foreground">
                                Muammo
                            </a>
                            <a
                                href="#jarayon"
                                className="hover:text-foreground"
                            >
                                Qanday ishlaydi
                            </a>
                            <a
                                href="#kafolatlar"
                                className="hover:text-foreground"
                            >
                                Kafolatlar
                            </a>
                            <a
                                href="#tariflar"
                                className="hover:text-foreground"
                            >
                                Tariflar
                            </a>
                        </nav>
                        <div className="ml-auto flex items-center gap-3">
                            <DemoBadge className="hidden lg:inline-flex" />
                            <Link
                                href={ctaHref}
                                className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                {ctaLabel}
                            </Link>
                        </div>
                    </Container>
                </header>

                <main>
                    {/* Hero */}
                    <section className="border-b">
                        <Container className="grid gap-14 py-16 sm:py-20 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:items-center lg:gap-16 lg:py-24">
                            <div>
                                <div className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                    <span className="size-1.5 rounded-full bg-gold" />
                                    Advokatlar va yuristlar uchun
                                </div>
                                <h1 className="mt-5 font-display text-[40px] leading-[1.08] font-semibold tracking-tight text-foreground sm:text-[52px]">
                                    Davlat ma'lumotlariga huquqiy asos bilan —{' '}
                                    <span className="text-primary">
                                        bitta oynada
                                    </span>
                                </h1>
                                <p className="mt-6 max-w-xl text-[17px] leading-relaxed text-muted-foreground">
                                    Sud ishlari, daromad, kadastr va transport
                                    bo'yicha so'rovlarni qonuniy asos bilan
                                    yuboring. Javob daqiqalarda keladi,
                                    shifrlangan holda saqlanadi, har bir ko'rish
                                    esa auditda.
                                </p>
                                <div className="mt-8 flex flex-wrap items-center gap-3">
                                    <Link
                                        href={ctaHref}
                                        className="inline-flex h-11 items-center gap-2 rounded-md bg-primary px-5 text-[15px] font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                    >
                                        {ctaLabel}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                    <a
                                        href="#jarayon"
                                        className="inline-flex h-11 items-center rounded-md border px-5 text-[15px] font-medium transition-colors hover:bg-accent"
                                    >
                                        Qanday ishlaydi
                                    </a>
                                </div>
                                <ul className="mt-10 grid max-w-xl grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4 sm:gap-x-4">
                                    {DATA_TYPES.map((t) => (
                                        <li
                                            key={t.label}
                                            className="flex items-start gap-2 text-foreground/80"
                                        >
                                            <t.icon className="mt-0.5 size-4 shrink-0 text-primary" />
                                            <span className="leading-snug">
                                                {t.label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="lg:pl-6">
                                <RequestPreview />
                            </div>
                        </Container>
                    </section>

                    {/* Problem */}
                    <section
                        id="muammo"
                        className="scroll-mt-16 border-b bg-muted/50"
                    >
                        <Container className="py-16 sm:py-20">
                            <SectionTitle
                                kicker="Muammo"
                                title="Advokat so'rovi bugun qog'ozda yashaydi"
                                text="Qonun advokatga ma'lumot so'rash huquqini beradi. Amalda esa bu huquq xat, muhr va kutish bilan cheklangan."
                            />
                            <div className="mt-10 grid gap-px overflow-hidden rounded-lg border bg-border md:grid-cols-3">
                                {PROBLEMS.map((p) => (
                                    <div key={p.title} className="bg-card p-6">
                                        <p.icon className="size-5 text-danger" />
                                        <h3 className="mt-4 text-base font-semibold">
                                            {p.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                            {p.text}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </Container>
                    </section>

                    {/* How it works */}
                    <section id="jarayon" className="scroll-mt-16 border-b">
                        <Container className="py-16 sm:py-20">
                            <SectionTitle
                                kicker="Qanday ishlaydi"
                                title="To'rt qadam: loyihadan javobgacha"
                                text="Har bir qadam qonuniy tartibni takrorlaydi — faqat qog'ozsiz va kuzatiladigan shaklda."
                            />
                            <ol className="mt-12 grid gap-8 md:grid-cols-4 md:gap-6">
                                {STEPS.map((s, i) => (
                                    <li key={s.title} className="relative">
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-md border border-primary/25 bg-navy-soft text-primary">
                                                <s.icon className="size-5" />
                                            </span>
                                            {i < STEPS.length - 1 && (
                                                <span
                                                    aria-hidden="true"
                                                    className="hidden h-px flex-1 bg-border md:block"
                                                />
                                            )}
                                        </div>
                                        <div className="mt-4 font-mono text-xs text-muted-foreground">
                                            0{i + 1}
                                        </div>
                                        <h3 className="mt-1 text-base font-semibold">
                                            {s.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                            {s.text}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </Container>
                    </section>

                    {/* Guarantees */}
                    <section
                        id="kafolatlar"
                        className="relative scroll-mt-16 overflow-hidden bg-sidebar text-white"
                    >
                        <LogoMark
                            aria-hidden="true"
                            accent="var(--sidebar-primary)"
                            className="pointer-events-none absolute -top-40 -right-40 size-[520px] text-white/[0.04]"
                        />
                        <Container className="relative py-16 sm:py-20">
                            <SectionTitle
                                inverted
                                kicker="Huquqiy kafolatlar"
                                title="Qoidalar interfeysda emas, tizimning o'zida"
                                text="Har bir cheklov serverda tekshiriladi. Tugmani chetlab o'tib bo'lmaydi."
                            />
                            <div className="mt-12 grid gap-px overflow-hidden rounded-lg border border-white/10 bg-white/10 sm:grid-cols-2 lg:grid-cols-3">
                                {GUARANTEES.map((g) => (
                                    <div
                                        key={g.title}
                                        className="bg-sidebar p-6"
                                    >
                                        <g.icon className="size-5 text-sidebar-primary" />
                                        <h3 className="mt-4 text-base font-semibold text-white">
                                            {g.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-relaxed text-white/65">
                                            {g.text}
                                        </p>
                                    </div>
                                ))}
                                <div className="flex flex-col justify-between bg-sidebar p-6">
                                    <div className="text-sm text-white/65">
                                        Asos tekshiruvi natijasi
                                    </div>
                                    <div className="mt-4 flex flex-col gap-2">
                                        <LegalBasisChip
                                            state="verified"
                                            detail="Sud qarori"
                                        />
                                        <LegalBasisChip state="blocked" />
                                    </div>
                                </div>
                            </div>
                        </Container>
                    </section>

                    {/* Tariffs */}
                    <section id="tariflar" className="scroll-mt-16 border-b">
                        <Container className="py-16 sm:py-20">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <SectionTitle
                                    kicker="Tariflar"
                                    title="Javob hech qachon pullik devor ortida emas"
                                    text="To'lov so'rov yuborilganda olinadi. Idora rad etsa yoki texnik xato bo'lsa — mablag' qaytariladi."
                                />
                            </div>
                            <div className="mt-10 grid gap-4 md:grid-cols-3">
                                {PLANS.map((plan) => (
                                    <div
                                        key={plan.name}
                                        className={cn(
                                            'flex flex-col rounded-lg border bg-card p-6',
                                            plan.featured &&
                                                'border-primary ring-1 ring-primary',
                                        )}
                                    >
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-lg font-semibold">
                                                {plan.name}
                                            </h3>
                                            {plan.featured && (
                                                <span className="rounded-sm bg-navy-soft px-1.5 py-0.5 text-[11px] font-medium text-navy">
                                                    Ko'p tanlanadi
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-1 text-sm text-muted-foreground">
                                            {plan.audience}
                                        </div>
                                        <div className="mt-6 flex items-baseline gap-1.5">
                                            {plan.price === 0 ? (
                                                <span className="text-3xl font-semibold tracking-tight">
                                                    Bepul
                                                </span>
                                            ) : (
                                                <>
                                                    <Money
                                                        amount={plan.price}
                                                        currency={false}
                                                        className="text-3xl font-semibold tracking-tight"
                                                    />
                                                    <span className="text-sm text-muted-foreground">
                                                        so'm / oy
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                        <ul className="mt-6 space-y-2.5 border-t pt-5 text-sm">
                                            {plan.features.map((f) => (
                                                <li
                                                    key={f}
                                                    className="flex items-start gap-2"
                                                >
                                                    <Check className="mt-0.5 size-4 shrink-0 text-success" />
                                                    {f}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-4 text-xs text-muted-foreground">
                                To'lov Click yoki Payme orqali (demo rejimda
                                simulyatsiya qilinadi).
                            </p>
                        </Container>
                    </section>

                    {/* CTA */}
                    <section>
                        <Container className="flex flex-col items-start justify-between gap-6 py-14 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-display text-2xl font-semibold">
                                    Birinchi so'rovni bugun yuboring
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    OneID orqali kiring, litsenziyangiz
                                    avtomatik tekshiriladi.
                                </p>
                            </div>
                            <Link
                                href={ctaHref}
                                className="inline-flex h-11 shrink-0 items-center gap-2 rounded-md bg-primary px-5 text-[15px] font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                {ctaLabel}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Container>
                    </section>
                </main>

                <footer className="border-t bg-muted/50">
                    <Container className="flex flex-col gap-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <LogoMark
                                className="size-6 text-primary"
                                accent="var(--gold)"
                            />
                            <span>LexLink · Lex Hackathon 2026 · demo</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <DemoBadge />
                            <span>
                                Platformadagi barcha shaxslar va raqamlar
                                uydirma.
                            </span>
                        </div>
                    </Container>
                </footer>
            </div>
        </>
    );
}

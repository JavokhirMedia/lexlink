import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/brand/page-header';
import { RegistryChip } from '@/components/brand/registry-chip';
import { Section } from '@/components/brand/section';
import { AccessBadge } from '@/components/legal/access-badge';
import { legalMap } from '@/routes';

const STEPS = [
    {
        title: 'OneID orqali kiring',
        text: "Shaxsingiz yagona identifikatsiya tizimi orqali tasdiqlanadi. Advokatlar uchun litsenziya holati reyestrdan avtomatik tekshiriladi.",
    },
    {
        title: 'Ish oching va huquqiy asosni biriktiring',
        text: "Ishga advokat orderi yoki sud qarori (ajrimi) raqamini kiriting. Asos reyestrda tekshiriladi: subyekt, muddat va so'raladigan ma'lumot turi mos kelishi kerak.",
    },
    {
        title: "So'rovni tayyorlang va imzolang",
        text: "Ma'lumot turini tanlang. Asos bu turni qamramasa, so'rov yuborilmaydi. Asos mos bo'lsa, so'rov elektron raqamli imzo bilan imzolanib, tegishli idoraga yuboriladi.",
    },
    {
        title: 'Javobni xavfsiz oling',
        text: "Idora javobi shifrlangan holda saqlanadi va muddati tugagach o'chiriladi. Har bir ochish va ko'rish audit jurnaliga yoziladi.",
    },
];

const DEMO_PEOPLE: { name: string; role: string; pinfl: string; note: string }[] = [
    {
        name: 'Rustamova Dilnoza Akmalovna',
        role: 'Advokat, litsenziya faol',
        pinfl: '41505880261034',
        note: "Asosiy ssenariy: aliment undirish ishi, so'rovlar va javoblar",
    },
    {
        name: 'Toshpulatov Jasur Baxtiyorovich',
        role: 'Yurist (advokat jamoasida)',
        pinfl: '32208950261047',
        note: "So'rov tayyorlaydi, imzolash huquqi yo'q",
    },
    {
        name: 'Karimova Nilufar Shavkatovna',
        role: 'Mijoz (ishonch bildiruvchi)',
        pinfl: '42303910261025',
        note: "O'z ishi bo'yicha ruxsat berilgan javoblarni ko'radi",
    },
    {
        name: 'Xolmatov Sherzod Ravshanovich',
        role: 'Advokat, litsenziya bekor qilingan',
        pinfl: '30311790261058',
        note: "Imzolash va so'rov yuborish bloklanadi",
    },
];

function maskPinfl(v: string) {
    return v[0] + '*'.repeat(v.length - 3) + v.slice(-2);
}

export default function Help() {
    return (
        <>
            <Head title="Yordam" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-10">
                <PageHeader
                    title="Yordam"
                    description="LexLink qanday ishlashi, qaysi huquqiy asos nimaga yetishi va ma'lumotlaringiz qanday himoyalanishi haqida qisqa qo'llanma"
                />

                <Section className="border-t border-hairline pt-8" title="Qanday ishlaydi">
                    <ol className="flex flex-col gap-6">
                        {STEPS.map((s, i) => (
                            <li key={s.title} className="flex gap-4">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-md border border-hairline bg-surface font-mono text-sm text-ink tabular-nums">
                                    {i + 1}
                                </span>
                                <div className="flex flex-col gap-1 pt-1">
                                    <h3 className="text-lg font-semibold text-ink">
                                        {s.title}
                                    </h3>
                                    <p className="max-w-[72ch] text-base leading-[26px] text-ink-secondary">
                                        {s.text}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </Section>

                <Section
                    className="border-t border-hairline pt-8"
                    title="Huquqiy asos turlari"
                    description="Asos bo'lmasa, so'rov yo'q"
                >
                    <div className="flex flex-col gap-4">
                        <div className="rounded-lg border bg-card p-6 shadow-xs">
                            <div className="flex flex-wrap items-center gap-3">
                                <h3 className="text-lg font-semibold text-ink">
                                    Advokat orderi
                                </h3>
                                <AccessBadge access="order" size="sm" />
                            </div>
                            <p className="mt-2 max-w-[72ch] text-base leading-[26px] text-ink-secondary">
                                Advokat so'rovi (Advokatura to'g'risidagi Qonun,
                                7¹-modda) bilan birga yuboriladi. Sud ishlari,
                                soliq ma'lumotining ochiq qismi, ko'chmas mulk,
                                transport vositalari va yuridik shaxslardagi
                                ulushlar uchun yetarli.
                            </p>
                        </div>
                        <div className="rounded-lg border bg-card p-6 shadow-xs">
                            <div className="flex flex-wrap items-center gap-3">
                                <h3 className="text-lg font-semibold text-ink">
                                    Sud qarori yoki ajrimi
                                </h3>
                                <AccessBadge access="court" size="sm" />
                            </div>
                            <p className="mt-2 max-w-[72ch] text-base leading-[26px] text-ink-secondary">
                                Sir rejimidagi va maxsus toifadagi ma'lumotlar
                                uchun talab qilinadi: ish haqi va daromad (soliq
                                siri), bank hisobvaraqlari (bank siri) va
                                chegaradan o'tish ma'lumotlari. Sud qarori
                                order bilan olinadigan ma'lumotlar uchun ham
                                qabul qilinadi.
                            </p>
                        </div>
                        <p className="max-w-[72ch] text-sm leading-6 text-ink-secondary">
                            Har bir ma'lumot turi bo'yicha to'liq jadval va
                            qonun havolalari{' '}
                            <Link
                                href={legalMap()}
                                className="focus-ring rounded-sm text-brand underline-offset-4 hover:underline"
                            >
                                Huquqiy xarita
                            </Link>{' '}
                            sahifasida
                        </p>
                    </div>
                </Section>

                <Section
                    className="border-t border-hairline pt-8"
                    title="Muddatlar"
                    description="Advokatura to'g'risidagi Qonun, 7¹-modda"
                >
                    <dl className="divide-y divide-hairline overflow-hidden rounded-lg border bg-card shadow-xs">
                        {[
                            ['15 kun', "So'rov olingan kundan boshlab axborot berish muddati"],
                            ['5 kun', "So'ralgan ma'lumot mavjud bo'lmasa, bu haqda xabar berish muddati"],
                            ['3 ish kuni', "Surishtiruv, tergov yoki sud ishi boshlangani haqidagi hujjat ilova qilinganda"],
                        ].map(([term, text]) => (
                            <div
                                key={term}
                                className="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6"
                            >
                                <dt className="w-28 shrink-0 text-base font-semibold text-ink tabular-nums">
                                    {term}
                                </dt>
                                <dd className="text-base leading-[26px] text-ink-secondary">
                                    {text}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Section>

                <Section className="border-t border-hairline pt-8" title="Maxfiylik va AI">
                    <div className="flex max-w-[72ch] flex-col gap-4 text-base leading-[26px] text-ink-secondary">
                        <p>
                            JShShIR va idora javoblari shifrlangan holda
                            saqlanadi. Ekranda JShShIR maskalangan ko'rinishda
                            chiqadi, masalan{' '}
                            <RegistryChip
                                value="3***********12"
                                kind="pinfl"
                                size="sm"
                                className="align-middle"
                            />
                            .
                        </p>
                        <p>
                            Huquqiy yordamchi va javob tahlili uchun serverdan
                            faqat egasizlantirilgan ma'lumot chiqadi: ism,
                            JShShIR, manzil va boshqa identifikatorlar
                            shartli belgilar bilan almashtiriladi. Shundan
                            so'ng ma'lumotni muayyan shaxsga bog'lab bo'lmaydi
                            (Shaxsga doir ma'lumotlar to'g'risidagi Qonun,
                            16-modda). AI natijasi maslahat xarakterida, uni
                            advokat tekshiradi.
                        </p>
                        <p>
                            Har bir ko'rish, yuklab olish va AI so'rovi audit
                            jurnalida kim, qachon va qaysi asosda bajargani
                            bilan qayd etiladi.
                        </p>
                    </div>
                </Section>

                <Section
                    className="border-t border-hairline pt-8"
                    title="Demo ma'lumotlar"
                    description="Bu hakaton demosi: barcha shaxslar, raqamlar va idoralar uydirma"
                >
                    <div className="flex flex-col gap-4">
                        <ul className="divide-y divide-hairline overflow-hidden rounded-lg border bg-card shadow-xs">
                            {DEMO_PEOPLE.map((p) => (
                                <li
                                    key={p.pinfl}
                                    className="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6"
                                >
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-base font-medium text-ink">
                                            {p.name}
                                        </span>
                                        <span className="text-sm text-ink-secondary">
                                            {p.role} · {p.note}
                                        </span>
                                    </div>
                                    <RegistryChip
                                        value={maskPinfl(p.pinfl)}
                                        kind="pinfl"
                                        size="sm"
                                    />
                                </li>
                            ))}
                        </ul>
                        <p className="max-w-[72ch] text-sm leading-6 text-ink-secondary">
                            Kirish sahifasidagi "Demo rejimi" bo'limida shaxsni
                            tanlab, OneID orqali uning nomidan kiring. Demo ishdagi
                            sud qarori raqami:{' '}
                            <RegistryChip
                                value="2-1001-2611/0457"
                                kind="decision"
                                size="sm"
                                className="align-middle"
                            />
                            .
                        </p>
                    </div>
                </Section>
            </div>
        </>
    );
}

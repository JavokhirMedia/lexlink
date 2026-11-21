import { Head, Link } from "@inertiajs/react";
import {
    Banknote,
    Building2,
    Clock3,
    ExternalLink,
    FilePlus2,
    Gavel,
    Landmark,
    Plane,
    Printer,
    ScrollText,
    ShieldOff,
    TriangleAlert,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { PageHeader } from "@/components/brand/page-header";
import { AccessBadge } from "@/components/legal/access-badge";
import { cn } from "@/lib/utils";
import { legalMap } from "@/routes";
import type {
    LegalDomain,
    LegalMatrix,
    LegalMatrixType,
    LegalRef,
} from "@/types/legal-map";

type Props = {
    matrix: LegalMatrix;
    secrecy: Record<string, string>;
    can_create_request: boolean;
};

export const DOMAIN_ICONS: Record<string, LucideIcon> = {
    court: Gavel,
    tax: Landmark,
    property: Building2,
    bank: Banknote,
    border: Plane,
};

/** Hides the app chrome (sidebar + top bar) when printing. */
const PRINT_CSS = `@media print {
  [data-slot="sidebar"], [data-slot="sidebar-inset"] > header { display: none !important; }
  [data-slot="sidebar-inset"] { margin: 0 !important; box-shadow: none !important; }
  body { background: white !important; }
}`;

/** "Soliq siri — faqat sud qarori ..." → "Soliq siri" (access is shown in its own column). */
function regimeShort(text: string | undefined): string {
    if (!text) {
        return "—";
    }

    return text.split(" — ")[0];
}

function refLabel(ref: LegalRef): string {
    return ref.article ? `${ref.law}, ${ref.article}-modda` : ref.law;
}

function RefChip({ refItem }: { refItem: LegalRef }) {
    const classes =
        "inline-flex max-w-full items-center gap-1 rounded-sm border bg-background px-1.5 py-0.5 text-xs leading-snug text-foreground/80";

    if (!refItem.url) {
        return (
            <span title={refItem.note} className={cn(classes, "border-dashed")}>
                {refLabel(refItem)}
            </span>
        );
    }

    return (
        <a
            href={refItem.url}
            target="_blank"
            rel="noopener noreferrer"
            title={refItem.note}
            className={cn(
                classes,
                "transition-colors hover:border-primary/40 hover:text-primary",
            )}
        >
            {refLabel(refItem)}
            <ExternalLink
                aria-hidden="true"
                className="size-3 shrink-0 opacity-60 print:hidden"
            />
        </a>
    );
}

function CreateRequestLink({ type }: { type: string }) {
    return (
        <Link
            href={`/requests/create?type=${encodeURIComponent(type)}`}
            className="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium whitespace-nowrap transition-colors hover:bg-accent print:hidden"
        >
            <FilePlus2 aria-hidden="true" className="size-3.5" />
            So'rov yaratish
        </Link>
    );
}

function deadlineFor(t: LegalMatrixType, days: number): string {
    return t.access === "order" ? `${days} kun` : "Sud ajrimi tartibida";
}

function Mark({ ok }: { ok: boolean }) {
    return ok ? (
        <span className="font-medium text-success">Ha</span>
    ) : (
        <span className="font-medium text-danger">Yo'q</span>
    );
}

function Overview({
    matrix,
    secrecy,
    canCreate,
}: {
    matrix: LegalMatrix;
    secrecy: Record<string, string>;
    canCreate: boolean;
}) {
    return (
        <section aria-labelledby="overview-title">
            <h2
                id="overview-title"
                className="text-xl font-semibold"
            >
                Umumiy jadval
            </h2>
            <div className="mt-3 overflow-x-auto rounded-lg border bg-card print:overflow-visible">
                <table className="w-full min-w-[760px] text-sm print:min-w-0">
                    <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2.5 font-medium">
                                Ma'lumot turi
                            </th>
                            <th className="px-3 py-2.5 font-medium">
                                Advokat orderi
                            </th>
                            <th className="px-3 py-2.5 font-medium">
                                Sud qarori
                            </th>
                            <th className="px-3 py-2.5 font-medium">
                                Sir rejimi
                            </th>
                            <th className="px-3 py-2.5 font-medium">
                                Javob muddati
                            </th>
                            {canCreate && (
                                <th className="px-3 py-2.5 print:hidden">
                                    <span className="sr-only">Amal</span>
                                </th>
                            )}
                        </tr>
                    </thead>
                    {matrix.domains.map((d) => (
                        <tbody key={d.key} className="border-t">
                            <tr className="bg-navy-soft/60">
                                <th
                                    scope="colgroup"
                                    colSpan={canCreate ? 6 : 5}
                                    className="px-4 py-2 text-left text-xs font-semibold text-navy"
                                >
                                    {d.title}
                                </th>
                            </tr>
                            {d.types.map((t) => (
                                <tr key={t.value} className="border-t">
                                    <td className="px-4 py-2.5">
                                        <div className="font-medium">
                                            {t.label}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {t.agency}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center gap-2">
                                            <Mark ok={t.access === "order"} />
                                            <AccessBadge
                                                access={t.access}
                                                size="sm"
                                            />
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <Mark
                                            ok={t.accepted_bases.some(
                                                (b) =>
                                                    b.value ===
                                                    "court_decision",
                                            )}
                                        />
                                    </td>
                                    <td className="px-3 py-2.5 text-xs text-foreground/80">
                                        {regimeShort(secrecy[t.value])}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs whitespace-nowrap tabular-nums">
                                        {deadlineFor(
                                            t,
                                            matrix.deadlines.default_days,
                                        )}
                                    </td>
                                    {canCreate && (
                                        <td className="px-3 py-2.5 text-right print:hidden">
                                            <CreateRequestLink type={t.value} />
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    ))}
                </table>
            </div>
        </section>
    );
}

function DomainSection({
    domain,
    canCreate,
}: {
    domain: LegalDomain;
    canCreate: boolean;
}) {
    const Icon = DOMAIN_ICONS[domain.key] ?? ScrollText;

    return (
        <section
            id={`soha-${domain.key}`}
            className="scroll-mt-20 rounded-lg border bg-card break-inside-avoid"
        >
            <div className="flex items-start gap-3 border-b px-5 py-4">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-md border border-primary/25 bg-navy-soft text-primary">
                    <Icon aria-hidden="true" className="size-[18px]" />
                </span>
                <div className="min-w-0">
                    <h3 className="text-lg leading-tight font-semibold">
                        {domain.title}
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {domain.summary}
                    </p>
                </div>
            </div>
            <div className="divide-y">
                {domain.types.map((t) => (
                    <article key={t.value} className="px-5 py-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <h4 className="text-[15px] font-semibold">
                                    {t.label}
                                </h4>
                                <div className="mt-0.5 text-xs text-muted-foreground">
                                    Manba: {t.agency}
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <AccessBadge access={t.access} />
                                {canCreate && (
                                    <CreateRequestLink type={t.value} />
                                )}
                            </div>
                        </div>
                        <div className="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <div className="text-xs font-medium text-muted-foreground">
                                    Nima olinadi
                                </div>
                                <ul className="mt-1.5 space-y-1 text-sm">
                                    {t.items.map((item) => (
                                        <li key={item} className="flex gap-2">
                                            <span
                                                aria-hidden="true"
                                                className="mt-2 size-1 shrink-0 rounded-full bg-gold"
                                            />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div>
                                <div className="text-xs font-medium text-muted-foreground">
                                    Nega
                                </div>
                                <p className="mt-1.5 text-sm leading-relaxed">
                                    {t.why}
                                </p>
                                <div className="mt-2.5 flex flex-wrap gap-1.5">
                                    {t.refs.map((r, i) => (
                                        <RefChip
                                            key={`${r.law}-${r.article}-${i}`}
                                            refItem={r}
                                        />
                                    ))}
                                </div>
                                {t.verify && (
                                    <p className="mt-2 inline-flex items-center gap-1.5 text-xs text-warning">
                                        <TriangleAlert
                                            aria-hidden="true"
                                            className="size-3.5"
                                        />
                                        Modda raqami lex.uz bilan tekshirilmoqda
                                    </p>
                                )}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

function Deadlines({ d }: { d: LegalMatrix["deadlines"] }) {
    const rows: { value: string; text: string }[] = [
        {
            value: `${d.default_days} kun`,
            text: "So'rov olingan sanadan e'tiboran axborot taqdim etiladi.",
        },
        {
            value: `${d.no_info_notice_days} kun`,
            text: "So'ralgan ma'lumot bo'lmasa, shu muddatda advokatga yozma xabar beriladi.",
        },
        {
            value: `${d.proceedings_work_days} ish kuni`,
            text: "So'rovga surishtiruv, tergov yoki sud ishi boshlanganini tasdiqlovchi hujjat ilova qilingan bo'lsa.",
        },
    ];

    return (
        <section className="rounded-lg border bg-card break-inside-avoid">
            <div className="flex items-center gap-2 border-b px-5 py-3.5">
                <Clock3 aria-hidden="true" className="size-4 text-primary" />
                <h2 className="text-[15px] font-semibold">Javob muddatlari</h2>
                <span className="ml-auto text-xs text-muted-foreground">
                    Advokatura to'g'risidagi Qonun, 7¹-modda
                </span>
            </div>
            <dl className="divide-y">
                {rows.map((r) => (
                    <div
                        key={r.value}
                        className="grid grid-cols-[96px_1fr] gap-4 px-5 py-3 text-sm"
                    >
                        <dt className="text-lg leading-tight font-semibold tabular-nums">
                            {r.value}
                        </dt>
                        <dd className="text-foreground/85">{r.text}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

function OutOfScope({ items }: { items: LegalMatrix["out_of_scope"] }) {
    return (
        <section className="rounded-lg border border-dashed bg-muted/40 break-inside-avoid">
            <div className="flex items-center gap-2 border-b border-dashed px-5 py-3.5">
                <ShieldOff
                    aria-hidden="true"
                    className="size-4 text-muted-foreground"
                />
                <h2 className="text-[15px] font-semibold">
                    Platforma orqali olinmaydi
                </h2>
            </div>
            <ul className="divide-y divide-dashed">
                {items.map((o) => (
                    <li key={o.title} className="px-5 py-3 text-sm">
                        <div className="font-medium">{o.title}</div>
                        <p className="mt-0.5 text-muted-foreground">{o.why}</p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function LegalMapContent({ matrix, secrecy, can_create_request }: Props) {
    const typeCount = matrix.domains.reduce((n, d) => n + d.types.length, 0);

    return (
        <div className="space-y-8">
            <style>{PRINT_CSS}</style>
            <PageHeader
                title="Huquqiy xarita — advokat qaysi ma'lumotni qanday asos bilan oladi"
                description={`${matrix.domains.length} ta soha, ${typeCount} ta ma'lumot turi. Har bir tur uchun zarur huquqiy asos, olinadigan ma'lumotlar va qonun moddalari.`}
                actions={
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex h-9 items-center gap-2 rounded-md border px-3.5 text-sm font-medium transition-colors hover:bg-accent print:hidden"
                    >
                        <Printer aria-hidden="true" className="size-4" />
                        Chop etish
                    </button>
                }
            />

            <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded-lg border border-success-border bg-success-soft/50 p-4">
                    <AccessBadge
                        access="order"
                        label="Advokat so'rovi + order"
                    />
                    <p className="mt-2 text-sm leading-relaxed">
                        Advokat so'rovi va advokat orderi yetarli (Advokatura
                        to'g'risidagi Qonun, 7¹-modda). Sud qarori ham qabul
                        qilinadi.
                    </p>
                </div>
                <div className="rounded-lg border border-danger-border bg-danger-soft/50 p-4">
                    <AccessBadge
                        access="court"
                        label="Faqat sud qarori / ajrimi"
                    />
                    <p className="mt-2 text-sm leading-relaxed">
                        Sir rejimidagi (soliq siri, bank siri) yoki maxsus
                        toifadagi shaxsga doir ma'lumot. Advokat so'rovi yetarli
                        emas — platforma bunday so'rovni orderi bilan yuborishga
                        yo'l qo'ymaydi.
                    </p>
                </div>
            </div>

            <nav
                aria-label="Sohalar"
                className="flex flex-wrap gap-2 print:hidden"
            >
                {matrix.domains.map((d) => (
                    <a
                        key={d.key}
                        href={`#soha-${d.key}`}
                        className="rounded-md border bg-card px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                    >
                        {d.title}
                    </a>
                ))}
            </nav>

            <Overview
                matrix={matrix}
                secrecy={secrecy}
                canCreate={can_create_request}
            />

            <div className="space-y-4">
                <h2 className="text-xl font-semibold">
                    Sohalar bo'yicha
                </h2>
                {matrix.domains.map((d) => (
                    <DomainSection
                        key={d.key}
                        domain={d}
                        canCreate={can_create_request}
                    />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Deadlines d={matrix.deadlines} />
                <OutOfScope items={matrix.out_of_scope} />
            </div>

            <p className="flex gap-2 border-t pt-4 text-xs leading-relaxed text-muted-foreground">
                <TriangleAlert
                    aria-hidden="true"
                    className="mt-px size-3.5 shrink-0"
                />
                {matrix.disclaimer}
            </p>
        </div>
    );
}

export default function LegalMapPage(props: Props) {
    return (
        <>
            <Head title="Huquqiy xarita" />
            <LegalMapContent {...props} />
        </>
    );
}

// Shell comes from app.tsx: app shell when logged in, public shell for guests.
LegalMapPage.layout = {
    breadcrumbs: [{ title: "Huquqiy xarita", href: legalMap() }],
};

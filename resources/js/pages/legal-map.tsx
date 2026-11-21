import { Head, Link } from "@inertiajs/react";
import { ExternalLink, FilePlus2, Printer, TriangleAlert } from "lucide-react";
import { PageHeader } from "@/components/brand/page-header";
import { Section } from "@/components/brand/section";
import { AccessBadge } from "@/components/legal/access-badge";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
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

/**
 * Print: the shell (utility bar, sidebar, footer) is already print:hidden and
 * the header prints as a static letterhead. Here we only drop the page tint,
 * shadows and in-page navigation.
 */
const PRINT_CSS = `@media print {
  @page { margin: 14mm; }
  html, body, main { background: #fff !important; }
  [data-legal-map] * { box-shadow: none !important; }
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

function RefLink({ refItem }: { refItem: LegalRef }) {
    if (!refItem.url) {
        return (
            <span title={refItem.note} className="text-sm text-ink-secondary">
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
            className="focus-ring inline-flex items-center gap-1.5 rounded-xs text-sm text-brand underline-offset-4 hover:underline"
        >
            {refLabel(refItem)}
            <ExternalLink
                aria-hidden="true"
                strokeWidth={1.5}
                className="size-3.5 shrink-0 print:hidden"
            />
            <span className="sr-only">(lex.uz, yangi oynada)</span>
        </a>
    );
}

function CreateRequestLink({ type }: { type: string }) {
    return (
        <Button asChild size="sm" variant="outline" className="print:hidden">
            <Link href={`/requests/create?type=${encodeURIComponent(type)}`}>
                <FilePlus2 aria-hidden="true" strokeWidth={1.5} />
                So'rov yaratish
            </Link>
        </Button>
    );
}

function deadlineFor(t: LegalMatrixType, days: number): string {
    return t.access === "order" ? `${days} kun` : "Sud ajrimi tartibida";
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
        <Section
            id="jadval"
            title="Umumiy jadval"
            description="Har bir ma'lumot turi uchun zarur huquqiy asos va javob muddati"
        >
            <div className="overflow-hidden rounded-lg border border-hairline bg-surface shadow-xs">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Ma'lumot turi</TableHead>
                            <TableHead>Zarur asos</TableHead>
                            <TableHead className="hidden md:table-cell">
                                Sud qarori qabul qilinadi
                            </TableHead>
                            <TableHead className="hidden lg:table-cell">
                                Sir rejimi
                            </TableHead>
                            <TableHead className="hidden md:table-cell">
                                Javob muddati
                            </TableHead>
                            {canCreate && (
                                <TableHead className="hidden lg:table-cell print:hidden">
                                    <span className="sr-only">Amal</span>
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    {matrix.domains.map((d, di) => (
                        <TableBody key={d.key}>
                            <TableRow className="bg-page hover:bg-page">
                                <th
                                    scope="colgroup"
                                    colSpan={canCreate ? 6 : 5}
                                    className="h-10 px-4 text-left text-sm font-semibold text-ink"
                                >
                                    <span className="mr-2 font-mono font-normal text-ink-muted tabular-nums">
                                        {di + 1}.
                                    </span>
                                    {d.title}
                                </th>
                            </TableRow>
                            {d.types.map((t) => {
                                const court = t.accepted_bases.some(
                                    (b) => b.value === "court_decision",
                                );

                                return (
                                    <TableRow key={t.value}>
                                        <TableCell className="py-3 whitespace-normal">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="font-medium text-ink">
                                                    {t.label}
                                                </span>
                                                <span className="text-meta text-ink-secondary">
                                                    {t.agency}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <AccessBadge access={t.access} size="sm" />
                                        </TableCell>
                                        <TableCell className="hidden text-ink md:table-cell">
                                            {court ? "Ha" : "Yo'q"}
                                        </TableCell>
                                        <TableCell className="hidden text-ink-secondary lg:table-cell">
                                            {regimeShort(secrecy[t.value])}
                                        </TableCell>
                                        <TableCell className="hidden whitespace-nowrap text-ink md:table-cell">
                                            {deadlineFor(t, matrix.deadlines.default_days)}
                                        </TableCell>
                                        {canCreate && (
                                            <TableCell className="hidden text-right lg:table-cell print:hidden">
                                                <CreateRequestLink type={t.value} />
                                            </TableCell>
                                        )}
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    ))}
                </Table>
            </div>
        </Section>
    );
}

function DomainSection({
    domain,
    index,
    canCreate,
}: {
    domain: LegalDomain;
    index: number;
    canCreate: boolean;
}) {
    return (
        <section
            id={`soha-${domain.key}`}
            aria-labelledby={`soha-${domain.key}-title`}
            className="scroll-mt-header rounded-lg border border-hairline bg-surface shadow-xs print:break-inside-avoid"
        >
            <header className="flex flex-col gap-1 border-b border-hairline px-6 py-5">
                <h3
                    id={`soha-${domain.key}-title`}
                    className="text-section-title text-ink"
                >
                    <span className="mr-2 font-mono font-normal text-ink-muted tabular-nums">
                        {index + 1}.
                    </span>
                    {domain.title}
                </h3>
                <p className="max-w-[72ch] text-base text-ink-secondary">
                    {domain.summary}
                </p>
            </header>
            <div className="divide-y divide-hairline">
                {domain.types.map((t, ti) => (
                    <article key={t.value} className="flex flex-col gap-4 px-6 py-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex min-w-0 flex-col gap-0.5">
                                <h4 className="text-base font-semibold text-ink">
                                    <span className="mr-2 font-mono font-normal text-ink-muted tabular-nums">
                                        {index + 1}.{ti + 1}
                                    </span>
                                    {t.label}
                                </h4>
                                <span className="text-sm text-ink-secondary">
                                    Manba: {t.agency}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <AccessBadge access={t.access} />
                                {canCreate && <CreateRequestLink type={t.value} />}
                            </div>
                        </div>
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <span className="text-sm font-medium text-ink-secondary">
                                    Nima olinadi
                                </span>
                                <ul className="flex flex-col gap-1.5 text-base text-ink">
                                    {t.items.map((item) => (
                                        <li key={item} className="flex gap-2.5">
                                            <span
                                                aria-hidden="true"
                                                className="mt-[11px] h-px w-2 shrink-0 bg-ink-muted"
                                            />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="flex flex-col gap-2">
                                <span className="text-sm font-medium text-ink-secondary">
                                    Huquqiy asos
                                </span>
                                <p className="text-base text-ink">{t.why}</p>
                                <ul className="flex flex-col gap-1 pt-1">
                                    {t.refs.map((r, i) => (
                                        <li key={`${r.law}-${r.article}-${i}`}>
                                            <RefLink refItem={r} />
                                        </li>
                                    ))}
                                </ul>
                                {t.verify && (
                                    <p className="inline-flex items-center gap-1.5 text-meta text-warning">
                                        <TriangleAlert
                                            aria-hidden="true"
                                            strokeWidth={1.5}
                                            className="size-4"
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
            text: "So'rov olingan sanadan e'tiboran axborot taqdim etiladi",
        },
        {
            value: `${d.no_info_notice_days} kun`,
            text: "So'ralgan ma'lumot bo'lmasa, shu muddatda advokatga yozma xabar beriladi",
        },
        {
            value: `${d.proceedings_work_days} ish kuni`,
            text: "So'rovga surishtiruv, tergov yoki sud ishi boshlanganini tasdiqlovchi hujjat ilova qilingan bo'lsa",
        },
    ];

    return (
        <Section
            title="Javob muddatlari"
            description="Advokatura to'g'risidagi Qonun, 7¹-modda"
            className="print:break-inside-avoid"
        >
            <dl className="divide-y divide-hairline overflow-hidden rounded-lg border border-hairline bg-surface shadow-xs">
                {rows.map((r) => (
                    <div
                        key={r.value}
                        className="grid grid-cols-[112px_minmax(0,1fr)] gap-4 px-6 py-4"
                    >
                        <dt className="text-lg font-semibold text-ink tabular-nums">
                            {r.value}
                        </dt>
                        <dd className="text-base text-ink-secondary">{r.text}</dd>
                    </div>
                ))}
            </dl>
        </Section>
    );
}

function OutOfScope({ items }: { items: LegalMatrix["out_of_scope"] }) {
    return (
        <Section
            title="Platforma orqali olinmaydi"
            description="Bu ma'lumotlar uchun boshqa tartib amal qiladi"
            className="print:break-inside-avoid"
        >
            <ul className="divide-y divide-hairline overflow-hidden rounded-lg border border-hairline bg-surface shadow-xs">
                {items.map((o) => (
                    <li key={o.title} className="flex flex-col gap-1 px-6 py-4">
                        <span className="text-base font-medium text-ink">{o.title}</span>
                        <p className="text-sm text-ink-secondary">{o.why}</p>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

function LegalMapContent({ matrix, secrecy, can_create_request }: Props) {
    const typeCount = matrix.domains.reduce((n, d) => n + d.types.length, 0);

    return (
        <div data-legal-map className="flex flex-col gap-10">
            <style>{PRINT_CSS}</style>
            <PageHeader
                title="Huquqiy xarita"
                description={`Advokat qaysi ma'lumotni qanday huquqiy asos bilan oladi: ${matrix.domains.length} ta soha, ${typeCount} ta ma'lumot turi, qonun moddalari va javob muddatlari`}
                actions={
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.print()}
                        className="print:hidden"
                    >
                        <Printer aria-hidden="true" strokeWidth={1.5} />
                        Chop etish
                    </Button>
                }
            />

            <div className="grid gap-4 md:grid-cols-2">
                <div className="flex flex-col gap-3 rounded-lg border border-hairline bg-surface p-6 shadow-xs">
                    <AccessBadge access="order" label="Advokat so'rovi va order" />
                    <p className="text-base text-ink-secondary">
                        Advokat so'rovi va advokat orderi yetarli (Advokatura
                        to'g'risidagi Qonun, 7¹-modda). Sud qarori ham qabul
                        qilinadi.
                    </p>
                </div>
                <div className="flex flex-col gap-3 rounded-lg border border-hairline bg-surface p-6 shadow-xs">
                    <AccessBadge access="court" label="Faqat sud qarori yoki ajrimi" />
                    <p className="text-base text-ink-secondary">
                        Sir rejimidagi (soliq siri, bank siri) yoki maxsus
                        toifadagi shaxsga doir ma'lumot. Advokat so'rovi yetarli
                        emas, platforma bunday so'rovni order bilan yuborishga
                        yo'l qo'ymaydi.
                    </p>
                </div>
            </div>

            <nav aria-label="Sohalar" className="flex flex-wrap gap-2 print:hidden">
                {matrix.domains.map((d, i) => (
                    <a
                        key={d.key}
                        href={`#soha-${d.key}`}
                        className="focus-ring inline-flex h-8 items-center gap-2 rounded-md border border-hairline bg-surface px-3 text-sm text-ink transition-colors duration-150 hover:bg-raised"
                    >
                        <span className="font-mono text-ink-muted tabular-nums">
                            {i + 1}.
                        </span>
                        {d.title}
                    </a>
                ))}
            </nav>

            <Overview matrix={matrix} secrecy={secrecy} canCreate={can_create_request} />

            <Section title="Sohalar bo'yicha" contentClassName="flex flex-col gap-6">
                {matrix.domains.map((d, i) => (
                    <DomainSection
                        key={d.key}
                        domain={d}
                        index={i}
                        canCreate={can_create_request}
                    />
                ))}
            </Section>

            <div className="grid gap-10 lg:grid-cols-2 lg:gap-6">
                <Deadlines d={matrix.deadlines} />
                <OutOfScope items={matrix.out_of_scope} />
            </div>

            <p className="flex max-w-[72ch] gap-2 border-t border-hairline pt-4 text-meta text-ink-secondary">
                <TriangleAlert
                    aria-hidden="true"
                    strokeWidth={1.5}
                    className="mt-px size-4 shrink-0"
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

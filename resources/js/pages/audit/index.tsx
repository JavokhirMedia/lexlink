import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ScrollText } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/brand/empty-state';
import { formatDateTime, formatNumber } from '@/components/brand/money';
import { PageHeader } from '@/components/brand/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { index as auditIndex } from '@/routes/audit';
import { show as caseShow } from '@/routes/cases';
import type { AuditEntry } from '@/types';

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Filters = {
    action: string | null;
    case_id: number | null;
    date_from: string | null;
    date_to: string | null;
};

type Props = {
    entries: Paginated<AuditEntry>;
    filters: Filters;
    actions: { value: string; label: string }[];
    cases: { id: number; title: string }[];
    scope: 'all' | 'advocate' | 'own';
};

const ALL = '__all';

const SCOPE_TEXT: Record<Props['scope'], string> = {
    all: 'Administrator: barcha foydalanuvchilar harakatlari, faqat metama’lumotlar',
    advocate:
        'Sizning ishlaringiz bo‘yicha barcha harakatlar va shaxsiy harakatlaringiz',
    own: 'Faqat sizning harakatlaringiz',
};

function formatMetaValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function Meta({ meta }: { meta: AuditEntry['meta'] }) {
    if (!meta || Object.keys(meta).length === 0) {
        return <span className="text-ink-muted">—</span>;
    }

    return (
        <dl className="flex flex-col gap-0.5 font-mono text-meta">
            {Object.entries(meta).map(([k, v]) => (
                <div key={k} className="flex min-w-0 gap-1.5">
                    <dt className="shrink-0 text-ink-muted">{k}:</dt>
                    <dd className="min-w-0 break-all text-ink-secondary">
                        {formatMetaValue(v)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditIndex({
    entries,
    filters,
    actions,
    cases,
    scope,
}: Props) {
    const [action, setAction] = useState(filters.action ?? ALL);
    const [caseId, setCaseId] = useState(
        filters.case_id ? String(filters.case_id) : ALL,
    );
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const filtered =
        !!filters.action ||
        !!filters.case_id ||
        !!filters.date_from ||
        !!filters.date_to;

    const apply = (e?: FormEvent) => {
        e?.preventDefault();

        router.get(
            auditIndex.url(),
            {
                action: action === ALL ? undefined : action,
                case_id: caseId === ALL ? undefined : caseId,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reset = () => {
        setAction(ALL);
        setCaseId(ALL);
        setDateFrom('');
        setDateTo('');
        router.get(auditIndex.url(), {}, { replace: true });
    };

    const caseTitle = (id: number | null) =>
        id === null
            ? null
            : (cases.find((c) => c.id === id)?.title ?? `Ish #${id}`);

    return (
        <>
            <Head title="Audit jurnali" />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title="Audit jurnali"
                    description={`O‘zgartirib bo‘lmaydigan harakatlar jurnali. ${SCOPE_TEXT[scope]}`}
                    meta={
                        <span className="tabular-nums">
                            Jami {formatNumber(entries.total)} ta yozuv
                        </span>
                    }
                />

                <form
                    onSubmit={apply}
                    aria-label="Jurnal filtrlari"
                    className="grid gap-4 rounded-lg border bg-card p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto] lg:items-end"
                >
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="f-action" className="text-sm">
                            Harakat
                        </Label>
                        <Select value={action} onValueChange={setAction}>
                            <SelectTrigger id="f-action" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Barchasi</SelectItem>
                                {actions.map((a) => (
                                    <SelectItem key={a.value} value={a.value}>
                                        {a.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="f-case" className="text-sm">
                            Ish
                        </Label>
                        <Select value={caseId} onValueChange={setCaseId}>
                            <SelectTrigger id="f-case" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Barcha ishlar</SelectItem>
                                {cases.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="f-from" className="text-sm">
                            Sanadan
                        </Label>
                        <Input
                            id="f-from"
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="f-to" className="text-sm">
                            Sanagacha
                        </Label>
                        <Input
                            id="f-to"
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                        />
                    </div>
                    <div className="flex gap-2 sm:col-span-2 lg:col-span-1">
                        <Button type="submit" className="flex-1 lg:flex-none">
                            Filtrlash
                        </Button>
                        {filtered && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={reset}
                            >
                                Tozalash
                            </Button>
                        )}
                    </div>
                </form>

                {entries.data.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title="Yozuvlar topilmadi"
                        description={
                            filtered
                                ? 'Filtrlarni o‘zgartirib ko‘ring'
                                : undefined
                        }
                        action={
                            filtered ? (
                                <Button variant="outline" onClick={reset}>
                                    Filtrlarni tozalash
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
                        <Table className="min-w-[880px]">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-[168px]">Vaqt</TableHead>
                                    <TableHead>Harakat</TableHead>
                                    <TableHead>Foydalanuvchi</TableHead>
                                    <TableHead>Ish / obyekt</TableHead>
                                    <TableHead>Tafsilotlar</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {entries.data.map((e) => (
                                    <TableRow key={e.id} className="align-top">
                                        <TableCell className="align-top whitespace-nowrap">
                                            <span className="block tabular-nums text-ink">
                                                {formatDateTime(e.created_at)}
                                            </span>
                                            {e.ip && (
                                                <span className="block font-mono text-meta text-ink-muted">
                                                    {e.ip}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top font-medium">
                                            {e.action_label}
                                        </TableCell>
                                        <TableCell className="align-top">
                                            {e.user?.name ?? (
                                                <span className="text-ink-muted">
                                                    Tizim
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-[240px] align-top">
                                            {e.case_id !== null &&
                                                (scope === 'all' ? (
                                                    <span className="block truncate">
                                                        {caseTitle(e.case_id)}
                                                    </span>
                                                ) : (
                                                    <Link
                                                        href={caseShow.url(
                                                            e.case_id,
                                                        )}
                                                        className="focus-ring block truncate rounded-xs text-ink underline decoration-hairline underline-offset-4 transition-colors hover:decoration-ink"
                                                    >
                                                        {caseTitle(e.case_id)}
                                                    </Link>
                                                ))}
                                            {e.subject_type && (
                                                <span className="block font-mono text-meta text-ink-muted">
                                                    {e.subject_type} #
                                                    {e.subject_id}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-[360px] align-top">
                                            <Meta meta={e.meta} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {entries.last_page > 1 && (
                    <nav
                        aria-label="Sahifalar"
                        className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span className="text-ink-secondary tabular-nums">
                            {formatNumber(entries.from)}–{formatNumber(entries.to)}{' '}
                            / {formatNumber(entries.total)}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {entries.links.map((l, i) => {
                                const label = paginationLabel(l.label);
                                const content =
                                    label === 'prev' ? (
                                        <>
                                            <ChevronLeft aria-hidden />
                                            <span className="sr-only sm:not-sr-only">
                                                Oldingi
                                            </span>
                                        </>
                                    ) : label === 'next' ? (
                                        <>
                                            <span className="sr-only sm:not-sr-only">
                                                Keyingi
                                            </span>
                                            <ChevronRight aria-hidden />
                                        </>
                                    ) : (
                                        label
                                    );
                                const cls = cn(
                                    'min-w-8 tabular-nums',
                                    l.active &&
                                        'border-brand-border bg-brand-soft text-brand hover:border-brand-border hover:bg-brand-soft',
                                    l.url === null &&
                                        'pointer-events-none opacity-50',
                                );

                                return l.url !== null ? (
                                    <Button
                                        key={i}
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className={cls}
                                    >
                                        <Link
                                            href={l.url}
                                            preserveScroll
                                            aria-current={
                                                l.active ? 'page' : undefined
                                            }
                                        >
                                            {content}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant="outline"
                                        disabled
                                        className={cls}
                                    >
                                        {content}
                                    </Button>
                                );
                            })}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function paginationLabel(label: string): string {
    if (label.includes('Previous') || label.includes('&laquo;')) {
        return 'prev';
    }

    if (label.includes('Next') || label.includes('&raquo;')) {
        return 'next';
    }

    return label;
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit jurnali', href: auditIndex() }],
};

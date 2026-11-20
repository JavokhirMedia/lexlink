import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/components/brand/page-header';
import { Badge } from '@/components/ui/badge';
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
import { cn } from '@/lib/utils';
import { index as auditIndex } from '@/routes/audit';
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
    all: 'Administrator: barcha foydalanuvchilar harakatlari (faqat metama’lumotlar).',
    advocate: 'Sizning ishlaringiz bo‘yicha barcha harakatlar va shaxsiy harakatlaringiz.',
    own: 'Faqat sizning harakatlaringiz.',
};

const dateTimeFmt = new Intl.DateTimeFormat('uz-UZ', {
    dateStyle: 'short',
    timeStyle: 'medium',
});

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
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-xs">
            {Object.entries(meta).map(([k, v]) => (
                <div key={k} className="contents">
                    <dt className="text-muted-foreground">{k}</dt>
                    <dd className="font-mono break-all">{formatMetaValue(v)}</dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditIndex({ entries, filters, actions, cases, scope }: Props) {
    const [action, setAction] = useState(filters.action ?? ALL);
    const [caseId, setCaseId] = useState(
        filters.case_id ? String(filters.case_id) : ALL,
    );
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

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
        id === null ? null : (cases.find((c) => c.id === id)?.title ?? `#${id}`);

    return (
        <>
            <Head title="Audit jurnali" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Audit jurnali"
                    description={`O‘zgartirib bo‘lmaydigan harakatlar jurnali. ${SCOPE_TEXT[scope]}`}
                />

                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-lg border p-4 md:grid-cols-5 md:items-end"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-action" className="text-xs">Harakat</Label>
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
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-case" className="text-xs">Ish</Label>
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
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-from" className="text-xs">Sanadan</Label>
                        <Input
                            id="f-from"
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-to" className="text-xs">Sanagacha</Label>
                        <Input
                            id="f-to"
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" className="flex-1">
                            Filtrlash
                        </Button>
                        <Button type="button" variant="outline" onClick={reset}>
                            Tozalash
                        </Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Vaqt</th>
                                <th className="px-3 py-2 font-medium">Harakat</th>
                                <th className="px-3 py-2 font-medium">Foydalanuvchi</th>
                                <th className="px-3 py-2 font-medium">Ish / obyekt</th>
                                <th className="px-3 py-2 font-medium">Tafsilotlar</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {entries.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        Yozuvlar topilmadi.
                                    </td>
                                </tr>
                            )}
                            {entries.data.map((e) => (
                                <tr key={e.id} className="align-top">
                                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                        {dateTimeFmt.format(new Date(e.created_at))}
                                        {e.ip && (
                                            <div className="text-xs text-muted-foreground">
                                                {e.ip}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="secondary">{e.action_label}</Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        {e.user?.name ?? (
                                            <span className="text-muted-foreground">Tizim</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {e.case_id !== null && (
                                            <div className="font-medium">
                                                {caseTitle(e.case_id)}
                                            </div>
                                        )}
                                        {e.subject_type && (
                                            <div className="text-muted-foreground">
                                                {e.subject_type} #{e.subject_id}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Meta meta={e.meta} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {entries.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="text-muted-foreground">
                            {entries.from}–{entries.to} / {entries.total}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {entries.links.map((l, i) => (
                                <Button
                                    key={i}
                                    asChild={l.url !== null}
                                    size="sm"
                                    variant={l.active ? 'default' : 'outline'}
                                    disabled={l.url === null}
                                    className={cn(l.url === null && 'pointer-events-none opacity-50')}
                                >
                                    {l.url !== null ? (
                                        <Link href={l.url} preserveScroll>
                                            {paginationLabel(l.label)}
                                        </Link>
                                    ) : (
                                        <span>{paginationLabel(l.label)}</span>
                                    )}
                                </Button>
                            ))}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function paginationLabel(label: string): string {
    if (label.includes('Previous') || label.includes('&laquo;')) {
        return '‹ Oldingi';
    }

    if (label.includes('Next') || label.includes('&raquo;')) {
        return 'Keyingi ›';
    }

    return label;
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit jurnali', href: auditIndex() }],
};

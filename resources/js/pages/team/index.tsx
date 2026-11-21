import { Form, Head, router, usePage } from "@inertiajs/react";
import { Check, Copy, UserMinus, Users } from "lucide-react";
import { EmptyState } from "@/components/brand/empty-state";
import { formatDate } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { Section } from "@/components/brand/section";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { useClipboard } from "@/hooks/use-clipboard";
import { cn } from "@/lib/utils";
import { index as teamIndex } from "@/routes/team";
import { store as storeInvite } from "@/routes/team/invites";
import { destroy as destroyLink } from "@/routes/team/links";

type TeamLink = {
    id: number;
    contact: string;
    status: "pending" | "accepted" | "revoked";
    status_label: string;
    person: {
        id: number;
        name: string;
        phone: string | null;
        bureau_name?: string | null;
    } | null;
    invite_url: string | null;
    invited_at: string | null;
    accepted_at: string | null;
};

type Props = {
    mode: "advocate" | "lawyer";
    links: TeamLink[];
};

const STATUS_CLASSES: Record<TeamLink["status"], string> = {
    pending: "bg-warning-soft text-warning",
    accepted: "bg-success-soft text-success",
    revoked: "bg-raised text-ink-secondary",
};

function LinkStatus({ link }: { link: TeamLink }) {
    return (
        <span
            className={cn(
                "inline-flex h-6 w-fit shrink-0 items-center rounded-sm px-2 text-xs font-medium whitespace-nowrap",
                STATUS_CLASSES[link.status],
            )}
        >
            {link.status_label}
        </span>
    );
}

function CopyButton({ value }: { value: string }) {
    const [copied, copy] = useClipboard();
    const done = copied === value;

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => copy(value)}
            aria-label={
                done ? "Havola nusxalandi" : "Taklif havolasini nusxalash"
            }
        >
            {done ? <Check className="text-success" /> : <Copy />}
            {done ? "Nusxalandi" : "Havolani nusxalash"}
        </Button>
    );
}

/** Mono, single line, selectable: the link the advocate sends by hand. */
function InviteUrl({ url, className }: { url: string; className?: string }) {
    return (
        <code
            className={cn(
                "block min-w-0 truncate font-mono text-[13px] leading-[18px] text-ink-secondary select-all",
                className,
            )}
            title={url}
        >
            {url}
        </code>
    );
}

export default function TeamIndex({ mode, links }: Props) {
    const { flash } = usePage();
    const newInviteUrl =
        typeof flash?.invite_url === "string" ? flash.invite_url : null;

    const revoke = (link: TeamLink) => {
        if (
            !window.confirm(
                link.status === "pending"
                    ? "Taklifni bekor qilasizmi?"
                    : `${link.person?.name ?? link.contact} jamoadan chiqarilsinmi? U sizning ishlaringizdan ham chiqariladi.`,
            )
        ) {
            return;
        }

        router.delete(destroyLink.url(link.id), { preserveScroll: true });
    };

    if (mode === "lawyer") {
        return (
            <>
                <Head title="Jamoa" />
                <div className="flex flex-col gap-8">
                    <PageHeader
                        title="Jamoa"
                        description="Siz yordamchi yurist sifatida ishlayotgan advokatlar. Bir nechta advokat bilan ishlash mumkin"
                    />
                    {links.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Hozircha birorta advokat jamoasida emassiz"
                            description="Advokat yuborgan taklif havolasini oching"
                        />
                    ) : (
                        <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                            {links.map((l) => (
                                <li
                                    key={l.id}
                                    className="flex min-h-14 flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="text-base font-medium text-ink">
                                            {l.person?.name}
                                        </p>
                                        <p className="text-sm text-ink-secondary">
                                            {l.person?.bureau_name ?? "Advokat"}
                                            {l.person?.phone
                                                ? ` · ${l.person.phone}`
                                                : ""}
                                        </p>
                                    </div>
                                    <p className="text-meta text-ink-muted tabular-nums">
                                        Qo'shilgan {formatDate(l.accepted_at)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Jamoa" />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title="Jamoa"
                    description="Yordamchi yuristlarni taklif qiling. Yurist so'rov loyihalarini tayyorlaydi; imzolash va yuborish faqat sizda qoladi"
                />

                <Section
                    title="Yuristni taklif qilish"
                    description="Demo rejimida xat yuborilmaydi: havolani nusxalab yuristga o'zingiz yuboring"
                    card
                    contentClassName="flex flex-col gap-4"
                >
                    <Form
                        {...storeInvite.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Label htmlFor="contact">
                                    Elektron pochta yoki telefon
                                </Label>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <div className="flex flex-1 flex-col gap-2">
                                        <Input
                                            id="contact"
                                            name="contact"
                                            required
                                            placeholder="yurist@example.uz yoki +998 90 123 45 67"
                                            aria-invalid={
                                                Boolean(errors.contact) ||
                                                undefined
                                            }
                                        />
                                        <InputError message={errors.contact} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Taklif havolasini yaratish
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>

                    {newInviteUrl && (
                        <div className="flex flex-col gap-3 rounded-md border border-brand-border bg-brand-soft p-4 sm:flex-row sm:items-center">
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <p className="text-sm font-medium text-ink">
                                    Taklif havolasi tayyor
                                </p>
                                <InviteUrl
                                    url={newInviteUrl}
                                    className="text-ink"
                                />
                            </div>
                            <CopyButton value={newInviteUrl} />
                        </div>
                    )}
                </Section>

                <Section
                    title="Jamoa a'zolari va takliflar"
                    description={
                        links.length > 0 ? `Jami ${links.length}` : undefined
                    }
                >
                    {links.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Hali hech kim taklif qilinmagan"
                            description="Yuqoridagi shakl orqali birinchi yuristni taklif qiling"
                        />
                    ) : (
                        <>
                            {/* Desktop: table */}
                            <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Yurist</TableHead>
                                            <TableHead>Holati</TableHead>
                                            <TableHead>Taklif</TableHead>
                                            <TableHead>Qo'shilgan</TableHead>
                                            <TableHead className="text-right">
                                                <span className="sr-only">
                                                    Amallar
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {links.map((l) => (
                                            <TableRow key={l.id}>
                                                <TableCell className="max-w-[360px]">
                                                    <div className="flex min-w-0 flex-col gap-0.5">
                                                        <span className="truncate text-base font-medium text-ink">
                                                            {l.person?.name ??
                                                                l.contact}
                                                        </span>
                                                        {l.person && (
                                                            <span className="truncate text-sm text-ink-secondary">
                                                                {l.contact}
                                                            </span>
                                                        )}
                                                        {l.invite_url && (
                                                            <InviteUrl
                                                                url={
                                                                    l.invite_url
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <LinkStatus link={l} />
                                                </TableCell>
                                                <TableCell className="text-ink-secondary">
                                                    {formatDate(l.invited_at)}
                                                </TableCell>
                                                <TableCell className="text-ink-secondary">
                                                    {l.accepted_at
                                                        ? formatDate(
                                                              l.accepted_at,
                                                          )
                                                        : "—"}
                                                </TableCell>
                                                <TableCell>
                                                    <RowActions
                                                        link={l}
                                                        onRevoke={revoke}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Mobile: stacked rows */}
                            <ul className="divide-y overflow-hidden rounded-lg border bg-card md:hidden">
                                {links.map((l) => (
                                    <li
                                        key={l.id}
                                        className="flex flex-col gap-3 p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="truncate text-base font-medium text-ink">
                                                    {l.person?.name ??
                                                        l.contact}
                                                </span>
                                                <span className="text-meta text-ink-muted tabular-nums">
                                                    {l.person
                                                        ? `${l.contact} · `
                                                        : ""}
                                                    Taklif{" "}
                                                    {formatDate(l.invited_at)}
                                                    {l.accepted_at &&
                                                        ` · qo'shilgan ${formatDate(l.accepted_at)}`}
                                                </span>
                                            </div>
                                            <LinkStatus link={l} />
                                        </div>
                                        {l.invite_url && (
                                            <InviteUrl url={l.invite_url} />
                                        )}
                                        <RowActions
                                            link={l}
                                            onRevoke={revoke}
                                            className="justify-start"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Section>
            </div>
        </>
    );
}

function RowActions({
    link,
    onRevoke,
    className,
}: {
    link: TeamLink;
    onRevoke: (l: TeamLink) => void;
    className?: string;
}) {
    if (!link.invite_url && link.status === "revoked") {
        return null;
    }

    return (
        <div className={cn("flex flex-wrap justify-end gap-2", className)}>
            {link.invite_url && <CopyButton value={link.invite_url} />}
            {link.status !== "revoked" && (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => onRevoke(link)}
                >
                    <UserMinus />
                    {link.status === "pending" ? "Bekor qilish" : "Chiqarish"}
                </Button>
            )}
        </div>
    );
}

TeamIndex.layout = {
    breadcrumbs: [{ title: "Jamoa", href: teamIndex() }],
};

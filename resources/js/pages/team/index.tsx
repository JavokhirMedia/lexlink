import { Form, Head, router, usePage } from '@inertiajs/react';
import { Check, Copy, UserMinus, UserPlus } from 'lucide-react';
import { PageHeader } from '@/components/brand/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { index as teamIndex } from '@/routes/team';
import { store as storeInvite } from '@/routes/team/invites';
import { destroy as destroyLink } from '@/routes/team/links';

type TeamLink = {
    id: number;
    contact: string;
    status: 'pending' | 'accepted' | 'revoked';
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
    mode: 'advocate' | 'lawyer';
    links: TeamLink[];
};

const dateFmt = new Intl.DateTimeFormat('uz-UZ', {
    dateStyle: 'medium',
});

function formatDate(value: string | null): string {
    return value ? dateFmt.format(new Date(value)) : '—';
}

function statusVariant(status: TeamLink['status']) {
    return status === 'accepted'
        ? 'default'
        : status === 'pending'
          ? 'secondary'
          : 'outline';
}

function CopyButton({ value }: { value: string }) {
    const [copied, copy] = useClipboard();

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => copy(value)}
        >
            {copied === value ? <Check /> : <Copy />}
            {copied === value ? 'Nusxalandi' : 'Nusxalash'}
        </Button>
    );
}

export default function TeamIndex({ mode, links }: Props) {
    const { flash } = usePage();
    const newInviteUrl =
        typeof flash?.invite_url === 'string' ? flash.invite_url : null;

    const revoke = (link: TeamLink) => {
        if (
            !window.confirm(
                link.status === 'pending'
                    ? 'Taklifni bekor qilasizmi?'
                    : `${link.person?.name ?? link.contact} jamoadan chiqarilsinmi? U sizning ishlaringizdan ham chiqariladi.`,
            )
        ) {
            return;
        }

        router.delete(destroyLink.url(link.id), { preserveScroll: true });
    };

    if (mode === 'lawyer') {
        return (
            <>
                <Head title="Jamoa" />
                <div className="space-y-6 p-4 md:p-6">
                    <PageHeader
                        title="Jamoa"
                        description="Siz yordamchi yurist sifatida ishlayotgan advokatlar. Bir nechta advokat bilan ishlash mumkin."
                    />
                    {links.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Hozircha birorta advokat jamoasida emassiz. Advokat
                            yuborgan taklif havolasini oching.
                        </p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {links.map((l) => (
                                <div
                                    key={l.id}
                                    className="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {l.person?.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {l.person?.bureau_name ?? 'Advokat'}
                                            {l.person?.phone
                                                ? ` · ${l.person.phone}`
                                                : ''}
                                        </div>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Qo‘shilgan: {formatDate(l.accepted_at)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Jamoa" />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Jamoa"
                    description="Yordamchi yuristlarni taklif qiling. Yurist so‘rov loyihalarini tayyorlaydi; imzolash va yuborish faqat sizda qoladi."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Yuristni taklif qilish</CardTitle>
                        <CardDescription>
                            Elektron pochta yoki telefon raqamini kiriting —
                            taklif havolasi yaratiladi. Demo rejimida xat
                            yuborilmaydi: havolani nusxalab yuristga o‘zingiz
                            yuboring.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Form
                            {...storeInvite.form()}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-2 sm:flex-row sm:items-start"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label htmlFor="contact" className="sr-only">
                                            Elektron pochta yoki telefon
                                        </Label>
                                        <Input
                                            id="contact"
                                            name="contact"
                                            required
                                            placeholder="yurist@example.uz yoki +998 90 123 45 67"
                                        />
                                        <InputError message={errors.contact} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? <Spinner /> : <UserPlus />}
                                        Taklif yaratish
                                    </Button>
                                </>
                            )}
                        </Form>

                        {newInviteUrl && (
                            <div className="flex flex-col gap-2 rounded-md border bg-muted/50 p-3 sm:flex-row sm:items-center">
                                <code className="flex-1 truncate text-xs">
                                    {newInviteUrl}
                                </code>
                                <CopyButton value={newInviteUrl} />
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    <h2 className="text-base font-semibold">Jamoa a’zolari va takliflar</h2>
                    {links.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Hali hech kim taklif qilinmagan.
                        </p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {links.map((l) => (
                                <div
                                    key={l.id}
                                    className="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {l.person?.name ?? l.contact}
                                            </span>
                                            <Badge variant={statusVariant(l.status)}>
                                                {l.status_label}
                                            </Badge>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {l.person ? `${l.contact} · ` : ''}
                                            Taklif: {formatDate(l.invited_at)}
                                            {l.accepted_at &&
                                                ` · Qo‘shilgan: ${formatDate(l.accepted_at)}`}
                                        </div>
                                        {l.invite_url && (
                                            <code className="block truncate text-xs text-muted-foreground">
                                                {l.invite_url}
                                            </code>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        {l.invite_url && (
                                            <CopyButton value={l.invite_url} />
                                        )}
                                        {l.status !== 'revoked' && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => revoke(l)}
                                            >
                                                <UserMinus />
                                                {l.status === 'pending'
                                                    ? 'Bekor qilish'
                                                    : 'Chiqarish'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

TeamIndex.layout = {
    breadcrumbs: [{ title: 'Jamoa', href: teamIndex() }],
};

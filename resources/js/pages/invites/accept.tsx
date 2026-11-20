import { Form, Head, Link } from '@inertiajs/react';
import { UserCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { OnboardingShell } from '@/components/onboarding/onboarding-shell';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { accept } from '@/routes/invites';
import { index as teamIndex } from '@/routes/team';

type Props = {
    token: string;
    invite: {
        advocate_name: string;
        bureau_name: string | null;
        license_number: string | null;
        contact: string;
        status: string;
        status_label: string;
    } | null;
    already_member: boolean;
    can_accept: boolean;
    reason: string | null;
    will_become_lawyer: boolean;
};

export default function InviteAccept({
    token,
    invite,
    already_member,
    can_accept,
    reason,
    will_become_lawyer,
}: Props) {
    return (
        <>
            <Head title="Jamoaga taklif" />

            <div className="space-y-6">
                {invite && (
                    <dl className="grid gap-3 rounded-md border p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs text-muted-foreground">Advokat</dt>
                            <dd className="font-medium">{invite.advocate_name}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Advokatlik tuzilmasi
                            </dt>
                            <dd>{invite.bureau_name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Litsenziya</dt>
                            <dd>{invite.license_number ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Taklif kimga</dt>
                            <dd>{invite.contact}</dd>
                        </div>
                    </dl>
                )}

                {reason && !can_accept && (
                    <Alert variant={already_member ? 'default' : 'destructive'}>
                        <AlertDescription>{reason}</AlertDescription>
                    </Alert>
                )}

                {can_accept && (
                    <Form {...accept.form(token)} className="space-y-4">
                        {({ processing, errors }) => (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    Taklifni qabul qilsangiz, advokat sizni o‘z
                                    ishlariga biriktira oladi. Siz so‘rov
                                    loyihalarini tayyorlaysiz; imzolash va
                                    yuborish advokat zimmasida.
                                    {will_become_lawyer &&
                                        ' Siz platformada yurist (litsenziyasiz) sifatida ro‘yxatdan o‘tasiz.'}
                                </p>
                                <InputError message={errors.invite} />
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing ? <Spinner /> : <UserCheck />}
                                    Taklifni qabul qilish
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {!can_accept && (
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link href={already_member ? teamIndex() : dashboard()}>
                                {already_member ? 'Jamoaga o‘tish' : 'Bosh sahifa'}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

InviteAccept.layout = (page: ReactNode) => (
    <OnboardingShell
        title="Advokat jamoasiga taklif"
        description="Siz advokatning yordamchi yuristi sifatida jamoaga taklif qilindingiz."
    >
        {page}
    </OnboardingShell>
);

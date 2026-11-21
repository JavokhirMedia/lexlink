import { Form, Head, Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import { RegistryChip } from "@/components/brand/registry-chip";
import InputError from "@/components/input-error";
import { OnboardingShell } from "@/components/onboarding/onboarding-shell";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { dashboard } from "@/routes";
import { accept } from "@/routes/invites";
import { index as teamIndex } from "@/routes/team";

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

            <div className="flex flex-col gap-6">
                {invite && (
                    <dl className="grid gap-x-6 gap-y-4 rounded-lg border p-5 sm:grid-cols-2">
                        <div className="flex flex-col gap-1">
                            <dt className="text-meta text-ink-muted">
                                Advokat
                            </dt>
                            <dd className="text-base font-medium text-ink">
                                {invite.advocate_name}
                            </dd>
                        </div>
                        <div className="flex flex-col gap-1">
                            <dt className="text-meta text-ink-muted">
                                Advokatlik tuzilmasi
                            </dt>
                            <dd className="text-base text-ink">
                                {invite.bureau_name ?? "—"}
                            </dd>
                        </div>
                        <div className="flex flex-col gap-1">
                            <dt className="text-meta text-ink-muted">
                                Litsenziya
                            </dt>
                            <dd>
                                <RegistryChip
                                    value={invite.license_number}
                                    kind="license"
                                />
                            </dd>
                        </div>
                        <div className="flex flex-col gap-1">
                            <dt className="text-meta text-ink-muted">
                                Taklif kimga
                            </dt>
                            <dd className="text-base break-all text-ink">
                                {invite.contact}
                            </dd>
                        </div>
                    </dl>
                )}

                {reason && !can_accept && (
                    <Alert variant={already_member ? "default" : "destructive"}>
                        <AlertDescription>{reason}</AlertDescription>
                    </Alert>
                )}

                {can_accept && (
                    <Form
                        {...accept.form(token)}
                        className="flex flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex flex-col gap-2 rounded-lg bg-raised p-4 text-sm text-ink-secondary">
                                    <p>
                                        Taklifni qabul qilsangiz, advokat sizni
                                        o'z ishlariga biriktira oladi. Siz
                                        so'rov loyihalarini tayyorlaysiz;
                                        imzolash va yuborish advokat zimmasida.
                                    </p>
                                    {will_become_lawyer && (
                                        <p className="text-ink">
                                            Siz platformada yurist
                                            (litsenziyasiz) sifatida ro'yxatdan
                                            o'tasiz.
                                        </p>
                                    )}
                                </div>
                                <InputError message={errors.invite} />
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full sm:w-auto sm:self-end"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Taklifni qabul qilish
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {!can_accept && (
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link
                                href={
                                    already_member ? teamIndex() : dashboard()
                                }
                            >
                                {already_member
                                    ? "Jamoaga o'tish"
                                    : "Bosh sahifa"}
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
        description="Siz advokatning yordamchi yuristi sifatida jamoaga taklif qilindingiz"
    >
        {page}
    </OnboardingShell>
);

import { Form, Head } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

type Profile = {
    name: string;
    phone: string | null;
    email: string | null;
    pinfl_masked: string | null;
    role_label: string | null;
    license_number: string | null;
    license_status: string | null;
    license_checked_at: string | null;
};

const LICENSE_LABELS: Record<string, string> = {
    active: 'Faol',
    revoked: 'Bekor qilingan',
    suspended: "To'xtatib turilgan",
    pending: 'Tekshirilmoqda',
};

function ReadOnlyField({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value || '—'}</dd>
        </div>
    );
}

export default function ProfilePage({ profile }: { profile: Profile }) {
    return (
        <>
            <Head title="Profil" />

            <h1 className="sr-only">Profil</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Shaxsiy ma’lumotlar"
                    description="OneID orqali tasdiqlangan. O‘zgartirish uchun OneID profilingizni yangilang."
                />

                <dl className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <ReadOnlyField label="F.I.Sh." value={profile.name} />
                    <ReadOnlyField label="JShShIR" value={profile.pinfl_masked} />
                    <ReadOnlyField label="Telefon" value={profile.phone} />
                    <ReadOnlyField label="Rol" value={profile.role_label} />
                    {profile.license_number && (
                        <>
                            <ReadOnlyField
                                label="Litsenziya raqami"
                                value={profile.license_number}
                            />
                            <ReadOnlyField
                                label="Litsenziya holati"
                                value={
                                    profile.license_status
                                        ? (LICENSE_LABELS[profile.license_status] ??
                                          profile.license_status)
                                        : null
                                }
                            />
                        </>
                    )}
                </dl>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Aloqa uchun elektron pochta"
                    description="Ixtiyoriy. Bildirishnomalar uchun ishlatiladi."
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Elektron pochta</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    defaultValue={profile.email ?? ''}
                                    autoComplete="email"
                                    placeholder="advokat@example.uz"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button disabled={processing} data-test="update-profile-button">
                                Saqlash
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProfilePage.layout = {
    breadcrumbs: [{ title: 'Profil', href: edit() }],
};

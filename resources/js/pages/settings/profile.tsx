import { Form, Head } from "@inertiajs/react";
import { Check, Lock } from "lucide-react";
import type { ReactNode } from "react";
import ProfileController from "@/actions/App/Http/Controllers/Settings/ProfileController";
import { formatDate } from "@/components/brand/money";
import { RegistryChip } from "@/components/brand/registry-chip";
import { Section } from "@/components/brand/section";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import { edit } from "@/routes/profile";

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

const LICENSE_LABELS: Record<string, { text: string; tone: string }> = {
    active: { text: "Faol", tone: "bg-success-soft text-success" },
    revoked: { text: "Bekor qilingan", tone: "bg-danger-soft text-danger" },
    suspended: {
        text: "To'xtatib turilgan",
        tone: "bg-danger-soft text-danger",
    },
    pending: { text: "Tekshirilmoqda", tone: "bg-warning-soft text-warning" },
};

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex min-h-11 flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
            <dt className="text-sm text-ink-secondary sm:w-44 sm:shrink-0">
                {label}
            </dt>
            <dd className="min-w-0 text-base text-ink">{children || "—"}</dd>
        </div>
    );
}

export default function ProfilePage({ profile }: { profile: Profile }) {
    const license = profile.license_status
        ? (LICENSE_LABELS[profile.license_status] ?? {
              text: profile.license_status,
              tone: "bg-raised text-ink-secondary",
          })
        : null;

    return (
        <>
            <Head title="Profil" />

            <Section
                title="Shaxsiy ma'lumotlar"
                description={
                    <span className="inline-flex items-start gap-1.5">
                        <Lock
                            aria-hidden
                            strokeWidth={1.5}
                            className="mt-0.5 size-4 shrink-0 text-ink-muted"
                        />
                        OneID orqali tasdiqlangan, faqat o'qish uchun.
                        O'zgartirish uchun OneID profilingizni yangilang
                    </span>
                }
                card
                contentClassName="py-3"
            >
                <dl className="divide-y">
                    <Field label="F.I.Sh.">{profile.name}</Field>
                    <Field label="JShShIR">
                        {profile.pinfl_masked && (
                            <RegistryChip
                                value={profile.pinfl_masked}
                                kind="pinfl"
                            />
                        )}
                    </Field>
                    <Field label="Telefon">
                        {profile.phone && (
                            <span className="tabular-nums">
                                {profile.phone}
                            </span>
                        )}
                    </Field>
                    <Field label="Rol">{profile.role_label}</Field>
                    {profile.license_number && (
                        <Field label="Litsenziya">
                            <span className="flex flex-wrap items-center gap-2">
                                <RegistryChip
                                    value={profile.license_number}
                                    kind="license"
                                />
                                {license && (
                                    <span
                                        className={cn(
                                            "inline-flex h-6 items-center rounded-sm px-2 text-xs font-medium",
                                            license.tone,
                                        )}
                                    >
                                        {license.text}
                                    </span>
                                )}
                                {profile.license_checked_at && (
                                    <span className="text-meta text-ink-muted tabular-nums">
                                        Reestrda tekshirilgan{" "}
                                        {formatDate(profile.license_checked_at)}
                                    </span>
                                )}
                            </span>
                        </Field>
                    )}
                </dl>
            </Section>

            <Section
                title="Aloqa uchun elektron pochta"
                description="Ixtiyoriy. Bildirishnomalar uchun ishlatiladi"
                card
            >
                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="email">Elektron pochta</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    defaultValue={profile.email ?? ""}
                                    autoComplete="email"
                                    placeholder="advokat@example.uz"
                                    aria-invalid={
                                        Boolean(errors.email) || undefined
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {processing && <Spinner />}
                                    Saqlash
                                </Button>
                                {recentlySuccessful && (
                                    <span className="inline-flex items-center gap-1.5 text-sm text-success">
                                        <Check aria-hidden className="size-4" />
                                        Saqlandi
                                    </span>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </Section>
        </>
    );
}

ProfilePage.layout = {
    breadcrumbs: [{ title: "Profil", href: edit() }],
};

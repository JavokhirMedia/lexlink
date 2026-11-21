import { Form, Head, Link } from "@inertiajs/react";
import { CheckCircle2, Circle, XCircle } from "lucide-react";
import type { ReactNode } from "react";
import { RegistryChip } from "@/components/brand/registry-chip";
import InputError from "@/components/input-error";
import { OnboardingShell } from "@/components/onboarding/onboarding-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import { dashboard } from "@/routes";
import { role as roleStep } from "@/routes/onboarding";
import { store as storeLicense } from "@/routes/onboarding/license";
import { store as storeRole } from "@/routes/onboarding/role";

type Refusal = {
    license_number: string;
    status: string;
    status_label: string;
};

type Props = {
    name: string;
    is_lawyer: boolean;
    refusal?: Refusal | null;
};

type CheckState = "idle" | "ok" | "fail";

/**
 * The registry answers in three steps; each refusal tells us how far it got:
 *   not found          -> step 1 failed
 *   belongs to someone -> step 1 ok, step 2 failed
 *   revoked/suspended  -> steps 1–2 ok, step 3 failed (session `refusal`)
 * A full pass redirects to the dashboard, so "all ok" is never rendered here.
 */
function checkStates(
    error: string | undefined,
    refusal: Refusal | null | undefined,
): [CheckState, CheckState, CheckState] {
    if (refusal) {
        return ["ok", "ok", "fail"];
    }

    if (error?.includes("topilmadi")) {
        return ["fail", "idle", "idle"];
    }

    if (error?.includes("boshqa shaxs")) {
        return ["ok", "fail", "idle"];
    }

    return ["idle", "idle", "idle"];
}

function CheckRow({
    state,
    children,
}: {
    state: CheckState;
    children: ReactNode;
}) {
    const Icon =
        state === "ok" ? CheckCircle2 : state === "fail" ? XCircle : Circle;

    return (
        <li className="flex min-h-11 items-center gap-3 px-4 py-2 text-sm">
            <Icon
                aria-hidden="true"
                strokeWidth={1.5}
                className={cn(
                    "size-[18px] shrink-0",
                    state === "ok" && "text-success",
                    state === "fail" && "text-danger",
                    state === "idle" && "text-ink-muted",
                )}
            />
            <span
                className={cn(
                    "flex-1",
                    state === "idle" ? "text-ink-secondary" : "text-ink",
                )}
            >
                {children}
            </span>
            <span className="sr-only">
                {state === "ok"
                    ? "tasdiqlandi"
                    : state === "fail"
                      ? "tasdiqlanmadi"
                      : "tekshirilmagan"}
            </span>
        </li>
    );
}

export default function OnboardingLicense({ name, is_lawyer, refusal }: Props) {
    return (
        <>
            <Head title="Litsenziyani tekshirish" />

            <div className="flex flex-col gap-8">
                <Form
                    {...storeLicense.form()}
                    className="flex flex-col gap-6"
                    resetOnError={false}
                >
                    {({ processing, errors }) => {
                        const states = checkStates(
                            errors.license_number,
                            refusal,
                        );
                        const checked = states.some((s) => s !== "idle");

                        return (
                            <>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="license_number">
                                        Advokatlik litsenziyasi raqami
                                    </Label>
                                    <Input
                                        id="license_number"
                                        name="license_number"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        spellCheck={false}
                                        placeholder="AL-00123"
                                        defaultValue={refusal?.license_number}
                                        aria-invalid={
                                            Boolean(errors.license_number) ||
                                            Boolean(refusal) ||
                                            undefined
                                        }
                                        className="h-11 font-mono uppercase tabular-nums placeholder:normal-case"
                                    />
                                    <p className="flex flex-wrap items-center gap-2 text-meta text-ink-muted">
                                        Raqam shu ko'rinishda:
                                        <RegistryChip
                                            value="AL-00123"
                                            kind="license"
                                            size="sm"
                                        />
                                    </p>
                                </div>

                                <div className="flex flex-col gap-2">
                                    <p className="text-sm font-medium text-ink">
                                        {checked
                                            ? "Reestr javobi"
                                            : "Advokatlar reestri nimani tekshiradi"}
                                    </p>
                                    <ul
                                        className={cn(
                                            "divide-y overflow-hidden rounded-lg border",
                                            refusal || errors.license_number
                                                ? "border-danger-border"
                                                : "",
                                        )}
                                        data-test={
                                            refusal
                                                ? "license-refusal"
                                                : undefined
                                        }
                                    >
                                        <CheckRow state={states[0]}>
                                            Litsenziya reestrda mavjud
                                        </CheckRow>
                                        <CheckRow state={states[1]}>
                                            OneID orqali tasdiqlangan
                                            shaxsingizga tegishli ({name})
                                        </CheckRow>
                                        <CheckRow state={states[2]}>
                                            {refusal ? (
                                                <>
                                                    Litsenziya amalda emas:{" "}
                                                    <span className="font-medium text-danger">
                                                        {refusal.status_label}
                                                    </span>
                                                </>
                                            ) : (
                                                "Litsenziya amalda (bekor qilinmagan, to'xtatilmagan)"
                                            )}
                                        </CheckRow>
                                    </ul>
                                    <InputError
                                        message={errors.license_number}
                                    />
                                    {refusal && (
                                        <p className="text-sm text-ink-secondary">
                                            Amaldagi litsenziyasiz advokat
                                            so'rovlarini imzolash va yuborish
                                            mumkin emas, shuning uchun advokat
                                            sifatida ro'yxatdan o'tkazib
                                            bo'lmaydi.
                                            {!is_lawyer &&
                                                " Platformadan yurist (litsenziyasiz) sifatida foydalanishingiz mumkin."}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full sm:w-auto sm:self-start"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {processing
                                        ? "Reestr tekshirmoqda"
                                        : "Reestrda tekshirish"}
                                </Button>
                            </>
                        );
                    }}
                </Form>

                <div className="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between">
                    {is_lawyer ? (
                        <Link
                            href={dashboard()}
                            className="focus-ring rounded-sm text-sm text-ink-secondary transition-colors hover:text-ink"
                        >
                            Bosh sahifaga qaytish
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={roleStep()}
                                className="focus-ring rounded-sm text-sm text-ink-secondary transition-colors hover:text-ink"
                            >
                                Orqaga
                            </Link>
                            <Form {...storeRole.form()}>
                                {({ processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="role"
                                            value="lawyer"
                                        />
                                        <Button
                                            type="submit"
                                            variant={
                                                refusal ? "default" : "outline"
                                            }
                                            disabled={processing}
                                            data-test="continue-as-lawyer"
                                            className="w-full sm:w-auto"
                                        >
                                            Yurist sifatida davom etish
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

OnboardingLicense.layout = (page: ReactNode) => (
    <OnboardingShell
        step={[2, 2]}
        title="Advokatlik litsenziyasini tasdiqlash"
        description="Faqat amaldagi litsenziyaga ega advokat so'rovlarni imzolash va yuborish huquqiga ega"
    >
        {page}
    </OnboardingShell>
);

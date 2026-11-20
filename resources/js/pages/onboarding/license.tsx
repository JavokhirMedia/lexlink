import { Form, Head, Link } from '@inertiajs/react';
import { BadgeCheck, ShieldX } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { OnboardingShell } from '@/components/onboarding/onboarding-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { role as roleStep } from '@/routes/onboarding';
import { store as storeLicense } from '@/routes/onboarding/license';
import { store as storeRole } from '@/routes/onboarding/role';

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

export default function OnboardingLicense({ name, is_lawyer, refusal }: Props) {
    return (
        <>
            <Head title="Litsenziyani tekshirish" />

            <div className="space-y-6">
                {refusal && (
                    <Alert variant="destructive" data-test="license-refusal">
                        <ShieldX />
                        <AlertTitle>
                            Litsenziya {refusal.license_number}{' '}
                            {refusal.status_label}
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                Advokatlar reestri ma’lumotiga ko‘ra ushbu
                                litsenziya amalda emas. Amaldagi litsenziyasiz
                                advokat so‘rovlarini imzolash va yuborish mumkin
                                emas, shuning uchun advokat sifatida ro‘yxatdan
                                o‘tkazib bo‘lmaydi.
                            </p>
                            {!is_lawyer && (
                                <p className="mt-2">
                                    Siz platformadan yurist (litsenziyasiz)
                                    sifatida foydalanishingiz mumkin.
                                </p>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <Form
                    {...storeLicense.form()}
                    className="space-y-4"
                    resetOnError={false}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="license_number">
                                    Advokatlik litsenziyasi raqami
                                </Label>
                                <Input
                                    id="license_number"
                                    name="license_number"
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Masalan: AL-00123"
                                    defaultValue={refusal?.license_number}
                                />
                                <InputError message={errors.license_number} />
                                <p className="text-xs text-muted-foreground">
                                    Raqam Advokatlar palatasi reestrida
                                    tekshiriladi: litsenziya amalda bo‘lishi va
                                    OneID orqali tasdiqlangan shaxsingizga ({name})
                                    tegishli bo‘lishi shart.
                                </p>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                size="lg"
                                disabled={processing}
                            >
                                {processing ? <Spinner /> : <BadgeCheck />}
                                Reestrda tekshirish
                            </Button>
                        </>
                    )}
                </Form>

                <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                    {is_lawyer ? (
                        <Link
                            href={dashboard()}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Bosh sahifaga qaytish
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={roleStep()}
                                className="text-sm text-muted-foreground hover:text-foreground"
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
                                            variant={refusal ? 'default' : 'outline'}
                                            disabled={processing}
                                            data-test="continue-as-lawyer"
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
        step="2-qadam / 2"
        title="Advokatlik litsenziyasini tasdiqlash"
        description="Faqat amaldagi litsenziyaga ega advokat so‘rovlarni imzolash va yuborish huquqiga ega."
    >
        {page}
    </OnboardingShell>
);

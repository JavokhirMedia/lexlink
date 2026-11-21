import { Head, Link, router, useHttp } from "@inertiajs/react";
import { REGEXP_ONLY_DIGITS } from "input-otp";
import { ArrowLeft } from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import {
    checkout as checkoutRoute,
    index as billingIndex,
} from "@/routes/billing";
import { complete, prepare } from "@/routes/billing/mock";
import { formatMoney } from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";

type Props = {
    provider: { code: "click" | "payme"; name: string };
    amount: number;
    balance: number;
    test_card: { number: string; expiry: string };
    demo_code: string;
};

type Prepared = {
    payment_id: string;
    amount: number;
    card_masked: string;
    phone_masked: string;
    expires_in: number;
};

type Completed = {
    status: string;
    amount: number;
    balance: number;
    redirect: string;
};

const formatCard = (v: string) =>
    v
        .replace(/\D/g, "")
        .slice(0, 16)
        .replace(/(\d{4})(?=\d)/g, "$1 ");

const formatExpiry = (v: string) => {
    const d = v.replace(/\D/g, "").slice(0, 4);

    return d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d;
};

export default function Checkout({
    provider,
    amount,
    balance,
    test_card,
    demo_code,
}: Props) {
    const [prepared, setPrepared] = useState<Prepared | null>(null);

    const card = useHttp<
        { amount: number; card_number: string; expiry: string },
        Prepared
    >({
        amount,
        card_number: test_card.number,
        expiry: test_card.expiry,
    });

    const otp = useHttp<{ payment_id: string; code: string }, Completed>({
        payment_id: "",
        code: "",
    });

    const submitCard = (e: FormEvent) => {
        e.preventDefault();
        card.post(prepare.url(provider.code), {
            onSuccess: (res) => {
                setPrepared(res);
                otp.setData({ payment_id: res.payment_id, code: "" });
            },
        }).catch(() => {});
    };

    const submitOtp = (e: FormEvent) => {
        e.preventDefault();
        otp.post(complete.url(provider.code), {
            onSuccess: (res) => router.visit(res.redirect),
        }).catch(() => {});
    };

    return (
        <>
            <Head title={`${provider.name} orqali to'lov`} />
            <div className="flex flex-col gap-8">
                <PageHeader
                    title="Balansni to'ldirish"
                    description={`${provider.name} orqali to'lov. Demo rejimida haqiqiy pul yechilmaydi`}
                />

                <section
                    aria-label="To'lov"
                    className="w-full max-w-[480px] overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div className="flex items-center justify-between gap-3 border-b px-6 py-4">
                        <span className="text-base font-semibold text-ink">
                            {provider.name}
                        </span>
                        <span className="inline-flex h-6 items-center rounded-sm border border-warning-border bg-warning-soft px-2 text-xs font-medium text-warning">
                            DEMO to'lov
                        </span>
                    </div>

                    <dl className="flex items-end justify-between gap-4 border-b bg-page px-6 py-4">
                        <div className="flex flex-col gap-1">
                            <dt className="text-meta text-ink-muted">
                                Qabul qiluvchi
                            </dt>
                            <dd className="text-sm font-medium text-ink">
                                LexLink, balansni to'ldirish
                            </dd>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            <dt className="text-meta text-ink-muted">Summa</dt>
                            <dd className="text-section-title text-ink tabular-nums">
                                {formatMoney(amount)}
                            </dd>
                        </div>
                    </dl>

                    <ol
                        className="flex gap-4 px-6 pt-5 text-meta"
                        aria-label="Bosqichlar"
                    >
                        <li
                            className={
                                prepared
                                    ? "text-ink-muted"
                                    : "font-medium text-ink"
                            }
                        >
                            1. Karta
                        </li>
                        <li
                            className={
                                prepared
                                    ? "font-medium text-ink"
                                    : "text-ink-muted"
                            }
                        >
                            2. SMS tasdiq
                        </li>
                    </ol>

                    {!prepared ? (
                        <form
                            onSubmit={submitCard}
                            className="flex flex-col gap-5 px-6 pt-4 pb-6"
                        >
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="card_number">
                                    Karta raqami
                                </Label>
                                <Input
                                    id="card_number"
                                    inputMode="numeric"
                                    autoComplete="cc-number"
                                    value={card.data.card_number}
                                    onChange={(e) =>
                                        card.setData(
                                            "card_number",
                                            formatCard(e.target.value),
                                        )
                                    }
                                    className="h-11 font-mono text-base tracking-wide tabular-nums"
                                />
                                <InputError message={card.errors.card_number} />
                            </div>
                            <div className="flex flex-col gap-2 sm:w-40">
                                <Label htmlFor="expiry">
                                    Amal qilish muddati
                                </Label>
                                <Input
                                    id="expiry"
                                    inputMode="numeric"
                                    autoComplete="cc-exp"
                                    placeholder="OO/YY"
                                    value={card.data.expiry}
                                    onChange={(e) =>
                                        card.setData(
                                            "expiry",
                                            formatExpiry(e.target.value),
                                        )
                                    }
                                    className="h-11 font-mono text-base tabular-nums"
                                />
                                <InputError message={card.errors.expiry} />
                            </div>
                            <InputError message={card.errors.amount} />
                            <p className="text-meta text-ink-muted">
                                Test karta oldindan to'ldirilgan
                            </p>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={card.processing}
                            >
                                {card.processing && <Spinner />}
                                SMS kod olish
                            </Button>
                        </form>
                    ) : (
                        <form
                            onSubmit={submitOtp}
                            className="flex flex-col gap-5 px-6 pt-4 pb-6"
                        >
                            <div className="flex flex-col gap-1 text-sm">
                                <p className="text-ink">
                                    Karta{" "}
                                    <span className="font-mono tabular-nums">
                                        {prepared.card_masked}
                                    </span>
                                </p>
                                <p className="text-ink-secondary">
                                    {prepared.phone_masked} raqamiga SMS kod
                                    yuborildi
                                </p>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="otp">SMS kod</Label>
                                <InputOTP
                                    id="otp"
                                    maxLength={6}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    value={otp.data.code}
                                    onChange={(v) => otp.setData("code", v)}
                                    autoFocus
                                >
                                    <InputOTPGroup>
                                        {Array.from({ length: 6 }, (_, i) => (
                                            <InputOTPSlot key={i} index={i} />
                                        ))}
                                    </InputOTPGroup>
                                </InputOTP>
                                <p className="text-meta text-ink-muted">
                                    Demo kod{" "}
                                    <span className="font-mono text-ink">
                                        {demo_code}
                                    </span>
                                    , istalgan 6 raqam qabul qilinadi
                                </p>
                                <InputError
                                    message={
                                        otp.errors.code ?? otp.errors.payment_id
                                    }
                                />
                            </div>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="lg"
                                    onClick={() => {
                                        setPrepared(null);
                                        otp.reset();
                                    }}
                                >
                                    Orqaga
                                </Button>
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="flex-1"
                                    disabled={
                                        otp.processing ||
                                        otp.data.code.length !== 6
                                    }
                                >
                                    {otp.processing && <Spinner />}
                                    {formatMoney(amount)} to'lash
                                </Button>
                            </div>
                        </form>
                    )}

                    <div className="flex items-center justify-between gap-3 border-t px-6 py-3 text-meta text-ink-muted">
                        <span className="tabular-nums">
                            Joriy balans {formatMoney(balance)}
                        </span>
                        <Link
                            href={billingIndex()}
                            className="focus-ring flex items-center gap-1 rounded-sm transition-colors hover:text-ink"
                        >
                            <ArrowLeft
                                className="size-4"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                            Bekor qilish
                        </Link>
                    </div>
                </section>
            </div>
        </>
    );
}

Checkout.layout = {
    breadcrumbs: [
        { title: "Tarif va balans", href: billingIndex() },
        { title: "To'lov", href: checkoutRoute() },
    ],
};

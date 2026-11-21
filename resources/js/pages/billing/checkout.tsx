import { Head, Link, router, useHttp } from "@inertiajs/react";
import { REGEXP_ONLY_DIGITS } from "input-otp";
import { ArrowLeft, CreditCard, Lock } from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";
import InputError from "@/components/input-error";
import { Badge } from "@/components/ui/badge";
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
import { formatNumber } from "@/components/brand/money";

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

const nf = { format: (n: number) => formatNumber(n) };

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
            <div className="flex flex-1 items-start justify-center">
                <div className="w-full max-w-md overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="flex items-center justify-between border-b px-5 py-4">
                        <div className="flex items-center gap-2">
                            <CreditCard
                                className="size-5 text-muted-foreground"
                                aria-hidden
                            />
                            <span className="font-semibold">
                                {provider.name}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                to'lov sahifasi
                            </span>
                        </div>
                        <Badge
                            variant="outline"
                            className="border-amber-500 text-amber-700 dark:text-amber-400"
                        >
                            DEMO to'lov
                        </Badge>
                    </div>

                    <div className="flex items-baseline justify-between border-b bg-muted/30 px-5 py-4">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Qabul qiluvchi
                            </p>
                            <p className="text-sm font-medium">
                                LexLink — balansni to'ldirish
                            </p>
                        </div>
                        <p className="text-xl font-semibold tabular-nums">
                            {nf.format(amount)} so'm
                        </p>
                    </div>

                    {!prepared ? (
                        <form
                            onSubmit={submitCard}
                            className="flex flex-col gap-4 px-5 py-5"
                        >
                            <div className="grid gap-2">
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
                                    className="font-mono tabular-nums"
                                />
                                <InputError message={card.errors.card_number} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
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
                                        className="font-mono tabular-nums"
                                    />
                                    <InputError message={card.errors.expiry} />
                                </div>
                            </div>
                            <InputError message={card.errors.amount} />
                            <p className="text-xs text-muted-foreground">
                                Test karta oldindan to'ldirilgan. Haqiqiy pul
                                yechilmaydi.
                            </p>
                            <Button type="submit" disabled={card.processing}>
                                {card.processing && <Spinner />}
                                SMS kod olish
                            </Button>
                        </form>
                    ) : (
                        <form
                            onSubmit={submitOtp}
                            className="flex flex-col gap-4 px-5 py-5"
                        >
                            <div className="text-sm">
                                <p>
                                    Karta:{" "}
                                    <span className="font-mono">
                                        {prepared.card_masked}
                                    </span>
                                </p>
                                <p className="text-muted-foreground">
                                    {prepared.phone_masked} raqamiga SMS kod
                                    yuborildi.
                                </p>
                            </div>
                            <div className="grid gap-2">
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
                                <p className="text-xs text-muted-foreground">
                                    Demo kod:{" "}
                                    <span className="font-mono">
                                        {demo_code}
                                    </span>{" "}
                                    (istalgan 6 raqam qabul qilinadi)
                                </p>
                                <InputError
                                    message={
                                        otp.errors.code ?? otp.errors.payment_id
                                    }
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setPrepared(null);
                                        otp.reset();
                                    }}
                                >
                                    Orqaga
                                </Button>
                                <Button
                                    type="submit"
                                    className="flex-1"
                                    disabled={
                                        otp.processing ||
                                        otp.data.code.length !== 6
                                    }
                                >
                                    {otp.processing && <Spinner />}
                                    {nf.format(amount)} so'm to'lash
                                </Button>
                            </div>
                        </form>
                    )}

                    <div className="flex items-center justify-between border-t px-5 py-3 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <Lock className="size-3.5" aria-hidden />
                            Joriy balans: {nf.format(balance)} so'm
                        </span>
                        <Link
                            href={billingIndex()}
                            className="flex items-center gap-1 hover:text-foreground"
                        >
                            <ArrowLeft className="size-3.5" aria-hidden />
                            Bekor qilish
                        </Link>
                    </div>
                </div>
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

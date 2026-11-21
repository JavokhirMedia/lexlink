import { Head, Link, router, usePage } from "@inertiajs/react";
import { Check, Info, Wallet } from "lucide-react";
import { useState } from "react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import {
    checkout,
    index as billingIndex,
    plan as planRoute,
} from "@/routes/billing";
import type { Plan } from "@/types/lexlink";
import { formatDate, formatDateTime, formatMoney, formatNumber } from "@/components/brand/money";

type TransactionRow = {
    id: number;
    type: "topup" | "charge" | "refund" | "subscription" | "ai";
    type_label: string;
    amount: number;
    balance_after: number;
    provider: string | null;
    status: string;
    description: string | null;
    data_request_id: number | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    plans: Plan[];
    current_plan_code: Plan["code"] | null;
    usage: {
        used_requests: number;
        included_requests: number;
        used_ai: number;
        included_ai: number;
        period_end: string | null;
    };
    balance: number;
    quote: {
        cost: number;
        from_subscription: boolean;
        allowed: boolean;
        reason: string | null;
    };
    ai_extra_price: number;
    transactions: Paginated<TransactionRow>;
    topup: { min: number; max: number; presets: number[] };
};

const nf = { format: (n: number) => formatNumber(n) };
const som = (v: number) => formatMoney(v);
const dateTime = (iso: string) => formatDateTime(iso);
const date = (iso: string) => formatDate(iso);

export default function BillingIndex({
    plans,
    current_plan_code,
    usage,
    balance,
    quote,
    ai_extra_price,
    transactions,
    topup,
}: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const currentPlan = plans.find((p) => p.code === current_plan_code) ?? null;

    return (
        <>
            <Head title="Tarif va balans" />
            <div className="flex w-full flex-col gap-8">
                <header className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Tarif va balans
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Obuna, balans va to'lovlar tarixi. Barcha summalar
                        so'mda.
                    </p>
                </header>

                <Alert>
                    <Info />
                    <AlertDescription className="text-foreground">
                        Javobni ko'rish uchun pul olinmaydi. To'lov faqat xizmat
                        uchun: obuna, so'rov yuborish va AI. Idora so'rovni rad
                        etsa yoki texnik xato bo'lsa, mablag' avtomatik
                        qaytariladi.
                    </AlertDescription>
                </Alert>

                {errors.billing && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.billing}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
                    <UsagePanel
                        currentPlan={currentPlan}
                        usage={usage}
                        quote={quote}
                        aiExtra={ai_extra_price}
                    />
                    <TopupPanel balance={balance} topup={topup} />
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="text-base font-semibold">Tariflar</h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        {plans.map((p) => (
                            <PlanCard
                                key={p.id}
                                plan={p}
                                current={p.code === current_plan_code}
                                balance={balance}
                            />
                        ))}
                    </div>
                </section>

                <TransactionsTable transactions={transactions} />
            </div>
        </>
    );
}

function UsagePanel({
    currentPlan,
    usage,
    quote,
    aiExtra,
}: {
    currentPlan: Plan | null;
    usage: Props["usage"];
    quote: Props["quote"];
    aiExtra: number;
}) {
    const pct =
        usage.included_requests > 0
            ? Math.min(
                  100,
                  (usage.used_requests / usage.included_requests) * 100,
              )
            : 0;
    const exhausted =
        usage.included_requests > 0 &&
        usage.used_requests >= usage.included_requests;

    return (
        <section className="flex flex-col gap-4 rounded-lg border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm text-muted-foreground">Joriy tarif</p>
                    <p className="text-lg font-semibold">
                        {currentPlan?.name ?? "Tanlanmagan"}
                    </p>
                </div>
                {usage.period_end && (
                    <p className="text-right text-xs text-muted-foreground">
                        Davr tugashi
                        <br />
                        <span className="text-foreground">
                            {date(usage.period_end)}
                        </span>
                    </p>
                )}
            </div>

            <div className="flex flex-col gap-2">
                <div className="flex items-baseline justify-between text-sm">
                    <span>
                        Bu oy:{" "}
                        <span className="font-semibold tabular-nums">
                            {usage.used_requests} / {usage.included_requests}
                        </span>{" "}
                        so'rov
                    </span>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        AI: {usage.used_ai} / {usage.included_ai}
                    </span>
                </div>
                <div
                    className="h-2 w-full overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={usage.included_requests}
                    aria-valuenow={usage.used_requests}
                >
                    <div
                        className={cn(
                            "h-full rounded-full",
                            exhausted ? "bg-amber-600" : "bg-primary",
                        )}
                        style={{ width: `${pct}%` }}
                    />
                </div>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 border-t pt-4 text-sm">
                <dt className="text-muted-foreground">Keyingi so'rov narxi</dt>
                <dd className="text-right font-medium tabular-nums">
                    {!quote.allowed && quote.cost === 0
                        ? "Ruxsat yo'q"
                        : quote.from_subscription
                          ? "Obuna doirasida"
                          : som(quote.cost)}
                </dd>
                <dt className="text-muted-foreground">
                    Limitdan tashqari so'rov
                </dt>
                <dd className="text-right tabular-nums">
                    {currentPlan?.extra_price != null
                        ? som(currentPlan.extra_price)
                        : "—"}
                </dd>
                <dt className="text-muted-foreground">Qo'shimcha AI xizmat</dt>
                <dd className="text-right tabular-nums">{som(aiExtra)}</dd>
            </dl>

            {quote.reason && (
                <p className="text-sm text-amber-700 dark:text-amber-400">
                    {quote.reason}
                </p>
            )}
        </section>
    );
}

function TopupPanel({
    balance,
    topup,
}: {
    balance: number;
    topup: Props["topup"];
}) {
    const [amount, setAmount] = useState<number>(topup.presets[1] ?? topup.min);
    const [custom, setCustom] = useState("");
    const [provider, setProvider] = useState<"click" | "payme">("click");

    const value = custom !== "" ? Number(custom.replace(/\D/g, "")) : amount;
    const valid =
        Number.isFinite(value) && value >= topup.min && value <= topup.max;

    return (
        <section className="flex flex-col gap-4 rounded-lg border bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm text-muted-foreground">Balans</p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {som(balance)}
                    </p>
                </div>
                <Wallet className="size-5 text-muted-foreground" aria-hidden />
            </div>

            <div className="flex flex-col gap-3 border-t pt-4">
                <p className="text-sm font-medium">Balansni to'ldirish</p>
                <div className="flex flex-wrap gap-2">
                    {topup.presets.map((p) => (
                        <Button
                            key={p}
                            type="button"
                            size="sm"
                            variant={
                                custom === "" && amount === p
                                    ? "default"
                                    : "outline"
                            }
                            onClick={() => {
                                setAmount(p);
                                setCustom("");
                            }}
                            className="tabular-nums"
                        >
                            {nf.format(p)}
                        </Button>
                    ))}
                    <Input
                        inputMode="numeric"
                        placeholder="Boshqa summa"
                        aria-label="Boshqa summa"
                        value={custom}
                        onChange={(e) =>
                            setCustom(e.target.value.replace(/[^\d\s]/g, ""))
                        }
                        className="h-8 w-36 tabular-nums"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        To'lov tizimi:
                    </span>
                    {(["click", "payme"] as const).map((p) => (
                        <Button
                            key={p}
                            type="button"
                            size="sm"
                            variant={provider === p ? "secondary" : "ghost"}
                            onClick={() => setProvider(p)}
                            aria-pressed={provider === p}
                        >
                            {p === "click" ? "Click" : "Payme"}
                        </Button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-muted-foreground">
                        {nf.format(topup.min)} – {nf.format(topup.max)} so'm
                    </p>
                    {valid ? (
                        <Button asChild>
                            <Link
                                href={checkout({
                                    query: { provider, amount: value },
                                })}
                            >
                                {som(value)} to'lash
                            </Link>
                        </Button>
                    ) : (
                        <Button disabled>To'lash</Button>
                    )}
                </div>
            </div>
        </section>
    );
}

function PlanCard({
    plan,
    current,
    balance,
}: {
    plan: Plan;
    current: boolean;
    balance: number;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const enough = balance >= plan.price;

    const activate = () => {
        router.post(
            planRoute.url(),
            { plan: plan.code },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <article
            className={cn(
                "flex flex-col gap-4 rounded-lg border bg-card p-5",
                current && "border-primary ring-1 ring-primary",
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h3 className="text-base font-semibold">{plan.name}</h3>
                    <p className="text-sm text-muted-foreground">
                        {plan.audience}
                    </p>
                </div>
                {current && <Badge>Joriy tarif</Badge>}
            </div>

            <p className="text-2xl font-semibold tabular-nums">
                {plan.price > 0 ? (
                    <>
                        {nf.format(plan.price)}{" "}
                        <span className="text-sm font-normal text-muted-foreground">
                            so'm/oy
                        </span>
                    </>
                ) : (
                    "Bepul"
                )}
            </p>

            <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1.5 text-sm">
                <dt className="text-muted-foreground">Oyiga so'rovlar</dt>
                <dd className="text-right font-medium tabular-nums">
                    {plan.included_requests}
                </dd>
                <dt className="text-muted-foreground">Qo'shimcha so'rov</dt>
                <dd className="text-right tabular-nums">
                    {plan.extra_price != null
                        ? som(plan.extra_price)
                        : "mavjud emas"}
                </dd>
                <dt className="text-muted-foreground">AI xizmatlar</dt>
                <dd className="text-right tabular-nums">{plan.included_ai}</dd>
            </dl>

            {plan.features && plan.features.length > 0 && (
                <ul className="flex flex-col gap-1.5 border-t pt-3 text-sm">
                    {plan.features.map((f) => (
                        <li key={f} className="flex gap-2">
                            <Check
                                className="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden
                            />
                            <span>{f}</span>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-auto pt-1">
                {current ? (
                    <Button variant="outline" className="w-full" disabled>
                        Faol
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={() => setOpen(true)}
                    >
                        Tarifga o'tish
                    </Button>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>"{plan.name}" tarifiga o'tish</DialogTitle>
                        <DialogDescription>
                            {plan.price > 0
                                ? `Balansingizdan ${som(plan.price)} yechiladi. Yangi tarif darhol faollashadi, oylik limit bugundan boshlab hisoblanadi.`
                                : "Bepul tarif darhol faollashadi. Start tarifida so'rov yuborib bo'lmaydi — faqat loyiha tayyorlash."}
                        </DialogDescription>
                    </DialogHeader>
                    <dl className="grid grid-cols-2 gap-y-1 text-sm">
                        <dt className="text-muted-foreground">Joriy balans</dt>
                        <dd className="text-right tabular-nums">
                            {som(balance)}
                        </dd>
                        <dt className="text-muted-foreground">
                            To'lovdan keyin
                        </dt>
                        <dd
                            className={cn(
                                "text-right font-medium tabular-nums",
                                !enough && "text-destructive",
                            )}
                        >
                            {som(balance - plan.price)}
                        </dd>
                    </dl>
                    {!enough && (
                        <p className="text-sm text-destructive">
                            Balans yetarli emas. Avval balansni to'ldiring.
                        </p>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Bekor qilish</Button>
                        </DialogClose>
                        <Button
                            onClick={activate}
                            disabled={!enough || processing}
                        >
                            {plan.price > 0
                                ? `${som(plan.price)} to'lash va faollashtirish`
                                : "Faollashtirish"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </article>
    );
}

function TransactionsTable({
    transactions,
}: {
    transactions: Paginated<TransactionRow>;
}) {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between">
                <h2 className="text-base font-semibold">To'lovlar tarixi</h2>
                <span className="text-xs text-muted-foreground">
                    Jami: {transactions.total}
                </span>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2 font-medium">Sana</th>
                            <th className="px-4 py-2 font-medium">Turi</th>
                            <th className="px-4 py-2 font-medium">Izoh</th>
                            <th className="px-4 py-2 text-right font-medium">
                                Summa
                            </th>
                            <th className="px-4 py-2 text-right font-medium">
                                Qoldiq
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {transactions.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Hozircha to'lovlar yo'q.
                                </td>
                            </tr>
                        )}
                        {transactions.data.map((t) => (
                            <tr key={t.id} className="border-b last:border-0">
                                <td className="px-4 py-2 whitespace-nowrap text-muted-foreground tabular-nums">
                                    {dateTime(t.created_at)}
                                </td>
                                <td className="px-4 py-2 whitespace-nowrap">
                                    <Badge variant="outline">
                                        {t.type_label}
                                    </Badge>
                                </td>
                                <td className="px-4 py-2">
                                    {t.description}
                                    {t.data_request_id && (
                                        <Link
                                            href={`/requests/${t.data_request_id}`}
                                            className="ml-2 text-xs text-primary hover:underline"
                                        >
                                            #{t.data_request_id}
                                        </Link>
                                    )}
                                </td>
                                <td
                                    className={cn(
                                        "px-4 py-2 text-right whitespace-nowrap tabular-nums",
                                        t.amount > 0 &&
                                            "text-emerald-700 dark:text-emerald-400",
                                    )}
                                >
                                    {t.amount > 0 ? "+" : ""}
                                    {t.amount === 0
                                        ? "obuna"
                                        : nf.format(t.amount)}
                                </td>
                                <td className="px-4 py-2 text-right whitespace-nowrap tabular-nums">
                                    {nf.format(t.balance_after)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {transactions.last_page > 1 && (
                <div className="flex items-center justify-end gap-2 text-sm">
                    <span className="text-muted-foreground">
                        {transactions.current_page} / {transactions.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!transactions.prev_page_url}
                        asChild={!!transactions.prev_page_url}
                    >
                        {transactions.prev_page_url ? (
                            <Link
                                href={transactions.prev_page_url}
                                preserveScroll
                            >
                                Oldingi
                            </Link>
                        ) : (
                            <span>Oldingi</span>
                        )}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!transactions.next_page_url}
                        asChild={!!transactions.next_page_url}
                    >
                        {transactions.next_page_url ? (
                            <Link
                                href={transactions.next_page_url}
                                preserveScroll
                            >
                                Keyingi
                            </Link>
                        ) : (
                            <span>Keyingi</span>
                        )}
                    </Button>
                </div>
            )}
        </section>
    );
}

BillingIndex.layout = {
    breadcrumbs: [{ title: "Tarif va balans", href: billingIndex() }],
};

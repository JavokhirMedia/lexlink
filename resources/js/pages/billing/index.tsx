import { Head, Link, router, usePage } from "@inertiajs/react";
import { Check, Info, Receipt } from "lucide-react";
import { useState } from "react";
import { EmptyState } from "@/components/brand/empty-state";
import {
    Money,
    formatDate,
    formatDateTime,
    formatMoney,
    formatNumber,
} from "@/components/brand/money";
import { PageHeader } from "@/components/brand/page-header";
import { Section } from "@/components/brand/section";
import { Alert, AlertDescription } from "@/components/ui/alert";
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { cn } from "@/lib/utils";
import {
    checkout,
    index as billingIndex,
    plan as planRoute,
} from "@/routes/billing";
import type { Plan } from "@/types/lexlink";

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
            <div className="flex flex-col gap-10">
                <PageHeader
                    title="Tarif va balans"
                    description="Obuna, balans va to'lovlar tarixi. Barcha summalar so'mda"
                />

                <div className="flex flex-col gap-4">
                    <p className="flex items-start gap-3 rounded-lg bg-raised px-4 py-3 text-sm text-ink-secondary">
                        <Info
                            aria-hidden
                            strokeWidth={1.5}
                            className="mt-0.5 size-4 shrink-0 text-ink-muted"
                        />
                        Javobni ko'rish uchun pul olinmaydi. To'lov faqat xizmat
                        uchun: obuna, so'rov yuborish va AI. Idora so'rovni rad
                        etsa yoki texnik xato bo'lsa, mablag' avtomatik
                        qaytariladi.
                    </p>

                    {errors.billing && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {errors.billing}
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                        <UsagePanel
                            currentPlan={currentPlan}
                            usage={usage}
                            quote={quote}
                            aiExtra={ai_extra_price}
                        />
                        <TopupPanel balance={balance} topup={topup} />
                    </div>
                </div>

                <Section
                    title="Tariflar"
                    description="Tarif darhol faollashadi, to'lov balansdan yechiladi"
                >
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
                </Section>

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
        <section
            aria-label="Joriy tarif"
            className="flex flex-col gap-6 rounded-lg border bg-card p-6 shadow-xs"
        >
            <div className="flex items-start justify-between gap-4">
                <div className="flex flex-col gap-1">
                    <p className="text-sm text-ink-secondary">Joriy tarif</p>
                    <p className="text-section-title text-ink">
                        {currentPlan?.name ?? "Tanlanmagan"}
                    </p>
                </div>
                {usage.period_end && (
                    <p className="flex flex-col items-end text-meta text-ink-muted">
                        Davr tugashi
                        <span className="text-sm text-ink tabular-nums">
                            {formatDate(usage.period_end)}
                        </span>
                    </p>
                )}
            </div>

            <div className="flex flex-col gap-2">
                <div className="flex items-baseline justify-between gap-4 text-sm">
                    <span className="text-ink-secondary">
                        Bu oy{" "}
                        <span className="font-semibold text-ink tabular-nums">
                            {formatNumber(usage.used_requests)} /{" "}
                            {formatNumber(usage.included_requests)}
                        </span>{" "}
                        so'rov
                    </span>
                    <span className="text-meta text-ink-muted tabular-nums">
                        AI {formatNumber(usage.used_ai)} /{" "}
                        {formatNumber(usage.included_ai)}
                    </span>
                </div>
                <div
                    className="h-2 w-full overflow-hidden rounded-full bg-raised"
                    role="progressbar"
                    aria-label="Oylik so'rovlar"
                    aria-valuemin={0}
                    aria-valuemax={usage.included_requests}
                    aria-valuenow={usage.used_requests}
                >
                    <div
                        className={cn(
                            "h-full rounded-full",
                            exhausted ? "bg-warning" : "bg-brand",
                        )}
                        style={{ width: `${pct}%` }}
                    />
                </div>
            </div>

            <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 border-t pt-4 text-sm">
                <dt className="text-ink-secondary">Keyingi so'rov narxi</dt>
                <dd className="text-right font-medium text-ink tabular-nums">
                    {!quote.allowed && quote.cost === 0
                        ? "Ruxsat yo'q"
                        : quote.from_subscription
                          ? "Obuna doirasida"
                          : formatMoney(quote.cost)}
                </dd>
                <dt className="text-ink-secondary">Limitdan tashqari so'rov</dt>
                <dd className="text-right text-ink tabular-nums">
                    {currentPlan?.extra_price != null
                        ? formatMoney(currentPlan.extra_price)
                        : "—"}
                </dd>
                <dt className="text-ink-secondary">Qo'shimcha AI xizmat</dt>
                <dd className="text-right text-ink tabular-nums">
                    {formatMoney(aiExtra)}
                </dd>
            </dl>

            {quote.reason && (
                <p className="rounded-md bg-warning-soft px-3 py-2 text-sm text-warning">
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
        <section
            aria-label="Balans"
            className="flex flex-col gap-6 rounded-lg border bg-card p-6 shadow-xs"
        >
            <div className="flex flex-col gap-1">
                <p className="text-sm text-ink-secondary">Balans</p>
                <p className="text-[28px] leading-[34px] font-semibold text-ink tabular-nums">
                    {formatMoney(balance)}
                </p>
            </div>

            <div className="flex flex-col gap-4 border-t pt-4">
                <p className="text-sm font-medium text-ink">
                    Balansni to'ldirish
                </p>
                <div
                    className={cn(
                        "grid gap-2",
                        topup.presets.length % 3 === 0
                            ? "grid-cols-3"
                            : "grid-cols-2 sm:grid-cols-4",
                    )}
                    role="radiogroup"
                    aria-label="Summa"
                >
                    {topup.presets.map((p) => {
                        const active = custom === "" && amount === p;

                        return (
                            <button
                                key={p}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                onClick={() => {
                                    setAmount(p);
                                    setCustom("");
                                }}
                                className={cn(
                                    "focus-ring h-10 rounded-md border text-sm font-medium tabular-nums transition-colors",
                                    active
                                        ? "border-brand bg-brand-soft text-brand"
                                        : "border-input bg-surface text-ink hover:bg-raised",
                                )}
                            >
                                {formatNumber(p)}
                            </button>
                        );
                    })}
                </div>
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        inputMode="numeric"
                        placeholder="Boshqa summa"
                        aria-label="Boshqa summa"
                        value={custom}
                        onChange={(e) =>
                            setCustom(e.target.value.replace(/[^\d\s]/g, ""))
                        }
                        className="tabular-nums sm:w-48"
                    />
                    <p className="text-meta text-ink-muted tabular-nums">
                        {formatNumber(topup.min)} dan {formatMoney(topup.max)}{" "}
                        gacha
                    </p>
                </div>

                <div className="flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div
                        className="inline-flex w-fit rounded-md bg-raised p-1"
                        role="radiogroup"
                        aria-label="To'lov tizimi"
                    >
                        {(["click", "payme"] as const).map((p) => (
                            <button
                                key={p}
                                type="button"
                                role="radio"
                                aria-checked={provider === p}
                                onClick={() => setProvider(p)}
                                className={cn(
                                    "focus-ring h-8 rounded-sm px-4 text-sm font-medium transition-colors",
                                    provider === p
                                        ? "bg-surface text-ink shadow-xs"
                                        : "text-ink-secondary hover:text-ink",
                                )}
                            >
                                {p === "click" ? "Click" : "Payme"}
                            </button>
                        ))}
                    </div>
                    {valid ? (
                        <Button asChild>
                            <Link
                                href={checkout({
                                    query: { provider, amount: value },
                                })}
                            >
                                {formatMoney(value)} to'lash
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
            aria-current={current ? "true" : undefined}
            className={cn(
                "flex flex-col gap-6 rounded-lg border bg-card p-6 shadow-xs",
                current && "border-brand ring-1 ring-brand",
            )}
        >
            <div className="flex flex-col gap-1">
                <div className="flex items-center justify-between gap-2">
                    <h3 className="text-lg font-semibold text-ink">
                        {plan.name}
                    </h3>
                    {current && (
                        <span className="text-sm font-medium text-brand">
                            Joriy tarif
                        </span>
                    )}
                </div>
                <p className="text-sm text-ink-secondary">{plan.audience}</p>
            </div>

            <p className="text-[28px] leading-[34px] font-semibold text-ink tabular-nums">
                {plan.price > 0 ? (
                    <>
                        {formatNumber(plan.price)}
                        <span className="text-sm font-normal text-ink-secondary">
                            {" "}
                            so'm / oy
                        </span>
                    </>
                ) : (
                    "Bepul"
                )}
            </p>

            <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-2 text-sm">
                <dt className="text-ink-secondary">Oyiga so'rovlar</dt>
                <dd className="text-right font-medium text-ink tabular-nums">
                    {formatNumber(plan.included_requests)}
                </dd>
                <dt className="text-ink-secondary">Qo'shimcha so'rov</dt>
                <dd className="text-right text-ink tabular-nums">
                    {plan.extra_price != null
                        ? formatMoney(plan.extra_price)
                        : "mavjud emas"}
                </dd>
                <dt className="text-ink-secondary">AI xizmatlar</dt>
                <dd className="text-right text-ink tabular-nums">
                    {formatNumber(plan.included_ai)}
                </dd>
            </dl>

            {plan.features && plan.features.length > 0 && (
                <ul className="flex flex-col gap-2 border-t pt-4 text-sm text-ink">
                    {plan.features.map((f) => (
                        <li key={f} className="flex gap-2">
                            <Check
                                className="mt-0.5 size-4 shrink-0 text-ink-muted"
                                strokeWidth={1.5}
                                aria-hidden
                            />
                            <span>{f.replace(/\.$/, "")}</span>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-auto">
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
                        <DialogTitle>“{plan.name}” tarifiga o'tish</DialogTitle>
                        <DialogDescription>
                            {plan.price > 0
                                ? `Balansingizdan ${formatMoney(plan.price)} yechiladi. Yangi tarif darhol faollashadi, oylik limit bugundan boshlab hisoblanadi.`
                                : "Bepul tarif darhol faollashadi. Start tarifida so'rov yuborib bo'lmaydi, faqat loyiha tayyorlanadi."}
                        </DialogDescription>
                    </DialogHeader>
                    <dl className="grid grid-cols-2 gap-y-2 text-sm">
                        <dt className="text-ink-secondary">Joriy balans</dt>
                        <dd className="text-right tabular-nums">
                            {formatMoney(balance)}
                        </dd>
                        <dt className="text-ink-secondary">To'lovdan keyin</dt>
                        <dd
                            className={cn(
                                "text-right font-medium tabular-nums",
                                !enough && "text-danger",
                            )}
                        >
                            {formatMoney(balance - plan.price)}
                        </dd>
                    </dl>
                    {!enough && (
                        <p className="text-sm text-danger">
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
                                ? `${formatMoney(plan.price)} to'lash va faollashtirish`
                                : "Faollashtirish"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </article>
    );
}

function TxAmount({ t }: { t: TransactionRow }) {
    return t.amount === 0 ? (
        <span className="text-ink-secondary">obuna</span>
    ) : (
        <Money amount={t.amount} signed />
    );
}

function TxDescription({ t }: { t: TransactionRow }) {
    return (
        <>
            {t.description}
            {t.data_request_id && (
                <Link
                    href={`/requests/${t.data_request_id}`}
                    className="focus-ring ml-2 rounded-sm text-meta text-brand hover:underline"
                >
                    so'rov #{t.data_request_id}
                </Link>
            )}
        </>
    );
}

function TransactionsTable({
    transactions,
}: {
    transactions: Paginated<TransactionRow>;
}) {
    return (
        <Section
            title="To'lovlar tarixi"
            description={
                transactions.total > 0
                    ? `Jami ${formatNumber(transactions.total)} ta yozuv`
                    : undefined
            }
        >
            {transactions.data.length === 0 ? (
                <EmptyState icon={Receipt} title="Hozircha to'lovlar yo'q" />
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Sana</TableHead>
                                    <TableHead>Turi</TableHead>
                                    <TableHead>Izoh</TableHead>
                                    <TableHead className="text-right">
                                        Summa
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Qoldiq
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.data.map((t) => (
                                    <TableRow key={t.id}>
                                        <TableCell className="whitespace-nowrap text-ink-secondary">
                                            {formatDateTime(t.created_at)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {t.type_label}
                                        </TableCell>
                                        <TableCell className="text-ink-secondary">
                                            <TxDescription t={t} />
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <TxAmount t={t} />
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap text-ink-secondary">
                                            <Money amount={t.balance_after} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <ul className="divide-y overflow-hidden rounded-lg border bg-card md:hidden">
                        {transactions.data.map((t) => (
                            <li
                                key={t.id}
                                className="flex flex-col gap-1 px-4 py-3"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="text-base font-medium text-ink">
                                        {t.type_label}
                                    </span>
                                    <span className="text-base font-medium">
                                        <TxAmount t={t} />
                                    </span>
                                </div>
                                {t.description && (
                                    <p className="text-sm text-ink-secondary">
                                        <TxDescription t={t} />
                                    </p>
                                )}
                                <p className="flex justify-between gap-3 text-meta text-ink-muted tabular-nums">
                                    <span>{formatDateTime(t.created_at)}</span>
                                    <span>
                                        Qoldiq{" "}
                                        <Money amount={t.balance_after} />
                                    </span>
                                </p>
                            </li>
                        ))}
                    </ul>

                    {transactions.last_page > 1 && (
                        <div className="flex items-center justify-end gap-2 text-sm">
                            <span className="text-ink-muted tabular-nums">
                                {transactions.current_page} /{" "}
                                {transactions.last_page}
                            </span>
                            <PageButton
                                url={transactions.prev_page_url}
                                label="Oldingi"
                            />
                            <PageButton
                                url={transactions.next_page_url}
                                label="Keyingi"
                            />
                        </div>
                    )}
                </div>
            )}
        </Section>
    );
}

function PageButton({ url, label }: { url: string | null; label: string }) {
    return url ? (
        <Button variant="outline" size="sm" asChild>
            <Link href={url} preserveScroll>
                {label}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="sm" disabled>
            {label}
        </Button>
    );
}

BillingIndex.layout = {
    breadcrumbs: [{ title: "Tarif va balans", href: billingIndex() }],
};

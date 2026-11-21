import { Head, Link, useForm } from "@inertiajs/react";
import { Plus, Trash2 } from "lucide-react";
import type { FormEvent, ReactNode } from "react";
import { PageHeader } from "@/components/brand/page-header";
import { Section } from "@/components/brand/section";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
    create as casesCreate,
    index as casesIndex,
    store as casesStore,
} from "@/routes/cases";

type Props = {
    categories: { value: string; label: string }[];
    advocates: { id: number; name: string }[];
    is_lawyer: boolean;
};

type Party = { role: string; name: string };

const PARTY_ROLES = ["Da'vogar", "Javobgar", "Uchinchi shaxs"];

export default function CasesCreate({
    categories,
    advocates,
    is_lawyer,
}: Props) {
    const form = useForm<{
        title: string;
        client_name: string;
        court_case_number: string;
        category: string;
        description: string;
        parties: Party[];
        advocate_id: string;
    }>({
        title: "",
        client_name: "",
        court_case_number: "",
        category: "",
        description: "",
        parties: [],
        advocate_id: advocates.length === 1 ? String(advocates[0].id) : "",
    });
    const { data, setData, errors, processing } = form;
    const errorFor = (key: string) =>
        (errors as Record<string, string | undefined>)[key];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.submit(casesStore());
    };

    const setParty = (i: number, patch: Partial<Party>) =>
        setData(
            "parties",
            data.parties.map((p, idx) => (idx === i ? { ...p, ...patch } : p)),
        );

    const noAdvocate = is_lawyer && advocates.length === 0;

    return (
        <>
            <Head title="Yangi ish" />
            <div className="flex w-full max-w-3xl flex-col gap-8">
                <PageHeader
                    title="Yangi ish"
                    description="Ish ma'lumotlarini kiriting. Huquqiy asoslar va so'rovlar ish sahifasida qo'shiladi"
                />

                {noAdvocate && (
                    <div
                        role="alert"
                        className="rounded-lg border border-warning-border bg-warning-soft px-4 py-3 text-sm text-ink"
                    >
                        Ish faqat advokatga biriktiriladi. Siz hali hech bir
                        advokat jamoasiga qo'shilmagansiz, advokatdan taklif
                        havolasini so'rang
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-10">
                    <Section
                        title="Ish ma'lumotlari"
                        card
                        contentClassName="flex flex-col gap-6"
                    >
                        <Field label="Ish nomi" htmlFor="title" error={errors.title}>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) =>
                                    setData("title", e.target.value)
                                }
                                placeholder="Masalan: Aliment undirish — Karimova N."
                                required
                            />
                        </Field>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field
                                label="Mijoz"
                                htmlFor="client_name"
                                error={errors.client_name}
                            >
                                <Input
                                    id="client_name"
                                    value={data.client_name}
                                    onChange={(e) =>
                                        setData("client_name", e.target.value)
                                    }
                                    placeholder="F.I.Sh."
                                    required
                                />
                            </Field>
                            <Field
                                label="Sud ishi raqami"
                                optional
                                htmlFor="court_case_number"
                                error={errors.court_case_number}
                            >
                                <Input
                                    id="court_case_number"
                                    value={data.court_case_number}
                                    onChange={(e) =>
                                        setData(
                                            "court_case_number",
                                            e.target.value,
                                        )
                                    }
                                    placeholder="2-1001-2611/0457"
                                    className="font-mono"
                                />
                            </Field>
                        </div>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field
                                label="Toifa"
                                htmlFor="category"
                                error={errors.category}
                            >
                                <Select
                                    value={data.category}
                                    onValueChange={(v) =>
                                        setData("category", v)
                                    }
                                >
                                    <SelectTrigger
                                        id="category"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Toifani tanlang" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem
                                                key={c.value}
                                                value={c.value}
                                            >
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

                            {is_lawyer && (
                                <Field
                                    label="Mas'ul advokat"
                                    htmlFor="advocate_id"
                                    error={errors.advocate_id}
                                    hint="Ish egasi advokat bo'ladi, siz ishga yurist sifatida qo'shilasiz"
                                >
                                    <Select
                                        value={data.advocate_id}
                                        onValueChange={(v) =>
                                            setData("advocate_id", v)
                                        }
                                        disabled={noAdvocate}
                                    >
                                        <SelectTrigger
                                            id="advocate_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Advokatni tanlang" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {advocates.map((a) => (
                                                <SelectItem
                                                    key={a.id}
                                                    value={String(a.id)}
                                                >
                                                    {a.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            )}
                        </div>

                        <Field
                            label="Tavsif"
                            optional
                            htmlFor="description"
                            error={errors.description}
                        >
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData("description", e.target.value)
                                }
                                rows={4}
                                placeholder="Ishning qisqacha mazmuni"
                            />
                        </Field>
                    </Section>

                    <Section
                        title="Ish taraflari"
                        description="Da'vogar, javobgar va boshqa ishtirokchilar"
                        actions={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setData("parties", [
                                        ...data.parties,
                                        {
                                            role: PARTY_ROLES[
                                                Math.min(data.parties.length, 1)
                                            ],
                                            name: "",
                                        },
                                    ])
                                }
                            >
                                <Plus /> Taraf qo'shish
                            </Button>
                        }
                        card
                        contentClassName="flex flex-col gap-4"
                    >
                        {data.parties.length === 0 && (
                            <p className="text-sm text-ink-secondary">
                                Taraflar hali qo'shilmagan
                            </p>
                        )}
                        {data.parties.map((p, i) => (
                            <div
                                key={i}
                                className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 sm:grid-cols-[180px_minmax(0,1fr)_auto] sm:items-start"
                            >
                                <Select
                                    value={p.role}
                                    onValueChange={(v) =>
                                        setParty(i, { role: v })
                                    }
                                >
                                    <SelectTrigger
                                        aria-label="Taraf roli"
                                        className="col-span-2 w-full sm:col-span-1"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PARTY_ROLES.map((r) => (
                                            <SelectItem key={r} value={r}>
                                                {r}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <div className="flex flex-col gap-2">
                                    <Input
                                        value={p.name}
                                        onChange={(e) =>
                                            setParty(i, {
                                                name: e.target.value,
                                            })
                                        }
                                        placeholder="F.I.Sh. yoki tashkilot nomi"
                                        aria-label="Taraf nomi"
                                    />
                                    <InputError
                                        message={errorFor(`parties.${i}.name`)}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="size-11"
                                    size="icon"
                                    aria-label="Tarafni olib tashlash"
                                    onClick={() =>
                                        setData(
                                            "parties",
                                            data.parties.filter(
                                                (_, idx) => idx !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </Section>

                    <div className="flex flex-col-reverse gap-2 border-t pt-6 sm:flex-row sm:justify-end">
                        <Button asChild variant="ghost" size="lg" type="button">
                            <Link href={casesIndex()}>Bekor qilish</Link>
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || noAdvocate}
                        >
                            Ishni yaratish
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function Field({
    label,
    htmlFor,
    optional = false,
    hint,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    optional?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={htmlFor}>
                {label}
                {optional && (
                    <span className="ml-1 font-normal text-ink-muted">
                        ixtiyoriy
                    </span>
                )}
            </Label>
            {children}
            {hint && <p className="text-xs text-ink-secondary">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

CasesCreate.layout = {
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: "Yangi ish", href: casesCreate() },
    ],
};

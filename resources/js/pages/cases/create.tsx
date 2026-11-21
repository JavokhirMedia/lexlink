import { Head, Link, useForm } from "@inertiajs/react";
import { Plus, Trash2 } from "lucide-react";
import type { FormEvent } from "react";
import InputError from "@/components/input-error";
import { Alert, AlertDescription } from "@/components/ui/alert";
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
            <div className="w-full max-w-3xl">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Yangi ish
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Ish ma'lumotlarini kiriting. Huquqiy asoslar va
                        so'rovlar ish sahifasida qo'shiladi.
                    </p>
                </div>

                {noAdvocate && (
                    <Alert className="mb-6">
                        <AlertDescription>
                            Ish faqat advokatga biriktiriladi. Siz hali hech bir
                            advokat jamoasiga qo'shilmagansiz — advokatdan
                            taklif havolasini so'rang.
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="space-y-8">
                    <section className="space-y-4 rounded-lg border bg-card p-5">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Ish nomi</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) =>
                                    setData("title", e.target.value)
                                }
                                placeholder="Masalan: Aliment undirish — Karimova D."
                                required
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="client_name">Mijoz</Label>
                                <Input
                                    id="client_name"
                                    value={data.client_name}
                                    onChange={(e) =>
                                        setData("client_name", e.target.value)
                                    }
                                    placeholder="F.I.Sh."
                                    required
                                />
                                <InputError message={errors.client_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="court_case_number">
                                    Sud ishi raqami{" "}
                                    <span className="text-muted-foreground">
                                        (ixtiyoriy)
                                    </span>
                                </Label>
                                <Input
                                    id="court_case_number"
                                    value={data.court_case_number}
                                    onChange={(e) =>
                                        setData(
                                            "court_case_number",
                                            e.target.value,
                                        )
                                    }
                                    placeholder="2-1234/2026"
                                    className="font-mono"
                                />
                                <InputError
                                    message={errors.court_case_number}
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Toifa</Label>
                                <Select
                                    value={data.category}
                                    onValueChange={(v) =>
                                        setData("category", v)
                                    }
                                >
                                    <SelectTrigger aria-label="Toifa">
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
                                <InputError message={errors.category} />
                            </div>

                            {is_lawyer && (
                                <div className="grid gap-2">
                                    <Label>Mas'ul advokat</Label>
                                    <Select
                                        value={data.advocate_id}
                                        onValueChange={(v) =>
                                            setData("advocate_id", v)
                                        }
                                        disabled={noAdvocate}
                                    >
                                        <SelectTrigger aria-label="Mas'ul advokat">
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
                                    <p className="text-xs text-muted-foreground">
                                        Ish egasi advokat bo'ladi; siz ishga
                                        yurist sifatida qo'shilasiz.
                                    </p>
                                    <InputError message={errors.advocate_id} />
                                </div>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Tavsif</Label>
                            <textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData("description", e.target.value)
                                }
                                rows={4}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                placeholder="Ishning qisqacha mazmuni"
                            />
                            <InputError message={errors.description} />
                        </div>
                    </section>

                    <section className="space-y-4 rounded-lg border bg-card p-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="font-medium">Ish taraflari</h2>
                                <p className="text-sm text-muted-foreground">
                                    Da'vogar, javobgar va boshqa ishtirokchilar.
                                </p>
                            </div>
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
                        </div>
                        {data.parties.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Taraflar qo'shilmagan.
                            </p>
                        )}
                        {data.parties.map((p, i) => (
                            <div
                                key={i}
                                className="grid gap-2 sm:grid-cols-[180px_1fr_auto] sm:items-start"
                            >
                                <Select
                                    value={p.role}
                                    onValueChange={(v) =>
                                        setParty(i, { role: v })
                                    }
                                >
                                    <SelectTrigger aria-label="Taraf roli">
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
                                <div>
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
                    </section>

                    <div className="flex justify-end gap-2">
                        <Button asChild variant="ghost" type="button">
                            <Link href={casesIndex()}>Bekor qilish</Link>
                        </Button>
                        <Button
                            type="submit"
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

CasesCreate.layout = {
    breadcrumbs: [
        { title: "Ishlar", href: casesIndex() },
        { title: "Yangi ish", href: casesCreate() },
    ],
};

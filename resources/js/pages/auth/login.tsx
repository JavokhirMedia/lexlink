import { Head } from '@inertiajs/react';
import { FlaskConical, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { redirect } from '@/routes/oneid';

type Persona = {
    pinfl: string;
    name: string;
    label: string;
    hint: string;
    group: string;
};

type Props = {
    personas: Persona[];
    error?: string | null;
};

export default function Login({ personas, error }: Props) {
    const [persona, setPersona] = useState<string>(personas[0]?.pinfl ?? '');

    // Full page navigation (not an Inertia visit): the flow leaves the SPA for the OneID authorize page.
    const oneIdUrl = redirect.url();
    const demoUrl = persona
        ? redirect.url({ query: { login_hint: persona } })
        : oneIdUrl;

    return (
        <>
            <Head title="Kirish" />

            <div className="flex flex-col gap-6">
                {error && (
                    <Alert variant="destructive">
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <Button asChild size="lg" className="h-12 w-full text-base">
                    <a href={oneIdUrl} data-test="oneid-login-button">
                        <ShieldCheck className="size-5" />
                        OneID orqali kirish
                    </a>
                </Button>

                <p className="text-center text-xs text-muted-foreground">
                    Shaxsingiz yagona identifikatsiya tizimi (OneID) orqali
                    tasdiqlanadi. Parol talab qilinmaydi.
                </p>

                {personas.length > 0 && (
                    <div className="space-y-3 rounded-md border border-dashed p-4">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <FlaskConical className="size-4 text-muted-foreground" />
                            Demo rejimi
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Sinov uchun: soxta (to'qima) shaxs sifatida OneID
                            orqali kiring. Barcha ma'lumotlar uydirma.
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="persona" className="text-xs">
                                Demo shaxs
                            </Label>
                            <Select value={persona} onValueChange={setPersona}>
                                <SelectTrigger id="persona" className="w-full">
                                    <SelectValue placeholder="Shaxsni tanlang" />
                                </SelectTrigger>
                                <SelectContent>
                                    {personas.map((p) => (
                                        <SelectItem key={p.pinfl} value={p.pinfl}>
                                            <span className="flex flex-col text-left">
                                                <span>{p.name}</span>
                                                <span className="text-xs text-muted-foreground">
                                                    {p.label} · {p.hint}
                                                </span>
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="w-full"
                            disabled={!persona}
                        >
                            <a href={demoUrl} data-test="demo-login-button">
                                Demo shaxs sifatida kirish
                            </a>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

Login.layout = {
    title: 'LexLink tizimiga kirish',
    description: 'Advokat va yuristlar uchun davlat idoralariga so‘rovlar platformasi',
};

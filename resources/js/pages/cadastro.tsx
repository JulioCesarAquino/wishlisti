import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Eye, EyeOff, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAttribution } from '@/lib/attribution';

const BENEFITS = [
    'Página com capa, cores e contagem regressiva',
    'Confirmação de presença com acompanhantes',
    'Lista de presentes e convite pelo WhatsApp',
    'Grátis para começar, sem cartão de crédito',
];

/** The same rules the server checks (see AppServiceProvider). */
const PASSWORD_RULES: { label: string; test: (value: string) => boolean }[] = [
    { label: '8 ou mais caracteres', test: (value) => value.length >= 8 },
    {
        label: 'Letra maiúscula e minúscula',
        test: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value),
    },
    { label: 'Um número', test: (value) => /\d/.test(value) },
    {
        label: 'Um caractere especial (!@#…)',
        test: (value) => /[^A-Za-z0-9]/.test(value),
    },
];

/** "(11) 98888-7777" while typing. */
function maskPhone(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 2) {
        return digits.length ? `(${digits}` : '';
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    if (digits.length <= 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-sm text-red-600">{message}</p> : null;
}

export default function Cadastro() {
    const attribution = useAttribution();
    const [showPassword, setShowPassword] = useState(false);

    const form = useForm({
        name: '',
        email: '',
        whatsapp: '',
        password: '',
        password_confirmation: '',
        website: '',
        ref: null as string | null,
        utm_source: null as string | null,
        utm_medium: null as string | null,
        utm_campaign: null as string | null,
    });

    useEffect(() => {
        form.setData((data) => ({ ...data, ...attribution }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [attribution]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/cadastro', {
            onError: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Criar conta" />

            <div className="grid min-h-screen bg-[#fbf8f3] font-sans text-[#2a211b] antialiased lg:grid-cols-[1fr_1.1fr]">
                <aside className="relative hidden overflow-hidden bg-[#2a211b] p-12 text-white lg:flex lg:flex-col lg:justify-between">
                    <div
                        className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_0%_0%,rgba(251,191,36,0.28),transparent_70%),radial-gradient(50%_50%_at_100%_100%,rgba(244,114,182,0.2),transparent_70%)]"
                        aria-hidden
                    />
                    <Link
                        href="/"
                        className="relative flex items-center gap-2 text-lg font-semibold"
                    >
                        <AppLogoIcon className="size-7 text-amber-400" />
                        Wishlisti
                    </Link>
                    <div className="relative">
                        <h2 className="font-display text-4xl leading-tight font-semibold text-balance">
                            Falta pouco para a página do seu evento ficar
                            pronta.
                        </h2>
                        <ul className="mt-8 space-y-3 text-white/85">
                            {BENEFITS.map((benefit) => (
                                <li
                                    key={benefit}
                                    className="flex items-center gap-2.5"
                                >
                                    <span className="flex size-6 items-center justify-center rounded-full bg-amber-400/20 text-amber-300">
                                        <Check className="size-3.5" />
                                    </span>
                                    {benefit}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <p className="relative text-sm text-white/60">
                        Quer ver antes?{' '}
                        <a
                            href="/demo-casamento"
                            className="font-medium text-amber-300 underline-offset-4 hover:underline"
                        >
                            Abra um exemplo
                        </a>
                    </p>
                </aside>

                <main className="flex items-center justify-center px-4 py-10 sm:px-6">
                    <div className="w-full max-w-md">
                        <Link
                            href="/"
                            className="mb-8 flex items-center justify-center gap-2 text-lg font-semibold lg:hidden"
                        >
                            <AppLogoIcon className="size-7 text-amber-500" />
                            Wishlisti
                        </Link>

                        <h1 className="font-display text-3xl font-semibold">
                            Crie sua conta grátis
                        </h1>
                        <p className="mt-2 text-[#6b5d52]">
                            Em seguida, você já monta a página do seu evento.
                        </p>

                        <form onSubmit={submit} className="mt-8 space-y-5">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">Seu nome</Label>
                                <Input
                                    id="name"
                                    autoComplete="name"
                                    autoFocus
                                    className="h-11 bg-white"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                                <FieldError message={form.errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    className="h-11 bg-white"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                                <FieldError message={form.errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="whatsapp">WhatsApp</Label>
                                <Input
                                    id="whatsapp"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel-national"
                                    placeholder="(11) 98888-7777"
                                    className="h-11 bg-white"
                                    value={form.data.whatsapp}
                                    onChange={(e) =>
                                        form.setData(
                                            'whatsapp',
                                            maskPhone(e.target.value),
                                        )
                                    }
                                />
                                <FieldError message={form.errors.whatsapp} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password">Senha</Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        autoComplete="new-password"
                                        className="h-11 bg-white pr-11"
                                        value={form.data.password}
                                        onChange={(e) =>
                                            form.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword((shown) => !shown)
                                        }
                                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-[#6b5d52]"
                                        aria-label={
                                            showPassword
                                                ? 'Esconder senha'
                                                : 'Mostrar senha'
                                        }
                                    >
                                        {showPassword ? (
                                            <EyeOff className="size-4" />
                                        ) : (
                                            <Eye className="size-4" />
                                        )}
                                    </button>
                                </div>
                                <ul className="grid grid-cols-2 gap-x-3 gap-y-1 pt-1 text-xs">
                                    {PASSWORD_RULES.map((rule) => {
                                        const ok = rule.test(
                                            form.data.password,
                                        );

                                        return (
                                            <li
                                                key={rule.label}
                                                className={
                                                    ok
                                                        ? 'flex items-center gap-1 text-[#4d6b45]'
                                                        : 'flex items-center gap-1 text-[#9a8b7f]'
                                                }
                                            >
                                                <Check className="size-3" />
                                                {rule.label}
                                            </li>
                                        );
                                    })}
                                </ul>
                                <FieldError message={form.errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password_confirmation">
                                    Confirme a senha
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    className="h-11 bg-white"
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            {/* Hidden from people: only bots fill it in. */}
                            <div className="hidden" aria-hidden>
                                <label htmlFor="website">Site</label>
                                <input
                                    id="website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    value={form.data.website}
                                    onChange={(e) =>
                                        form.setData('website', e.target.value)
                                    }
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#2a211b] text-base font-semibold text-white shadow-lg shadow-amber-900/20 transition hover:bg-[#3a2e25] disabled:opacity-70"
                            >
                                {form.processing && (
                                    <Loader2 className="size-4 animate-spin" />
                                )}
                                Criar conta e montar meu evento
                            </button>
                        </form>

                        <p className="mt-6 text-center text-sm text-[#6b5d52]">
                            Já tem conta?{' '}
                            <a
                                href="/admin/login"
                                className="font-semibold text-[#2a211b] underline-offset-4 hover:underline"
                            >
                                Entrar
                            </a>
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}

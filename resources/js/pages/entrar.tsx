import { Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Loader2 } from 'lucide-react';
import { useState } from 'react';
import AuthLayout from '@/components/auth-layout';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    /** The panel's "forgot password" page (Filament's flow, by e-mail). */
    password_reset_url: string | null;
};

export default function Entrar({ password_reset_url }: Props) {
    const [showPassword, setShowPassword] = useState(false);

    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/entrar', {
            onError: () => form.reset('password'),
        });
    };

    return (
        <AuthLayout
            pageTitle="Entrar"
            asideTitle="Que bom te ver de novo. Seu evento está te esperando."
            title="Entre na sua conta"
            subtitle="Acompanhe confirmações, presentes e recados do seu evento."
        >
            <form onSubmit={submit} className="mt-8 space-y-5">
                <div className="grid gap-1.5">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        autoFocus
                        className="h-11 bg-white"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                    {form.errors.email && (
                        <p className="text-sm text-red-600">
                            {form.errors.email}
                        </p>
                    )}
                </div>

                <div className="grid gap-1.5">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="password">Senha</Label>
                        {password_reset_url && (
                            <a
                                href={password_reset_url}
                                className="text-sm font-medium text-amber-800 underline-offset-4 hover:underline"
                            >
                                Esqueceu a senha?
                            </a>
                        )}
                    </div>
                    <div className="relative">
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="current-password"
                            className="h-11 bg-white pr-11"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((shown) => !shown)}
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
                    {form.errors.password && (
                        <p className="text-sm text-red-600">
                            {form.errors.password}
                        </p>
                    )}
                </div>

                <label className="flex cursor-pointer items-center gap-2 text-sm text-[#6b5d52]">
                    <input
                        type="checkbox"
                        className="size-4 accent-[#2a211b]"
                        checked={form.data.remember}
                        onChange={(e) =>
                            form.setData('remember', e.target.checked)
                        }
                    />
                    Continuar conectado neste aparelho
                </label>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#2a211b] text-base font-semibold text-white shadow-lg shadow-amber-900/20 transition hover:bg-[#3a2e25] disabled:opacity-70"
                >
                    {form.processing && (
                        <Loader2 className="size-4 animate-spin" />
                    )}
                    Entrar
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-[#6b5d52]">
                Ainda não tem conta?{' '}
                <Link
                    href="/cadastro"
                    className="font-semibold text-[#2a211b] underline-offset-4 hover:underline"
                >
                    Crie grátis
                </Link>
            </p>
        </AuthLayout>
    );
}

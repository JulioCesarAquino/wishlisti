import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';

const BENEFITS = [
    'Página com capa, cores e contagem regressiva',
    'Confirmação de presença com acompanhantes',
    'Lista de presentes e convite pelo WhatsApp',
    'Grátis para começar, sem cartão de crédito',
];

/**
 * The sign-up and sign-in pages: what Wishlisti gives on the dark side
 * (on computers), the form on the light one — in the home page's look.
 */
export default function AuthLayout({
    pageTitle,
    asideTitle,
    title,
    subtitle,
    children,
}: {
    pageTitle: string;
    asideTitle: string;
    title: string;
    subtitle: string;
    children: React.ReactNode;
}) {
    return (
        <>
            <Head title={pageTitle} />

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
                            {asideTitle}
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
                            {title}
                        </h1>
                        <p className="mt-2 text-[#6b5d52]">{subtitle}</p>

                        {children}
                    </div>
                </main>
            </div>
        </>
    );
}

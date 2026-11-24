import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Baby,
    CalendarCheck,
    Cake,
    Check,
    ChevronDown,
    Gift,
    Heart,
    Images,
    Instagram,
    type LucideIcon,
    MapPin,
    MessageCircle,
    MessageSquareHeart,
    Palette,
    Smartphone,
    Sparkles,
    Timer,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useAttribution } from '@/lib/attribution';
import { cn } from '@/lib/utils';

type Demo = {
    slug: string;
    title: string;
    type_label: string;
    cover_image_url: string | null;
    url: string;
};

type Props = {
    demos: Demo[];
    pricing: {
        premium_price: number;
        free_gift_limit: number;
        free_photo_limit: number;
    };
};

const SIGN_UP_URL = '/cadastro';

function formatPrice(value: number): string {
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

/** How each occasion's card looks while its demo has no cover photo. */
const OCCASION_LOOKS: Record<
    string,
    { icon: LucideIcon; gradient: string; blurb: string }
> = {
    'demo-casamento': {
        icon: Heart,
        gradient: 'from-[#e9e2d0] via-[#c9d3bd] to-[#7d8f73]',
        blurb: 'Cerimônia e festa, lista com cotas de lua de mel e álbuns do grande dia.',
    },
    'demo-cha-de-bebe': {
        icon: Baby,
        gradient: 'from-[#eef4f8] via-[#c9dcec] to-[#6d9dc5]',
        blurb: 'Fraldas por tamanho, enxoval e confirmação com a idade das crianças.',
    },
    'demo-aniversario': {
        icon: Cake,
        gradient: 'from-[#fdf2f6] via-[#efc6dc] to-[#c77dab]',
        blurb: 'Tema, programação da festa e presentes escolhidos pela aniversariante.',
    },
};

const FEATURES: { icon: LucideIcon; title: string; text: string }[] = [
    {
        icon: Wallet,
        title: 'Presentes por Pix, cartão ou boleto',
        text: 'O dinheiro cai direto na sua conta Mercado Pago. Cotas de lua de mel, itens da casa ou qualquer valor.',
    },
    {
        icon: CalendarCheck,
        title: 'Confirmação de presença',
        text: 'Cada convidado confirma com os acompanhantes. Você vê a lista e o total de pessoas na hora.',
    },
    {
        icon: MessageCircle,
        title: 'Convite pelo WhatsApp',
        text: 'Mensagem pronta com o nome de cada convidado e um link pessoal que já sabe quem ele é.',
    },
    {
        icon: MessageSquareHeart,
        title: 'Mural de recados',
        text: 'Os convidados deixam mensagens de carinho, e você escolhe quais aparecem para todos.',
    },
    {
        icon: Images,
        title: 'Álbuns de fotos',
        text: 'Ensaio, cerimônia, festa: as fotos organizadas em álbuns, prontas para baixar.',
    },
    {
        icon: MapPin,
        title: 'Mapa e "como chegar"',
        text: 'Cada local com horário, mapa e atalho para o Google Maps ou o Waze.',
    },
    {
        icon: Timer,
        title: 'Contagem regressiva',
        text: 'Os dias até a festa, logo na capa, para todo mundo entrar no clima.',
    },
    {
        icon: Palette,
        title: 'Do seu jeito',
        text: 'Capa, cores, fontes, ordem das abas e o texto do convite: tudo personalizável.',
    },
];

const STEPS = [
    {
        title: 'Crie a página',
        text: 'Escolha o tipo de evento, coloque a data, a capa e as cores. Leva poucos minutos.',
    },
    {
        title: 'Monte a lista',
        text: 'Adicione presentes do catálogo ou do seu jeito, com foto, preço e cotas.',
    },
    {
        title: 'Mande o link',
        text: 'Compartilhe no WhatsApp e acompanhe confirmações, presentes e recados pelo painel.',
    },
];

function faq(pricing: Props['pricing']) {
    return [
        {
            question: 'Quanto custa?',
            answer: `Criar a página é grátis, sem cartão de crédito. Para receber presentes online, liberar o mural de recados, os álbuns e a lista completa, o Premium custa ${formatPrice(pricing.premium_price)} por evento, com pagamento único e sem mensalidade.`,
        },
        {
            question: 'Para onde vai o dinheiro dos presentes?',
            answer: 'Direto para a sua conta Mercado Pago. O Wishlisti não cobra taxa sobre os presentes: só existe a tarifa normal do Mercado Pago.',
        },
        {
            question: 'Meus convidados precisam baixar um app ou criar conta?',
            answer: 'Não. Eles abrem o link no celular, confirmam presença, escolhem o presente e pagam ali mesmo, sem cadastro.',
        },
        {
            question: 'Dá para presentear pessoalmente?',
            answer: 'Sim. O convidado reserva o presente na lista, ele sai para os outros, e a entrega fica combinada com você.',
        },
        {
            question: 'O que acontece com a página depois da festa?',
            answer: 'Ela vira uma lembrança: a história, as fotos e os recados continuam no ar, e o mapa e a confirmação saem sozinhos. Veja no exemplo, no botão "Depois do evento".',
        },
        {
            question: 'Serve para que tipo de evento?',
            answer: 'Casamento, noivado, chá de bebê, chá revelação, chá de panela, aniversário, batizado, formatura, churrasco, confraternização e o que mais você quiser comemorar.',
        },
    ];
}

/**
 * A phone with the demo running inside it, for real: the visitor can
 * scroll and tap around before signing up.
 */
function LivePhone({ src, title }: { src: string; title: string }) {
    const [loaded, setLoaded] = useState(false);

    return (
        <div className="relative mx-auto h-[580px] w-[284px] shrink-0 rounded-[2.75rem] bg-neutral-900 p-[10px] shadow-[0_40px_80px_-20px_rgba(60,40,20,0.45)] ring-1 ring-black/10">
            <div className="absolute top-[18px] left-1/2 z-10 h-[22px] w-[90px] -translate-x-1/2 rounded-full bg-neutral-900" />
            <div className="relative h-full w-full overflow-hidden rounded-[2.1rem] bg-[#f6f1ea]">
                {!loaded && (
                    <div className="absolute inset-0 flex animate-pulse flex-col items-center justify-center gap-3 text-[#7d8f73]">
                        <Heart className="size-8" />
                        <span className="text-xs">Abrindo o exemplo…</span>
                    </div>
                )}
                <iframe
                    src={src}
                    title={title}
                    loading="lazy"
                    onLoad={() => setLoaded(true)}
                    className={cn(
                        'h-[700px] w-[330px] origin-top-left scale-[0.8] border-0 transition-opacity duration-500',
                        loaded ? 'opacity-100' : 'opacity-0',
                    )}
                />
            </div>
        </div>
    );
}

/** The little notifications floating around the phone. */
function FloatingToast({
    icon: Icon,
    title,
    text,
    className,
    delay,
}: {
    icon: LucideIcon;
    title: string;
    text: string;
    className: string;
    delay: string;
}) {
    return (
        <div
            className={cn(
                'absolute z-20 hidden w-56 animate-[float_6s_ease-in-out_infinite] items-start gap-2.5 rounded-2xl bg-white/95 p-3 text-left shadow-xl ring-1 shadow-amber-900/10 ring-black/5 backdrop-blur lg:flex',
                className,
            )}
            style={{ animationDelay: delay }}
            aria-hidden
        >
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <Icon className="size-4" />
            </span>
            <span>
                <span className="block text-sm font-semibold text-[#2a211b]">
                    {title}
                </span>
                <span className="block text-xs text-[#7a6a5d]">{text}</span>
            </span>
        </div>
    );
}

function PrimaryCta({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <Link
            href={SIGN_UP_URL}
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-full bg-[#2a211b] px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-amber-900/20 transition hover:-translate-y-0.5 hover:bg-[#3a2e25]',
                className,
            )}
        >
            {children}
        </Link>
    );
}

function SectionHeading({
    eyebrow,
    title,
    text,
}: {
    eyebrow: string;
    title: string;
    text?: string;
}) {
    return (
        <div className="mx-auto mb-12 max-w-2xl text-center">
            <p className="mb-3 text-sm font-semibold tracking-wide text-amber-700 uppercase">
                {eyebrow}
            </p>
            <h2 className="font-display text-3xl leading-tight font-semibold text-balance text-[#2a211b] sm:text-4xl">
                {title}
            </h2>
            {text && (
                <p className="mt-4 text-lg text-pretty text-[#6b5d52]">
                    {text}
                </p>
            )}
        </div>
    );
}

export default function Welcome({ demos, pricing }: Props) {
    const { auth } = usePage().props;
    // Keeps the campaign (utm_*) and the event page that brought them,
    // for the sign-up.
    useAttribution();

    const heroDemo =
        demos.find((demo) => demo.slug === 'demo-casamento') ?? demos[0];
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 12);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <>
            <Head title="A página do seu evento">
                <style>{`@keyframes float { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }`}</style>
            </Head>

            <div className="min-h-screen overflow-x-clip bg-[#fbf8f3] font-sans text-[#2a211b] antialiased">
                <header
                    className={cn(
                        'sticky top-0 z-40 transition-colors',
                        scrolled
                            ? 'border-b border-black/5 bg-[#fbf8f3]/85 backdrop-blur'
                            : 'bg-transparent',
                    )}
                >
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <Link
                            href="/"
                            className="flex items-center gap-2 text-lg font-semibold"
                        >
                            <AppLogoIcon className="size-7 text-amber-500" />
                            Wishlisti
                        </Link>
                        <nav className="flex items-center gap-1 text-sm sm:gap-3">
                            <a
                                href="#exemplos"
                                className="hidden rounded-full px-3 py-2 font-medium text-[#6b5d52] hover:text-[#2a211b] sm:inline"
                            >
                                Exemplos
                            </a>
                            <a
                                href="#planos"
                                className="hidden rounded-full px-3 py-2 font-medium text-[#6b5d52] hover:text-[#2a211b] sm:inline"
                            >
                                Planos
                            </a>
                            {auth.user ? (
                                <a
                                    href="/admin"
                                    className="rounded-full bg-[#2a211b] px-4 py-2 font-semibold text-white"
                                >
                                    Ir para o painel
                                </a>
                            ) : (
                                <>
                                    <a
                                        href="/admin/login"
                                        className="rounded-full px-3 py-2 font-medium text-[#6b5d52] hover:text-[#2a211b]"
                                    >
                                        Entrar
                                    </a>
                                    <Link
                                        href={SIGN_UP_URL}
                                        className="rounded-full bg-[#2a211b] px-4 py-2 font-semibold text-white hover:bg-[#3a2e25]"
                                    >
                                        Criar grátis
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    {/* Hero */}
                    <section className="relative">
                        <div
                            className="pointer-events-none absolute inset-0 -top-20 bg-[radial-gradient(60%_50%_at_15%_10%,rgba(251,191,36,0.22),transparent_70%),radial-gradient(50%_45%_at_90%_30%,rgba(244,114,182,0.16),transparent_70%),radial-gradient(40%_40%_at_60%_95%,rgba(125,143,115,0.18),transparent_70%)]"
                            aria-hidden
                        />
                        <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pt-10 pb-20 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:pt-16 lg:pb-28">
                            <div className="text-center lg:text-left">
                                <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-white/70 px-3 py-1 text-sm font-medium text-amber-800">
                                    <Sparkles className="size-4" />
                                    Casamentos, chás e aniversários
                                </p>
                                <h1 className="font-display text-4xl leading-[1.08] font-semibold text-balance sm:text-5xl lg:text-6xl">
                                    A página do seu evento, linda como a sua
                                    festa.
                                </h1>
                                <p className="mx-auto mt-6 max-w-xl text-lg text-pretty text-[#6b5d52] sm:text-xl lg:mx-0">
                                    Convite, confirmação de presença, lista de
                                    presentes com Pix e álbum de fotos em um só
                                    link, pronto para mandar no WhatsApp.
                                </p>
                                <div className="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                                    <PrimaryCta className="w-full sm:w-auto">
                                        Criar meu evento grátis
                                        <ArrowRight className="size-4" />
                                    </PrimaryCta>
                                    {heroDemo && (
                                        <a
                                            href={heroDemo.url}
                                            className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-[#2a211b]/15 bg-white/70 px-6 py-3.5 text-base font-semibold hover:bg-white sm:w-auto"
                                        >
                                            Ver um exemplo
                                        </a>
                                    )}
                                </div>
                                <ul className="mt-6 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm text-[#6b5d52] lg:justify-start">
                                    {[
                                        'Grátis para começar',
                                        'Sem cartão de crédito',
                                        'Pronto em minutos',
                                    ].map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-center gap-1.5"
                                        >
                                            <Check className="size-4 text-[#7d8f73]" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {heroDemo && (
                                <div className="relative mx-auto">
                                    <FloatingToast
                                        icon={CalendarCheck}
                                        title="Marina confirmou presença"
                                        text="Ela e mais 2 acompanhantes"
                                        className="top-16 -left-52"
                                        delay="0s"
                                    />
                                    <FloatingToast
                                        icon={Gift}
                                        title="Presente recebido via Pix"
                                        text="Jantar romântico · R$ 350,00"
                                        className="top-64 -right-44"
                                        delay="1.5s"
                                    />
                                    <FloatingToast
                                        icon={MessageSquareHeart}
                                        title="Novo recado no mural"
                                        text="“Vi esse amor nascer e crescer…”"
                                        className="bottom-10 -left-60"
                                        delay="3s"
                                    />
                                    <LivePhone
                                        src={heroDemo.url}
                                        title={`Exemplo: ${heroDemo.title}`}
                                    />
                                    <p className="mt-4 flex items-center justify-center gap-1.5 text-sm text-[#6b5d52]">
                                        <Smartphone className="size-4" />
                                        Exemplo ao vivo: role e toque à vontade
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>

                    {/* Demos */}
                    {demos.length > 0 && (
                        <section
                            id="exemplos"
                            className="scroll-mt-20 border-y border-black/5 bg-white py-20"
                        >
                            <div className="mx-auto max-w-6xl px-4 sm:px-6">
                                <SectionHeading
                                    eyebrow="Experimente antes de criar"
                                    title="Abra um exemplo e sinta a experiência do convidado"
                                    text="Confirme presença, escolha um presente, pague com um Pix de mentira e deixe um recado. Nada do que você fizer nos exemplos é salvo."
                                />
                                <div className="grid gap-6 md:grid-cols-3">
                                    {demos.map((demo) => {
                                        const look =
                                            OCCASION_LOOKS[demo.slug] ??
                                            OCCASION_LOOKS['demo-casamento'];

                                        return (
                                            <a
                                                key={demo.slug}
                                                href={demo.url}
                                                className="group overflow-hidden rounded-3xl border border-black/5 bg-[#fbf8f3] shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                                            >
                                                <div
                                                    className={cn(
                                                        'relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-gradient-to-br',
                                                        look.gradient,
                                                    )}
                                                >
                                                    {demo.cover_image_url ? (
                                                        <img
                                                            src={
                                                                demo.cover_image_url
                                                            }
                                                            alt=""
                                                            loading="lazy"
                                                            className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105"
                                                        />
                                                    ) : (
                                                        <look.icon className="size-14 text-white/90" />
                                                    )}
                                                    <span className="absolute top-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-[#2a211b]">
                                                        {demo.type_label}
                                                    </span>
                                                </div>
                                                <div className="p-5">
                                                    <h3 className="font-display text-xl font-semibold">
                                                        {demo.title}
                                                    </h3>
                                                    <p className="mt-1.5 text-sm text-[#6b5d52]">
                                                        {look.blurb}
                                                    </p>
                                                    <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800">
                                                        Abrir exemplo
                                                        <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                                    </span>
                                                </div>
                                            </a>
                                        );
                                    })}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Features */}
                    <section className="py-20">
                        <div className="mx-auto max-w-6xl px-4 sm:px-6">
                            <SectionHeading
                                eyebrow="Tudo em um só link"
                                title="Menos planilha e grupo de WhatsApp, mais tempo para curtir"
                            />
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {FEATURES.map((feature) => (
                                    <div
                                        key={feature.title}
                                        className="rounded-3xl border border-black/5 bg-white p-6 shadow-sm"
                                    >
                                        <span className="mb-4 flex size-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-800">
                                            <feature.icon className="size-5" />
                                        </span>
                                        <h3 className="mb-1.5 font-semibold">
                                            {feature.title}
                                        </h3>
                                        <p className="text-sm text-[#6b5d52]">
                                            {feature.text}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* Keepsake */}
                    {heroDemo && (
                        <section className="px-4 sm:px-6">
                            <div className="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-[#2a211b] px-6 py-14 text-center text-white sm:px-12 lg:text-left">
                                <div
                                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_80%_at_100%_0%,rgba(251,191,36,0.25),transparent_70%)]"
                                    aria-hidden
                                />
                                <div className="relative grid items-center gap-8 lg:grid-cols-[1.4fr_1fr]">
                                    <div>
                                        <p className="mb-3 text-sm font-semibold tracking-wide text-amber-300 uppercase">
                                            Depois da festa
                                        </p>
                                        <h2 className="font-display text-3xl leading-tight font-semibold text-balance sm:text-4xl">
                                            A página vira uma lembrança para
                                            sempre
                                        </h2>
                                        <p className="mt-4 max-w-xl text-lg text-white/75 lg:max-w-none">
                                            No dia seguinte, o mapa e a
                                            confirmação saem de cena e ficam a
                                            história, os álbuns de fotos e os
                                            recados de quem esteve lá.
                                        </p>
                                    </div>
                                    <div className="flex justify-center lg:justify-end">
                                        <a
                                            href={`${heroDemo.url}?momento=depois`}
                                            className="inline-flex items-center gap-2 rounded-full bg-amber-400 px-6 py-3.5 font-semibold text-[#2a211b] hover:bg-amber-300"
                                        >
                                            Ver como fica depois
                                            <ArrowRight className="size-4" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Steps */}
                    <section className="py-20">
                        <div className="mx-auto max-w-5xl px-4 sm:px-6">
                            <SectionHeading
                                eyebrow="Como funciona"
                                title="Do cadastro ao link no WhatsApp em três passos"
                            />
                            <ol className="grid gap-6 md:grid-cols-3">
                                {STEPS.map((step, index) => (
                                    <li key={step.title} className="relative">
                                        <span className="mb-3 block font-display text-5xl font-semibold text-amber-300">
                                            {index + 1}
                                        </span>
                                        <h3 className="mb-1.5 text-lg font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="text-[#6b5d52]">
                                            {step.text}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    {/* Pricing */}
                    <section
                        id="planos"
                        className="scroll-mt-20 border-y border-black/5 bg-white py-20"
                    >
                        <div className="mx-auto max-w-5xl px-4 sm:px-6">
                            <SectionHeading
                                eyebrow="Planos"
                                title="Comece grátis. Libere o Premium só se precisar."
                                text="Sem mensalidade: o Premium é por evento, com pagamento único."
                            />
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="flex flex-col rounded-3xl border border-black/10 bg-[#fbf8f3] p-7">
                                    <h3 className="text-lg font-semibold">
                                        Grátis
                                    </h3>
                                    <p className="mt-2 font-display text-4xl font-semibold">
                                        R$ 0
                                    </p>
                                    <p className="mt-1 text-sm text-[#6b5d52]">
                                        Para sempre
                                    </p>
                                    <ul className="mt-6 flex-1 space-y-3 text-sm">
                                        {[
                                            'Página com capa, cores e contagem regressiva',
                                            'Confirmação de presença com acompanhantes',
                                            'Mapa e "como chegar" para cada local',
                                            `Lista com até ${pricing.free_gift_limit} presentes do catálogo, para entregar pessoalmente`,
                                            `Galeria com até ${pricing.free_photo_limit} fotos`,
                                        ].map((item) => (
                                            <li
                                                key={item}
                                                className="flex gap-2"
                                            >
                                                <Check className="mt-0.5 size-4 shrink-0 text-[#7d8f73]" />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                    <Link
                                        href={SIGN_UP_URL}
                                        className="mt-8 inline-flex items-center justify-center rounded-full border border-[#2a211b]/20 px-6 py-3 font-semibold hover:bg-white"
                                    >
                                        Criar grátis
                                    </Link>
                                </div>
                                <div className="relative flex flex-col rounded-3xl bg-[#2a211b] p-7 text-white shadow-xl shadow-amber-900/20">
                                    <span className="absolute -top-3 right-6 rounded-full bg-amber-400 px-3 py-1 text-xs font-bold text-[#2a211b]">
                                        Mais completo
                                    </span>
                                    <h3 className="text-lg font-semibold">
                                        Premium
                                    </h3>
                                    <p className="mt-2 font-display text-4xl font-semibold">
                                        {formatPrice(pricing.premium_price)}
                                    </p>
                                    <p className="mt-1 text-sm text-white/70">
                                        Por evento, pagamento único
                                    </p>
                                    <ul className="mt-6 flex-1 space-y-3 text-sm">
                                        {[
                                            'Tudo do grátis',
                                            'Presentes por Pix, cartão ou boleto, direto na sua conta Mercado Pago',
                                            'Presentes do seu jeito, cotas e lista sem limite',
                                            'Lista nominal de convidados e convite com o nome de cada um',
                                            'Mural de recados',
                                            'Álbuns de fotos sem limite',
                                            'Agenda de contatos para os próximos eventos',
                                        ].map((item) => (
                                            <li
                                                key={item}
                                                className="flex gap-2"
                                            >
                                                <Check className="mt-0.5 size-4 shrink-0 text-amber-300" />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                    <Link
                                        href={SIGN_UP_URL}
                                        className="mt-8 inline-flex items-center justify-center rounded-full bg-amber-400 px-6 py-3 font-semibold text-[#2a211b] hover:bg-amber-300"
                                    >
                                        Começar agora
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* FAQ */}
                    <section className="py-20">
                        <div className="mx-auto max-w-3xl px-4 sm:px-6">
                            <SectionHeading
                                eyebrow="Dúvidas"
                                title="Perguntas frequentes"
                            />
                            <div className="divide-y divide-black/10 rounded-3xl border border-black/5 bg-white px-6">
                                {faq(pricing).map((item) => (
                                    <details
                                        key={item.question}
                                        className="group py-5"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold [&::-webkit-details-marker]:hidden">
                                            {item.question}
                                            <ChevronDown className="size-5 shrink-0 text-[#6b5d52] transition group-open:rotate-180" />
                                        </summary>
                                        <p className="mt-3 text-[#6b5d52]">
                                            {item.answer}
                                        </p>
                                    </details>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* Final CTA */}
                    <section className="px-4 pb-20 sm:px-6">
                        <div className="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-amber-100 via-[#fbe7d8] to-rose-100 px-6 py-16 text-center">
                            <h2 className="mx-auto max-w-2xl font-display text-3xl leading-tight font-semibold text-balance sm:text-4xl">
                                Seus convidados vão lembrar do convite. E da
                                festa.
                            </h2>
                            <p className="mx-auto mt-4 max-w-xl text-lg text-[#6b5d52]">
                                Crie a página agora e mande o link ainda hoje.
                            </p>
                            <PrimaryCta className="mt-8">
                                Criar meu evento grátis
                                <ArrowRight className="size-4" />
                            </PrimaryCta>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-black/5 px-4 py-8 text-center text-sm text-[#6b5d52]">
                    <p className="flex flex-wrap items-center justify-center gap-1.5">
                        © {new Date().getFullYear()} Wishlisti | Desenvolvido
                        por Julio Cesar Aquino
                        <a
                            href="https://instagram.com/juliucaezer"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Instagram de Julio Cesar Aquino"
                            className="hover:text-amber-600"
                        >
                            <Instagram className="size-4" />
                        </a>
                    </p>
                </footer>
            </div>
        </>
    );
}

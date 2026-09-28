import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Baby,
    CreditCard,
    Gift,
    Heart,
    Instagram,
    PartyPopper,
    Share2,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { store as storeInviteRequest } from '@/routes/invite-requests';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const STEPS = [
    {
        icon: Gift,
        title: 'Crie sua lista',
        description:
            'Monte a lista de presentes do seu evento em minutos, com fotos e preços do seu jeito.',
    },
    {
        icon: Share2,
        title: 'Compartilhe o link',
        description:
            'Envie a página do seu evento para os convidados por WhatsApp ou redes sociais.',
    },
    {
        icon: CreditCard,
        title: 'Receba os presentes',
        description:
            'Convidados pagam com Pix, cartão ou boleto. Você acompanha tudo em um só lugar.',
    },
];

const OCCASIONS = [
    { icon: Heart, label: 'Casamento' },
    { icon: Baby, label: 'Chá de bebê' },
    { icon: Sparkles, label: 'Chá de panela' },
    { icon: PartyPopper, label: 'Aniversário' },
];

function InviteRequestForm({ onSuccess }: { onSuccess: () => void }) {
    const form = useForm({
        name: '',
        email: '',
        whatsapp: '',
    });

    const submit = () => {
        form.post(storeInviteRequest().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess();
            },
        });
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-1.5">
                <Label htmlFor="invite_name">Nome</Label>
                <Input
                    id="invite_name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                {form.errors.name && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {form.errors.name}
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="invite_email">E-mail</Label>
                <Input
                    id="invite_email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                {form.errors.email && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {form.errors.email}
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="invite_whatsapp">WhatsApp</Label>
                <Input
                    id="invite_whatsapp"
                    value={form.data.whatsapp}
                    onChange={(e) => form.setData('whatsapp', e.target.value)}
                />
                {form.errors.whatsapp && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {form.errors.whatsapp}
                    </p>
                )}
            </div>

            <Button
                className="w-full"
                onClick={submit}
                disabled={form.processing}
            >
                Solicitar convite
            </Button>
        </div>
    );
}

function InviteRequestDialog({ trigger }: { trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const [sent, setSent] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    setSent(false);
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                {sent ? (
                    <div className="py-6 text-center">
                        <Gift className="mx-auto mb-3 size-10 text-primary" />
                        <DialogTitle className="mb-2">
                            Pedido recebido!
                        </DialogTitle>
                        <DialogDescription>
                            Em breve entraremos em contato para liberar seu
                            acesso.
                        </DialogDescription>
                    </div>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>Solicitar convite</DialogTitle>
                            <DialogDescription>
                                Conte pra gente quem é você e entraremos em
                                contato para liberar seu acesso.
                            </DialogDescription>
                        </DialogHeader>
                        <InviteRequestForm onSuccess={() => setSent(true)} />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head />

            <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
                    <span className="flex items-center gap-2 text-lg font-semibold">
                        <AppLogoIcon className="size-6 text-amber-500" />
                        Wishlisti
                    </span>
                    <nav className="flex items-center gap-4">
                        {auth.user ? (
                            <a
                                href="/admin"
                                className="text-sm font-medium hover:underline"
                            >
                                Painel
                            </a>
                        ) : (
                            <>
                                <a
                                    href="/admin/login"
                                    className="text-sm font-medium hover:underline"
                                >
                                    Entrar
                                </a>
                                <InviteRequestDialog
                                    trigger={
                                        <Button size="sm">
                                            Solicitar convite
                                        </Button>
                                    }
                                />
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto max-w-3xl px-6 pt-16 pb-20 text-center">
                        <h1 className="mb-4 text-4xl font-semibold text-balance sm:text-5xl">
                            A lista de presentes do seu jeito
                        </h1>
                        <p className="mx-auto mb-8 max-w-xl text-lg text-[#706f6c] dark:text-[#A1A09A]">
                            Crie a página de presentes do seu casamento, chá de
                            bebê ou aniversário e receba via Pix, cartão ou
                            boleto — sem complicação para você nem para seus
                            convidados.
                        </p>
                        <InviteRequestDialog
                            trigger={
                                <Button size="lg">Solicitar convite</Button>
                            }
                        />
                    </section>

                    <section className="border-t border-[#e3e3e0] bg-white py-16 dark:border-[#3E3E3A] dark:bg-[#111110]">
                        <div className="mx-auto max-w-5xl px-6">
                            <h2 className="mb-10 text-center text-2xl font-semibold">
                                Como funciona
                            </h2>
                            <div className="grid gap-6 sm:grid-cols-3">
                                {STEPS.map((step) => (
                                    <Card key={step.title}>
                                        <CardContent className="pt-2 text-center">
                                            <step.icon className="mx-auto mb-4 size-8 text-primary" />
                                            <h3 className="mb-2 font-semibold">
                                                {step.title}
                                            </h3>
                                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                                {step.description}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="py-16">
                        <div className="mx-auto max-w-5xl px-6 text-center">
                            <h2 className="mb-8 text-2xl font-semibold">
                                Para toda celebração
                            </h2>
                            <div className="flex flex-wrap items-center justify-center gap-3">
                                {OCCASIONS.map((occasion) => (
                                    <span
                                        key={occasion.label}
                                        className="inline-flex items-center gap-2 rounded-full border border-[#e3e3e0] px-4 py-2 text-sm dark:border-[#3E3E3A]"
                                    >
                                        <occasion.icon className="size-4" />
                                        {occasion.label}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="border-t border-[#e3e3e0] bg-white py-16 text-center dark:border-[#3E3E3A] dark:bg-[#111110]">
                        <h2 className="mb-4 text-2xl font-semibold">
                            Pronto para começar?
                        </h2>
                        <p className="mx-auto mb-6 max-w-md text-[#706f6c] dark:text-[#A1A09A]">
                            Solicite seu convite e comece a montar sua lista de
                            presentes hoje mesmo.
                        </p>
                        <InviteRequestDialog
                            trigger={
                                <Button size="lg">Solicitar convite</Button>
                            }
                        />
                    </section>
                </main>

                <footer className="px-6 py-8 text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    <p className="flex items-center justify-center gap-1.5">
                        © {new Date().getFullYear()} Wishlisti | Desenvolvido
                        por Julio Cesar Aquino
                        <a
                            href="https://instagram.com/juliucaezer"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Instagram de Julio Cesar Aquino"
                            className="text-[#706f6c] hover:text-amber-500 dark:text-[#A1A09A]"
                        >
                            <Instagram className="size-4" />
                        </a>
                    </p>
                </footer>
            </div>
        </>
    );
}

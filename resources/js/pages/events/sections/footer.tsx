import { Copy, Facebook, MapPin, MessageCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Separator } from '@/components/ui/separator';
import {
    bodyTextStyle,
    headingStyle,
    type EventData,
} from '@/pages/events/types';

function currentUrl(): string {
    return `${window.location.origin}${window.location.pathname}`;
}

export function Footer({ event }: { event: EventData }) {
    const url = currentUrl();

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(url);
            toast.success('Link copiado!');
        } catch {
            toast.error('Não foi possível copiar o link.');
        }
    };

    const shareButtons = [
        {
            label: 'WhatsApp',
            icon: MessageCircle,
            href: `https://wa.me/?text=${encodeURIComponent(`${event.title} — ${url}`)}`,
        },
        {
            label: 'Facebook',
            icon: Facebook,
            href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
        },
    ];

    return (
        <footer className="mx-auto max-w-3xl px-6 py-10 text-center">
            <Separator className="mb-8" />

            <p className="mb-4 text-sm" style={headingStyle(event)}>
                {event.title}
                {event.event_date && (
                    <>
                        {' • '}
                        {new Date(
                            `${event.event_date}T00:00:00`,
                        ).toLocaleDateString('pt-BR', {
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </>
                )}
            </p>

            <div className="mb-4 flex justify-center gap-3">
                {event.sections.some(
                    (section) => section.key === 'localizacao',
                ) && (
                    <a
                        href="#localizacao"
                        className="flex size-9 items-center justify-center rounded-full border border-black/10 transition hover:bg-black/5"
                        aria-label="Ver localização"
                    >
                        <MapPin
                            className="size-4"
                            style={bodyTextStyle(event)}
                        />
                    </a>
                )}
                {shareButtons.map((button) => (
                    <a
                        key={button.label}
                        href={button.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex size-9 items-center justify-center rounded-full border border-black/10 transition hover:bg-black/5"
                        aria-label={`Compartilhar no ${button.label}`}
                    >
                        <button.icon
                            className="size-4"
                            style={bodyTextStyle(event)}
                        />
                    </a>
                ))}
                <button
                    type="button"
                    onClick={copyLink}
                    className="flex size-9 items-center justify-center rounded-full border border-black/10 transition hover:bg-black/5"
                    aria-label="Copiar link"
                >
                    <Copy className="size-4" style={bodyTextStyle(event)} />
                </button>
            </div>

            <p className="text-xs opacity-70" style={bodyTextStyle(event)}>
                {event.visits_count}{' '}
                {event.visits_count === 1
                    ? 'pessoa já visitou'
                    : 'pessoas já visitaram'}{' '}
                esta página.
            </p>
        </footer>
    );
}

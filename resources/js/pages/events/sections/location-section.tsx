import { ArrowLeft, Link as LinkIcon, MapPin, Navigation } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    bodyTextStyle,
    googleMapsEmbedUrl,
    headingStyle,
    googleMapsUrl,
    wazeUrl,
    type EventData,
    type EventLocation,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    /** Slug of the location in focus, when the event has more than one. */
    focused: string | null;
    onFocus: (slug: string | null) => void;
};

/**
 * A single location shows straight away; several show as cards ("Cerimônia",
 * "Festa"…), each opening its own details — with a link of its own to share.
 */
export function LocationSection({ event, focused, onFocus }: Props) {
    const { locations } = event;
    const single = locations.length === 1;
    const current = single
        ? locations[0]
        : locations.find((location) => location.slug === focused);

    if (current) {
        return (
            <div className="mx-auto max-w-3xl px-6 py-8">
                {!single && (
                    <Button
                        variant="link"
                        className="mb-2 px-0"
                        style={bodyTextStyle(event)}
                        onClick={() => onFocus(null)}
                    >
                        <ArrowLeft />
                        Todas as localizações
                    </Button>
                )}
                <LocationDetails event={event} location={current} />
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-3xl px-6 py-8">
            <h2
                className="mb-6 text-center text-2xl font-semibold"
                style={headingStyle(event)}
            >
                Localização
            </h2>
            <div className="grid gap-4 sm:grid-cols-2">
                {locations.map((location) => (
                    <a
                        key={location.slug}
                        href={location.url}
                        onClick={(e) => {
                            e.preventDefault();
                            onFocus(location.slug);
                        }}
                        className="block rounded-lg border border-black/10 bg-white/40 p-5 transition-colors hover:bg-white/60"
                    >
                        <MapPin
                            className="mb-2 size-6"
                            style={{
                                color: event.secondary_color ?? undefined,
                            }}
                        />
                        <p
                            className="text-lg font-semibold"
                            style={headingStyle(event)}
                        >
                            {location.name}
                        </p>
                        <p
                            className="mt-1 text-sm leading-relaxed whitespace-pre-line"
                            style={bodyTextStyle(event)}
                        >
                            {location.address}
                        </p>
                    </a>
                ))}
            </div>
        </div>
    );
}

function LocationDetails({
    event,
    location,
}: {
    event: EventData;
    location: EventLocation;
}) {
    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(location.url);
            toast.success('Link copiado.');
        } catch {
            toast.error(`Não foi possível copiar. O link é: ${location.url}`);
        }
    };

    return (
        <>
            <h2
                className="mb-4 text-2xl font-semibold"
                style={headingStyle(event)}
            >
                {location.name}
            </h2>
            <p
                className="mb-4 leading-relaxed whitespace-pre-line"
                style={bodyTextStyle(event)}
            >
                {location.address}
            </p>
            <div className="aspect-video overflow-hidden rounded-lg">
                <iframe
                    title={`Mapa: ${location.name}`}
                    src={googleMapsEmbedUrl(location)}
                    className="h-full w-full border-0"
                    loading="lazy"
                    referrerPolicy="strict-origin-when-cross-origin"
                    allowFullScreen
                />
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
                <Button asChild variant="outline">
                    <a
                        href={googleMapsUrl(location)}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <MapPin />
                        Google Maps
                    </a>
                </Button>
                <Button asChild variant="outline">
                    <a
                        href={wazeUrl(location)}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Navigation />
                        Waze
                    </a>
                </Button>
                <Button variant="outline" onClick={copyLink}>
                    <LinkIcon />
                    Copiar link
                </Button>
            </div>
        </>
    );
}

import { MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { CoverImage } from '@/pages/events/sections/cover-image';
import { Countdown } from '@/pages/events/sections/countdown';
import {
    accentButtonStyle,
    bodyTextStyle,
    EVENT_TYPE_LABELS,
    headingStyle,
    type EventData,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    /** Opens the location tab, on a given location or on all of them. */
    onOpenLocation: (slug: string | null) => void;
};

export function HomeSection({ event, onOpenLocation }: Props) {
    return (
        <div>
            {event.cover_image_url && (
                <CoverImage
                    src={event.cover_image_url}
                    alt={event.title}
                    intensity={event.cover_effect_intensity}
                />
            )}

            <div className="mx-auto max-w-3xl px-6 py-10 text-center">
                <h1
                    className="mb-2 text-5xl font-semibold"
                    style={headingStyle(event)}
                >
                    {event.title}
                </h1>
                <Badge variant="secondary" className="mb-3">
                    {EVENT_TYPE_LABELS[event.type] ?? event.type}
                </Badge>
                {event.event_date && (
                    <p className="mb-2 text-sm" style={bodyTextStyle(event)}>
                        {new Date(
                            `${event.event_date}T00:00:00`,
                        ).toLocaleDateString('pt-BR', {
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </p>
                )}

                <Countdown event={event} />

                <LocationShortcut
                    event={event}
                    onOpenLocation={onOpenLocation}
                />

                {event.description && (
                    <p
                        className="mt-4 leading-relaxed whitespace-pre-line"
                        style={bodyTextStyle(event)}
                    >
                        {event.description}
                    </p>
                )}

                {event.story && (
                    <>
                        <Separator className="my-8" />
                        <h2
                            className="mb-4 text-2xl font-semibold"
                            style={headingStyle(event)}
                        >
                            Nossa história
                        </h2>
                        <p
                            className="leading-relaxed whitespace-pre-line"
                            style={bodyTextStyle(event)}
                        >
                            {event.story}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}

const MAX_LOCATION_BUTTONS = 3;

/**
 * Most guests open the invite to find out when and where: the "where" sits
 * right under the date — one button per location (or a single one when
 * there are many), only while the location tab is on.
 */
function LocationShortcut({ event, onOpenLocation }: Props) {
    const { locations } = event;
    const hasTab = event.sections.some(
        (section) => section.key === 'localizacao',
    );

    if (!event.show_location_shortcut || !hasTab || locations.length === 0) {
        return null;
    }

    if (locations.length === 1) {
        return (
            <div className="mt-6">
                <Button
                    size="lg"
                    style={accentButtonStyle(event)}
                    onClick={() => onOpenLocation(null)}
                >
                    <MapPin />
                    Como chegar
                </Button>
                <p
                    className="mx-auto mt-2 max-w-sm truncate text-sm"
                    style={bodyTextStyle(event)}
                >
                    {locations[0].address.split('\n')[0]}
                </p>
            </div>
        );
    }

    if (locations.length > MAX_LOCATION_BUTTONS) {
        return (
            <div className="mt-6">
                <Button
                    size="lg"
                    style={accentButtonStyle(event)}
                    onClick={() => onOpenLocation(null)}
                >
                    <MapPin />
                    Ver locais
                </Button>
            </div>
        );
    }

    return (
        <div className="mt-6 flex flex-wrap justify-center gap-2">
            {locations.map((location) => (
                <Button
                    key={location.slug}
                    size="lg"
                    style={accentButtonStyle(event)}
                    onClick={() => onOpenLocation(location.slug)}
                >
                    <MapPin />
                    {location.name}
                </Button>
            ))}
        </div>
    );
}

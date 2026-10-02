import type { CSSProperties } from 'react';

export type Product = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    price: number;
    quantity_available: number;
    is_sold_out: boolean;
};

export type FontFamily = 'default' | 'script' | 'serif' | 'classic';

export type EventData = {
    slug: string;
    type: string;
    title: string;
    event_date: string | null;
    description: string | null;
    story: string | null;
    url: string;
    /** Tabs of the menu, in the host's order (only those turned on). */
    sections: { key: PageSectionKey; label: string }[];
    locations: EventLocation[];
    cover_image_url: string | null;
    gallery_urls: string[];
    primary_color: string | null;
    secondary_color: string | null;
    font_color_primary: string | null;
    font_color_secondary: string | null;
    font_family: FontFamily | null;
    cover_effect_intensity: number;
    is_published: boolean;
    visits_count: number;
    accepts_online_gifts: boolean;
    accepts_in_person_gifts: boolean;
    accepts_free_amount: boolean;
    gift_display_mode: GiftDisplayMode;
    gift_message: string;
    has_guestbook: boolean;
    mp_public_key: string | null;
    rsvp_required_fields: RsvpContactField[];
    rsvp_collect_companions: boolean;
};

export type PageSectionKey =
    | 'inicio'
    | 'galeria'
    | 'presentes'
    | 'confirmar-presenca'
    | 'recados'
    | 'localizacao';

export type EventLocation = {
    name: string;
    slug: string;
    address: string;
    maps_url: string | null;
    latitude: number | null;
    longitude: number | null;
    /** Shareable link that opens the page on this location. */
    url: string;
};

export type RsvpContactField = 'whatsapp' | 'email' | 'cpf';

export type GuestContact = {
    name: string;
    whatsapp: string | null;
    email: string | null;
    cpf: string | null;
};

export type Fulfillment = 'online' | 'in_person';

export type GiftDisplayMode = 'list' | 'discreet' | 'none';

export type GuestMessage = {
    id: number;
    author_name: string;
    message: string;
};

export type Reservation = {
    id: number;
    status: 'reserved' | 'received';
    is_anonymous: boolean;
    items: string[];
};

export type Guest = GuestContact & {
    rsvp_status: 'confirmed' | 'declined' | null;
    rsvp_guests_count: number | null;
    companions: GuestContact[];
    reservations: Reservation[];
};

export const EVENT_TYPE_LABELS: Record<string, string> = {
    casamento: 'Casamento',
    cha_bebe: 'Chá de bebê',
    cha_panela: 'Chá de panela',
    aniversario: 'Aniversário',
    outro: 'Evento',
};

export function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

type FontDefinition = {
    label: string;
    headingFamily: string;
    googleFontsHref: string | null;
};

export const FONT_FAMILIES: Record<FontFamily, FontDefinition> = {
    default: {
        label: 'Moderna',
        headingFamily: 'inherit',
        googleFontsHref: null,
    },
    script: {
        label: 'Manuscrita',
        headingFamily: "'Dancing Script', cursive",
        googleFontsHref:
            'https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&display=swap',
    },
    serif: {
        label: 'Elegante',
        headingFamily: "'Playfair Display', serif",
        googleFontsHref:
            'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&display=swap',
    },
    classic: {
        label: 'Clássica',
        headingFamily: "'Cormorant Garamond', serif",
        googleFontsHref:
            'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;700&display=swap',
    },
};

export const DEFAULT_PRIMARY_COLOR = '#f5f1e8';
export const DEFAULT_SECONDARY_COLOR = '#8a9a7e';
export const DEFAULT_FONT_COLOR_PRIMARY = '#3d3d2f';
export const DEFAULT_FONT_COLOR_SECONDARY = '#6b6b5a';

/**
 * A subtle paper-grain texture, generated procedurally (no external image),
 * tinted with the event's primary color.
 */
export function grainBackgroundStyle(
    primaryColor: string | null,
): CSSProperties {
    const noise =
        "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.35'/%3E%3C/svg%3E\")";

    return {
        backgroundColor: primaryColor ?? DEFAULT_PRIMARY_COLOR,
        backgroundImage: noise,
    };
}

/**
 * Google Maps iframe URL with a pin on the location. Uses the classic
 * `maps?q=…&output=embed` format: a search for the coordinates (or, without
 * them, for the address), which — unlike the `embed?pb=` "share a map"
 * format — drops a marker on the spot. Unofficial like the other one, but
 * needs no API key and no billing account.
 */
export function googleMapsEmbedUrl(location: EventLocation): string {
    return `https://maps.google.com/maps?q=${encodeURIComponent(mapsQuery(location))}&z=16&hl=pt-BR&output=embed`;
}

/**
 * Where "Abrir no mapa" goes: the host's own link (Google Maps, Waze…) or,
 * without one, a Google Maps search that opens the app on phones.
 */
export function openMapUrl(location: EventLocation): string {
    return (
        location.maps_url ??
        `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapsQuery(location))}`
    );
}

function mapsQuery(location: EventLocation): string {
    return location.latitude !== null && location.longitude !== null
        ? `${location.latitude},${location.longitude}`
        : location.address;
}

export function headingStyle(event: EventData): CSSProperties {
    return {
        fontFamily: FONT_FAMILIES[event.font_family ?? 'default'].headingFamily,
        color: event.font_color_primary ?? DEFAULT_FONT_COLOR_PRIMARY,
    };
}

export function bodyTextStyle(event: EventData): CSSProperties {
    return {
        color: event.font_color_secondary ?? DEFAULT_FONT_COLOR_SECONDARY,
    };
}

export function accentButtonStyle(event: EventData): CSSProperties {
    return {
        backgroundColor: event.secondary_color ?? DEFAULT_SECONDARY_COLOR,
        color: '#ffffff',
    };
}

/**
 * Overrides the fixed, theme-independent `--color-muted-foreground` token
 * (see the `.event-page` CSS class) with the event's own secondary font
 * color, so placeholder text in inputs stays legible against dark themes
 * instead of using a gray tuned only for light backgrounds.
 */
export function mutedTextCssVars(event: EventData): CSSProperties {
    return {
        '--color-muted-foreground':
            event.font_color_secondary ?? DEFAULT_FONT_COLOR_SECONDARY,
    } as CSSProperties;
}

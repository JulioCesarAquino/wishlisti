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
    /** Start time, already formatted ("16h", "18h30"); null without a date. */
    event_time: string | null;
    /** When the event starts (ISO, with offset): what the countdown counts to. */
    starts_at: string | null;
    description: string | null;
    /** "Casamento", "Churrasco"… ("Evento" for another kind). */
    type_label: string;
    /** The story section's title: by type, or the host's own. */
    story_title: string;
    story: string | null;
    url: string;
    /** Tabs of the menu, in the host's order (only those turned on). */
    sections: { key: PageSectionKey; label: string }[];
    locations: EventLocation[];
    cover_image_url: string | null;
    gallery_urls: string[];
    primary_color: string | null;
    secondary_color: string | null;
    /** Gift and checkout buttons; the server fills in the default when unset. */
    button_color: string;
    font_color_primary: string | null;
    font_color_secondary: string | null;
    font_family: FontFamily | null;
    cover_effect_intensity: number;
    /** The "Como chegar" button(s) on the home tab. */
    show_location_shortcut: boolean;
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
    /** Each field of the RSVP form (besides the name): hidden, optional or required. */
    rsvp_fields: Record<RsvpField, RsvpFieldMode>;
    /** The same, for each companion (the children: name and age, say). */
    rsvp_companion_fields: Record<RsvpField, RsvpFieldMode>;
    /** Under this age a guest counts as a child; null when not told apart. */
    rsvp_child_age_limit: number | null;
    /** The form asks how many of the party are under that age. */
    rsvp_asks_children_count: boolean;
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
    /** When this part starts, already formatted ("16h"), if the host set it. */
    start_time: string | null;
    slug: string;
    address: string;
    maps_url: string | null;
    latitude: number | null;
    longitude: number | null;
    /** Shareable link that opens the page on this location. */
    url: string;
};

export type RsvpContactField = 'whatsapp' | 'email' | 'cpf';

export type RsvpField = RsvpContactField | 'age';

export type RsvpFieldMode = 'hidden' | 'optional' | 'required';

export type GuestContact = {
    name: string;
    whatsapp: string | null;
    email: string | null;
    cpf: string | null;
    age?: number | null;
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
    rsvp_children_count: number | null;
    companions: GuestContact[];
    reservations: Reservation[];
};

/** "30 de outubro de 2026 · 16h": the date, with the time when there is one. */
export function formatEventDate(event: EventData): string | null {
    if (!event.event_date) {
        return null;
    }

    const date = new Date(`${event.event_date}T00:00:00`).toLocaleDateString(
        'pt-BR',
        { day: '2-digit', month: 'long', year: 'numeric' },
    );

    return event.event_time ? `${date} · ${event.event_time}` : date;
}

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
 * "Google Maps": the host's own link when it is one (any map link other
 * than Waze's), or else a search that opens the app on phones.
 */
export function googleMapsUrl(location: EventLocation): string {
    return location.maps_url && !isWazeLink(location.maps_url)
        ? location.maps_url
        : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapsQuery(location))}`;
}

/**
 * "Waze": the host's own Waze link, or else Waze's universal link, which
 * opens the app (or the website) already navigating to the spot.
 */
export function wazeUrl(location: EventLocation): string {
    if (location.maps_url && isWazeLink(location.maps_url)) {
        return location.maps_url;
    }

    return location.latitude !== null && location.longitude !== null
        ? `https://waze.com/ul?ll=${location.latitude},${location.longitude}&navigate=yes`
        : `https://waze.com/ul?q=${encodeURIComponent(location.address)}&navigate=yes`;
}

function isWazeLink(url: string): boolean {
    try {
        return /(^|\.)waze\.com$/i.test(new URL(url).hostname);
    } catch {
        return false;
    }
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

/** Buttons and highlights in general: the secondary color. */
export function accentButtonStyle(event: EventData): CSSProperties {
    const background = event.secondary_color ?? DEFAULT_SECONDARY_COLOR;

    return {
        backgroundColor: background,
        color: readableTextOn(background),
    };
}

/**
 * The gift and checkout buttons — the ones that should stand out — in the
 * button color the host picked.
 */
export function giftButtonStyle(event: EventData): CSSProperties {
    return {
        backgroundColor: event.button_color,
        color: readableTextOn(event.button_color),
    };
}

const DARK_TEXT = '#1f1f1f';

/**
 * White or dark text, whichever contrasts more with the background (WCAG
 * relative luminance) — so a light button color the host picked still
 * reads.
 */
export function readableTextOn(background: string): string {
    const luminance = relativeLuminance(background);

    if (luminance === null) {
        return '#ffffff';
    }

    const darkLuminance = relativeLuminance(DARK_TEXT) ?? 0;
    const againstWhite = 1.05 / (luminance + 0.05);
    const againstDark = (luminance + 0.05) / (darkLuminance + 0.05);

    return againstWhite >= againstDark ? '#ffffff' : DARK_TEXT;
}

function relativeLuminance(color: string): number | null {
    let hex = color.trim().replace('#', '');

    if (hex.length === 3) {
        hex = hex.replace(/./g, (digit) => digit + digit);
    }

    if (!/^[0-9a-f]{6}$/i.test(hex)) {
        return null;
    }

    const [r, g, b] = [0, 2, 4].map((start) => {
        const channel = parseInt(hex.slice(start, start + 2), 16) / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
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

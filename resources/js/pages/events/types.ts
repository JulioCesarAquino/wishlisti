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
    address: string | null;
    latitude: number | null;
    longitude: number | null;
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
    mp_public_key: string | null;
    rsvp_required_fields: RsvpContactField[];
    rsvp_collect_companions: boolean;
};

export type RsvpContactField = 'whatsapp' | 'email' | 'cpf';

export type GuestContact = {
    name: string;
    whatsapp: string | null;
    email: string | null;
    cpf: string | null;
};

export type Guest = GuestContact & {
    rsvp_status: 'confirmed' | 'declined' | null;
    rsvp_guests_count: number | null;
    companions: GuestContact[];
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
 * Builds a Google Maps "share → embed a map" iframe URL directly from
 * coordinates, using the same `pb=` format Google's own embed dialog
 * generates. This is an unofficial, undocumented format (not the paid Embed
 * API), but it needs no API key and no billing account — Google could change
 * it without notice, though it's been stable for years.
 */
export function googleMapsEmbedUrl(event: EventData): string | null {
    if (event.latitude === null || event.longitude === null) {
        return null;
    }

    const pb =
        `!1m14!1m12!1m3!1d3000!2d${event.longitude}!3d${event.latitude}` +
        '!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0' +
        '!3m2!1spt-BR!2sbr!4v1700000000000!5m2!1spt-BR!2sbr';

    return `https://www.google.com/maps/embed?pb=${pb}`;
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

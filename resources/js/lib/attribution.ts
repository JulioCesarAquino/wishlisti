import { useEffect, useState } from 'react';

/**
 * Where a visitor came from: the event page that brought them ("Feita com
 * Wishlisti" on its footer, ?ref=slug) and the ad campaign of the link
 * (utm_source, utm_medium, utm_campaign). Remembered for a while — they
 * may sign up another day — and sent along with the sign-up.
 */
export type Attribution = {
    ref: string | null;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
};

const STORAGE_KEY = 'wishlisti_attribution';

/** The key the referral used before the campaigns were kept too. */
const LEGACY_REFERRAL_KEY = 'wishlisti_ref';

/** How long the origin is remembered. */
const DAYS = 30;

const EMPTY: Attribution = {
    ref: null,
    utm_source: null,
    utm_medium: null,
    utm_campaign: null,
};

function fromUrl(): Partial<Attribution> {
    const params = new URLSearchParams(window.location.search);
    const found: Partial<Attribution> = {};

    for (const key of Object.keys(EMPTY) as (keyof Attribution)[]) {
        const value = params.get(key)?.trim();

        if (value) {
            found[key] = value.slice(0, 150);
        }
    }

    return found;
}

function readSaved(): Attribution | null {
    try {
        const saved = JSON.parse(
            window.localStorage.getItem(STORAGE_KEY) ?? 'null',
        ) as (Attribution & { at: number }) | null;

        if (saved && Date.now() - saved.at < DAYS * 24 * 60 * 60 * 1000) {
            return { ...EMPTY, ...saved };
        }

        const legacy = JSON.parse(
            window.localStorage.getItem(LEGACY_REFERRAL_KEY) ?? 'null',
        ) as { slug: string; at: number } | null;

        if (legacy && Date.now() - legacy.at < DAYS * 24 * 60 * 60 * 1000) {
            return { ...EMPTY, ref: legacy.slug };
        }
    } catch {
        // Blocked or malformed storage: only the link counts.
    }

    return null;
}

/**
 * Reads the origin from the link — a new link replaces what was kept —
 * or from what the browser kept.
 */
export function useAttribution(): Attribution {
    const [attribution, setAttribution] = useState<Attribution>(EMPTY);

    useEffect(() => {
        const linked = fromUrl();

        if (Object.keys(linked).length === 0) {
            setAttribution(readSaved() ?? EMPTY);

            return;
        }

        const next = { ...EMPTY, ...linked };
        setAttribution(next);

        try {
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ ...next, at: Date.now() }),
            );
        } catch {
            // Blocked storage: it still counts on this visit.
        }
    }, []);

    return attribution;
}

import { ChevronLeft, ChevronRight, Images } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    bodyTextStyle,
    headingStyle,
    type EventData,
    type GalleryPhoto,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    photos: GalleryPhoto[];
};

/**
 * Calls back once the image is there, also when it already was (from the
 * cache) before React got to listen for it.
 */
function useLoaded(): [
    boolean,
    (img: HTMLImageElement | null) => void,
    () => void,
] {
    const [loaded, setLoaded] = useState(false);
    const markLoaded = () => setLoaded(true);
    const ref = (img: HTMLImageElement | null) => {
        if (img?.complete && img.naturalWidth > 0) {
            setLoaded(true);
        }
    };

    return [loaded, ref, markLoaded];
}

/**
 * A grid square: the tiny blurred preview shimmers until the photo
 * arrives, then the photo "develops" over it (see .gallery-photo in
 * app.css).
 */
function GalleryTile({
    photo,
    index,
    onOpen,
}: {
    photo: GalleryPhoto;
    index: number;
    onOpen: () => void;
}) {
    const [loaded, ref, markLoaded] = useLoaded();

    return (
        <button
            type="button"
            onClick={onOpen}
            className="gallery-photo aspect-square overflow-hidden rounded-lg"
            data-loaded={loaded || undefined}
        >
            {photo.placeholder && (
                <img
                    src={photo.placeholder}
                    alt=""
                    aria-hidden
                    className="gallery-photo-placeholder"
                />
            )}
            <img
                ref={ref}
                src={photo.thumb}
                alt=""
                loading="lazy"
                decoding="async"
                onLoad={markLoaded}
                onError={markLoaded}
                // Squares that arrive together develop one after the other.
                style={{ transitionDelay: `${(index % 3) * 90}ms` }}
                className="gallery-photo-image h-full w-full object-cover"
            />
        </button>
    );
}

/**
 * The photo opened in full: the grid's copy (already loaded), until the
 * large one arrives.
 */
function FullPhoto({ photo }: { photo: GalleryPhoto }) {
    const [loaded, ref, markLoaded] = useLoaded();

    return (
        <div className="relative">
            {!loaded && (
                <img
                    src={photo.thumb}
                    alt=""
                    aria-hidden
                    className="w-full rounded-lg blur-sm"
                />
            )}
            <img
                ref={ref}
                src={photo.full}
                alt=""
                onLoad={markLoaded}
                onError={markLoaded}
                className={
                    loaded
                        ? 'w-full rounded-lg'
                        : 'absolute inset-0 h-full w-full opacity-0'
                }
            />
        </div>
    );
}

/**
 * The opened photo, with arrows to the previous and next ones (also the
 * keyboard's arrows and a swipe on the phone). It goes round: after the
 * last one comes the first.
 */
function PhotoViewer({
    photos,
    index,
    onChange,
}: {
    photos: GalleryPhoto[];
    index: number;
    onChange: (index: number) => void;
}) {
    const count = photos.length;
    const go = (step: number) => onChange((index + step + count) % count);
    const swipeStart = useRef<number | null>(null);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowLeft') {
                onChange((index - 1 + count) % count);
            } else if (event.key === 'ArrowRight') {
                onChange((index + 1) % count);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [index, count, onChange]);

    // The neighbours load in the background, to be there when asked for.
    useEffect(() => {
        if (count < 2) {
            return;
        }

        for (const step of [1, -1]) {
            new Image().src = photos[(index + step + count) % count].full;
        }
    }, [photos, index, count]);

    const arrow =
        'absolute top-1/2 z-10 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/65 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none';

    return (
        <div
            className="relative touch-pan-y select-none"
            onTouchStart={(event) => {
                swipeStart.current = event.touches[0].clientX;
            }}
            onTouchEnd={(event) => {
                if (swipeStart.current === null) {
                    return;
                }

                const distance =
                    event.changedTouches[0].clientX - swipeStart.current;
                swipeStart.current = null;

                if (Math.abs(distance) > 50) {
                    go(distance < 0 ? 1 : -1);
                }
            }}
        >
            <FullPhoto key={photos[index].full} photo={photos[index]} />

            {count > 1 && (
                <>
                    <button
                        type="button"
                        onClick={() => go(-1)}
                        className={`${arrow} left-2`}
                        aria-label="Foto anterior"
                    >
                        <ChevronLeft className="size-6" />
                    </button>
                    <button
                        type="button"
                        onClick={() => go(1)}
                        className={`${arrow} right-2`}
                        aria-label="Próxima foto"
                    >
                        <ChevronRight className="size-6" />
                    </button>
                    <span className="absolute bottom-2 left-1/2 z-10 -translate-x-1/2 rounded-full bg-black/45 px-3 py-1 text-xs font-medium text-white tabular-nums backdrop-blur-sm">
                        {index + 1} / {count}
                    </span>
                </>
            )}
        </div>
    );
}

export function GallerySection({ event, photos }: Props) {
    const [opened, setOpened] = useState<number | null>(null);

    if (photos.length === 0) {
        return (
            <div
                className="mx-auto max-w-3xl px-6 py-16 text-center"
                style={bodyTextStyle(event)}
            >
                <Images className="mx-auto mb-3 size-10 opacity-40" />
                Ainda não há fotos na galeria.
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-4xl px-6 py-8">
            <h2
                className="mb-4 text-center text-2xl font-semibold"
                style={headingStyle(event)}
            >
                Galeria
            </h2>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {photos.map((photo, index) => (
                    <GalleryTile
                        key={photo.full}
                        photo={photo}
                        index={index}
                        onOpen={() => setOpened(index)}
                    />
                ))}
            </div>

            <Dialog
                open={opened !== null}
                onOpenChange={(open) => !open && setOpened(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="sr-only">Foto</DialogTitle>
                    </DialogHeader>
                    {opened !== null && photos[opened] && (
                        <PhotoViewer
                            photos={photos}
                            index={opened}
                            onChange={setOpened}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

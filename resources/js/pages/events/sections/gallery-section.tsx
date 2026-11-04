import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    CloudDownload,
    Images,
} from 'lucide-react';
import { type CSSProperties, useEffect, useRef, useState } from 'react';
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
    type GalleryAlbum,
    type GalleryPhoto,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    albums: GalleryAlbum[];
    /** The album opened, from the URL ("#galeria/12"); null on the list. */
    openedAlbum: string | null;
    onOpenAlbum: (id: string | null) => void;
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

            {/* A link to the server, which sends the original as an
                attachment: saved on phones too, not opened in a tab. */}
            <a
                href={photos[index].download}
                className="absolute top-2 left-2 z-10 flex size-10 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/65 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                aria-label="Baixar foto"
                title="Baixar foto"
            >
                <CloudDownload className="size-5" />
            </a>

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

/** The photos in a grid, each one opening in the viewer. */
function PhotoGrid({ photos }: { photos: GalleryPhoto[] }) {
    const [opened, setOpened] = useState<number | null>(null);

    return (
        <>
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
        </>
    );
}

/**
 * Where each photo behind the cover goes, furthest back first: turned and
 * pushed to alternate sides, like cards opened in a fan.
 */
const FAN_POSITIONS = [
    { rotate: -8, shift: -9 },
    { rotate: 8, shift: 9 },
    { rotate: -15, shift: -16 },
];

/**
 * An album on the list: the cover in front, a few of its other photos
 * fanned out behind it, and the name below. The fan opens a little more
 * on hover (see .album-card in app.css).
 */
function AlbumCard({
    event,
    album,
    onOpen,
}: {
    event: EventData;
    album: GalleryAlbum;
    onOpen: () => void;
}) {
    const [loaded, ref, markLoaded] = useLoaded();
    const fan = album.fan.slice(0, FAN_POSITIONS.length);
    const count = album.photos.length;

    return (
        <button
            type="button"
            onClick={onOpen}
            className="album-card group flex w-full flex-col items-center rounded-xl px-6 pt-6 pb-2 focus-visible:ring-2 focus-visible:ring-black/30 focus-visible:outline-none"
        >
            <div className="relative aspect-square w-full max-w-72">
                {fan
                    .map((photo, index) => (
                        <img
                            key={photo.full}
                            src={photo.thumb}
                            alt=""
                            aria-hidden
                            loading="lazy"
                            decoding="async"
                            className="album-fan-photo absolute inset-[6%] h-[88%] w-[88%] rounded-lg object-cover shadow-md ring-4 ring-white"
                            style={
                                {
                                    '--fan-rotate': `${FAN_POSITIONS[index].rotate}deg`,
                                    '--fan-shift': `${FAN_POSITIONS[index].shift}%`,
                                } as CSSProperties
                            }
                        />
                    ))
                    // The furthest back is drawn first.
                    .reverse()}
                {/* .gallery-photo sets its own position: the wrapper places it. */}
                <div className="album-fan-cover absolute inset-[6%] rounded-lg shadow-lg ring-4 ring-white">
                    <div
                        className="gallery-photo h-full w-full overflow-hidden rounded-lg"
                        data-loaded={loaded || undefined}
                    >
                        {album.cover.placeholder && (
                            <img
                                src={album.cover.placeholder}
                                alt=""
                                aria-hidden
                                className="gallery-photo-placeholder"
                            />
                        )}
                        <img
                            ref={ref}
                            src={album.cover.thumb}
                            alt=""
                            loading="lazy"
                            decoding="async"
                            onLoad={markLoaded}
                            onError={markLoaded}
                            className="gallery-photo-image h-full w-full object-cover"
                        />
                    </div>
                </div>
            </div>
            <span
                className="album-name mt-5 text-xl font-semibold"
                style={headingStyle(event)}
            >
                {album.name}
            </span>
            <span className="text-sm" style={bodyTextStyle(event)}>
                {count === 1 ? '1 foto' : `${count} fotos`}
            </span>
        </button>
    );
}

/**
 * With a single album (free events always), its photos straight away, as
 * a plain gallery. With more, a card per album — side by side on wide
 * screens, one under the other on phones — that opens its photos.
 */
export function GallerySection({
    event,
    albums,
    openedAlbum,
    onOpenAlbum,
}: Props) {
    if (albums.length === 0) {
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

    const album =
        albums.length === 1
            ? albums[0]
            : albums.find((item) => String(item.id) === openedAlbum);

    if (album) {
        return (
            <div className="mx-auto max-w-4xl px-6 py-8">
                {albums.length > 1 && (
                    <button
                        type="button"
                        onClick={() => onOpenAlbum(null)}
                        className="mb-2 inline-flex items-center gap-1 text-sm font-medium hover:underline"
                        style={bodyTextStyle(event)}
                    >
                        <ArrowLeft className="size-4" />
                        Todos os álbuns
                    </button>
                )}
                <h2
                    className="mb-4 text-center text-2xl font-semibold"
                    style={headingStyle(event)}
                >
                    {albums.length > 1 ? album.name : 'Galeria'}
                </h2>
                <PhotoGrid key={album.id} photos={album.photos} />
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl px-6 py-8">
            <h2
                className="mb-6 text-center text-2xl font-semibold"
                style={headingStyle(event)}
            >
                Galeria
            </h2>
            {/* Flex rather than grid: a last row with fewer albums sits centered. */}
            <div className="flex flex-wrap justify-center gap-x-6 gap-y-10">
                {albums.map((item) => (
                    <div
                        key={item.id}
                        className="w-full md:w-[calc((100%-1.5rem)/2)] lg:w-[calc((100%-3rem)/3)]"
                    >
                        <AlbumCard
                            event={event}
                            album={item}
                            onOpen={() => {
                                onOpenAlbum(String(item.id));
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

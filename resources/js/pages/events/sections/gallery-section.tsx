import { Images } from 'lucide-react';
import { useState } from 'react';
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

export function GallerySection({ event, photos }: Props) {
    const [preview, setPreview] = useState<GalleryPhoto | null>(null);

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
                        onOpen={() => setPreview(photo)}
                    />
                ))}
            </div>

            <Dialog
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="sr-only">Foto</DialogTitle>
                    </DialogHeader>
                    {preview && (
                        <FullPhoto key={preview.full} photo={preview} />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

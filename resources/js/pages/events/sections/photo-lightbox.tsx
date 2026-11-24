import * as DialogPrimitive from '@radix-ui/react-dialog';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Loader2,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { type GalleryPhoto } from '@/pages/events/types';

const MAX_SCALE = 4;
const DOUBLE_TAP_SCALE = 2.5;

type Point = { x: number; y: number };

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

/**
 * Phones (and tablets) whose browser can hand a picture to the system's
 * share sheet — on the iPhone it has "Salvar Imagem", which goes to the
 * Photos app. A web page can't write to the gallery any other way.
 */
function canShareFiles(): boolean {
    try {
        return (
            window.matchMedia('(pointer: coarse)').matches &&
            typeof navigator.canShare === 'function' &&
            navigator.canShare({
                files: [new File([''], 'foto.jpg', { type: 'image/jpeg' })],
            })
        );
    } catch {
        return false;
    }
}

/** The original, as a file to share; fetched once per photo. */
const originals = new Map<string, Promise<File>>();

function original(photo: GalleryPhoto, name: string): Promise<File> {
    let file = originals.get(photo.download);

    if (!file) {
        file = fetch(photo.download)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('download failed');
                }

                return response.blob();
            })
            .then(
                (blob) =>
                    new File([blob], name, { type: blob.type || 'image/jpeg' }),
            );
        file.catch(() => originals.delete(photo.download));
        originals.set(photo.download, file);
    }

    return file;
}

/**
 * The photo, zoomable: pinch and double tap on the phone, the wheel and a
 * double click on the computer; dragged around while zoomed in. Not zoomed,
 * a sideways swipe goes to the next or previous photo.
 */
function ZoomablePhoto({
    photo,
    onSwipe,
    zoomRef,
}: {
    photo: GalleryPhoto;
    onSwipe: (step: number) => void;
    zoomRef: React.MutableRefObject<((by: number) => void) | null>;
}) {
    const box = useRef<HTMLDivElement>(null);
    const img = useRef<HTMLImageElement>(null);
    const [view, setView] = useState({ scale: 1, x: 0, y: 0 });
    const [gesturing, setGesturing] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const viewRef = useRef(view);
    viewRef.current = view;

    const pointers = useRef(new Map<number, Point>());
    const gesture = useRef<{
        start: Point;
        moved: boolean;
        pinch: { distance: number; scale: number; mid: Point } | null;
    } | null>(null);
    const lastTap = useRef<{ time: number; point: Point } | null>(null);

    /** Keeps the photo covering the screen while zoomed in. */
    const bounded = (scale: number, x: number, y: number) => {
        const image = img.current;
        const frame = box.current;

        if (!image || !frame || scale <= 1) {
            return { scale: Math.max(scale, 1), x: 0, y: 0 };
        }

        const maxX = Math.max(
            0,
            (image.offsetWidth * scale - frame.clientWidth) / 2,
        );
        const maxY = Math.max(
            0,
            (image.offsetHeight * scale - frame.clientHeight) / 2,
        );

        return { scale, x: clamp(x, -maxX, maxX), y: clamp(y, -maxY, maxY) };
    };

    /** Zooms keeping the point under the finger (from the centre) still. */
    const zoomAt = (scale: number, focus: Point) => {
        const current = viewRef.current;
        const next = clamp(scale, 1, MAX_SCALE);
        const ratio = next / current.scale;

        setView(
            bounded(
                next,
                focus.x - (focus.x - current.x) * ratio,
                focus.y - (focus.y - current.y) * ratio,
            ),
        );
    };

    const fromCentre = (clientX: number, clientY: number): Point => {
        const rect = box.current!.getBoundingClientRect();

        return {
            x: clientX - rect.left - rect.width / 2,
            y: clientY - rect.top - rect.height / 2,
        };
    };

    // The zoom buttons of the top bar.
    useEffect(() => {
        zoomRef.current = (by: number) =>
            zoomAt(viewRef.current.scale * by, { x: 0, y: 0 });

        return () => {
            zoomRef.current = null;
        };
    });

    // The wheel zooms (a listener of our own: React's can't stop the page).
    useEffect(() => {
        const frame = box.current;

        if (!frame) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            event.preventDefault();
            zoomAt(
                viewRef.current.scale * (event.deltaY < 0 ? 1.15 : 1 / 1.15),
                fromCentre(event.clientX, event.clientY),
            );
        };

        frame.addEventListener('wheel', onWheel, { passive: false });

        return () => frame.removeEventListener('wheel', onWheel);
    });

    const onPointerDown = (event: React.PointerEvent) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        pointers.current.set(event.pointerId, {
            x: event.clientX,
            y: event.clientY,
        });
        setGesturing(true);

        const points = [...pointers.current.values()];

        if (points.length === 1) {
            gesture.current = {
                start: points[0],
                moved: false,
                pinch: null,
            };
        } else if (points.length === 2 && gesture.current) {
            const [a, b] = points;
            gesture.current.moved = true;
            gesture.current.pinch = {
                distance: Math.hypot(a.x - b.x, a.y - b.y),
                scale: viewRef.current.scale,
                mid: fromCentre((a.x + b.x) / 2, (a.y + b.y) / 2),
            };
        }
    };

    const onPointerMove = (event: React.PointerEvent) => {
        const previous = pointers.current.get(event.pointerId);

        if (!previous || !gesture.current) {
            return;
        }

        const point = { x: event.clientX, y: event.clientY };
        pointers.current.set(event.pointerId, point);
        const points = [...pointers.current.values()];

        if (
            Math.hypot(
                point.x - gesture.current.start.x,
                point.y - gesture.current.start.y,
            ) > 8
        ) {
            gesture.current.moved = true;
        }

        if (points.length === 2 && gesture.current.pinch) {
            const [a, b] = points;
            const { distance, scale, mid } = gesture.current.pinch;
            zoomAt(scale * (Math.hypot(a.x - b.x, a.y - b.y) / distance), mid);
        } else if (points.length === 1 && viewRef.current.scale > 1) {
            const current = viewRef.current;
            setView(
                bounded(
                    current.scale,
                    current.x + point.x - previous.x,
                    current.y + point.y - previous.y,
                ),
            );
        }
    };

    const onPointerUp = (event: React.PointerEvent) => {
        const start = gesture.current;
        pointers.current.delete(event.pointerId);

        if (pointers.current.size > 0) {
            // One finger of a pinch lifted: the other one goes on panning.
            if (start) {
                start.pinch = null;
            }

            return;
        }

        setGesturing(false);
        gesture.current = null;

        if (!start) {
            return;
        }

        const dx = event.clientX - start.start.x;
        const dy = event.clientY - start.start.y;

        // A sideways swipe, not zoomed: the next or previous photo.
        if (
            viewRef.current.scale === 1 &&
            Math.abs(dx) > 50 &&
            Math.abs(dx) > Math.abs(dy)
        ) {
            onSwipe(dx < 0 ? 1 : -1);

            return;
        }

        if (start.moved) {
            return;
        }

        // A double tap (or click) zooms in there, or back out.
        const now = Date.now();
        const tap = lastTap.current;
        const point = { x: event.clientX, y: event.clientY };

        if (
            tap &&
            now - tap.time < 300 &&
            Math.hypot(point.x - tap.point.x, point.y - tap.point.y) < 30
        ) {
            lastTap.current = null;

            if (viewRef.current.scale > 1) {
                setView({ scale: 1, x: 0, y: 0 });
            } else {
                zoomAt(DOUBLE_TAP_SCALE, fromCentre(point.x, point.y));
            }
        } else {
            lastTap.current = { time: now, point };
        }
    };

    const transform = `translate(${view.x}px, ${view.y}px) scale(${view.scale})`;

    return (
        <div
            ref={box}
            className={cn(
                'relative flex h-full w-full touch-none items-center justify-center overflow-hidden select-none',
                view.scale > 1
                    ? 'cursor-grab active:cursor-grabbing'
                    : 'cursor-zoom-in',
            )}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
        >
            {/* The grid's copy, blurred, until the large one arrives. */}
            {!loaded && (
                <img
                    src={photo.thumb}
                    alt=""
                    aria-hidden
                    className="absolute max-h-full max-w-full object-contain blur-sm"
                    style={{ width: '100%', height: '100%' }}
                />
            )}
            <img
                ref={img}
                src={photo.full}
                alt=""
                draggable={false}
                onLoad={() => setLoaded(true)}
                onError={() => setLoaded(true)}
                className={cn(
                    'max-h-full max-w-full object-contain will-change-transform',
                    !gesturing && 'transition-transform duration-200 ease-out',
                    loaded ? 'opacity-100' : 'opacity-0',
                )}
                style={{ transform }}
            />
        </div>
    );
}

/**
 * The opened photo, on the whole screen: zoom, the previous and next ones
 * (arrows, the keyboard, a swipe), and the button to keep it — on phones,
 * through the share sheet ("Salvar Imagem" puts it in the Photos app); on
 * computers, as a download.
 */
export function PhotoLightbox({
    photos,
    index,
    onChange,
    onClose,
}: {
    photos: GalleryPhoto[];
    index: number;
    onChange: (index: number) => void;
    onClose: () => void;
}) {
    const count = photos.length;
    const photo = photos[index];
    const go = (step: number) => onChange((index + step + count) % count);
    const zoom = useRef<((by: number) => void) | null>(null);
    const [shareable, setShareable] = useState(false);
    const [saving, setSaving] = useState(false);
    const name = `foto-${index + 1}.jpg`;

    useEffect(() => setShareable(canShareFiles()), []);

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

    // On phones, the original of the photo being looked at is fetched ahead:
    // the share sheet must open right on the tap (iOS refuses it later).
    useEffect(() => {
        if (!shareable) {
            return;
        }

        const timer = setTimeout(() => {
            original(photo, name).catch(() => {});
        }, 500);

        return () => clearTimeout(timer);
    }, [shareable, photo, name]);

    const save = async () => {
        if (!shareable) {
            // The server sends it as an attachment: saved, not opened.
            window.location.href = photo.download;

            return;
        }

        setSaving(true);

        try {
            const file = await original(photo, name);
            await navigator.share({ files: [file] });
        } catch (error) {
            if ((error as DOMException)?.name === 'AbortError') {
                return;
            }

            // Too late for the share sheet, or none: a plain download.
            window.location.href = photo.download;
        } finally {
            setSaving(false);
        }
    };

    const button =
        'flex size-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none';

    return (
        <DialogPrimitive.Root open onOpenChange={(open) => !open && onClose()}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/95 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    // The demo's drag-to-scroll leaves the photo to its own pan.
                    data-no-drag-scroll
                    className="fixed inset-0 z-50 flex flex-col outline-none data-[state=open]:animate-in data-[state=open]:fade-in-0"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Foto {index + 1} de {count}
                    </DialogPrimitive.Title>

                    <div className="flex shrink-0 items-center justify-between gap-2 px-3 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2 text-white">
                        <span className="min-w-14 text-sm font-medium tabular-nums opacity-80">
                            {count > 1 && `${index + 1} / ${count}`}
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => zoom.current?.(1 / 1.5)}
                                className={cn(button, 'hidden sm:flex')}
                                aria-label="Diminuir zoom"
                            >
                                <ZoomOut className="size-5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => zoom.current?.(1.5)}
                                className={cn(button, 'hidden sm:flex')}
                                aria-label="Aumentar zoom"
                            >
                                <ZoomIn className="size-5" />
                            </button>
                            <button
                                type="button"
                                onClick={save}
                                disabled={saving}
                                className="flex h-10 items-center gap-2 rounded-full bg-white px-4 text-sm font-semibold text-neutral-900 transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none disabled:opacity-70"
                            >
                                {saving ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <Download className="size-4" />
                                )}
                                {shareable ? 'Salvar' : 'Baixar'}
                            </button>
                            <DialogPrimitive.Close
                                className={button}
                                aria-label="Fechar"
                            >
                                <X className="size-5" />
                            </DialogPrimitive.Close>
                        </div>
                    </div>

                    <div className="relative min-h-0 flex-1">
                        <ZoomablePhoto
                            key={photo.full}
                            photo={photo}
                            onSwipe={go}
                            zoomRef={zoom}
                        />

                        {count > 1 && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => go(-1)}
                                    className={cn(
                                        button,
                                        'absolute top-1/2 left-3 hidden -translate-y-1/2 sm:flex',
                                    )}
                                    aria-label="Foto anterior"
                                >
                                    <ChevronLeft className="size-6" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => go(1)}
                                    className={cn(
                                        button,
                                        'absolute top-1/2 right-3 hidden -translate-y-1/2 sm:flex',
                                    )}
                                    aria-label="Próxima foto"
                                >
                                    <ChevronRight className="size-6" />
                                </button>
                            </>
                        )}
                    </div>

                    <p className="shrink-0 px-4 pt-2 pb-[max(0.75rem,env(safe-area-inset-bottom))] text-center text-xs text-white/60">
                        {shareable
                            ? 'Toque duas vezes ou use dois dedos para dar zoom. Em "Salvar", escolha "Salvar Imagem" para guardar no app Fotos.'
                            : 'Clique duas vezes ou use a roda do mouse para dar zoom.'}
                    </p>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Whether the page runs inside the home page's phone (an iframe). There it
 * shows as a guest sees it, without desktop scrollbars (see app.css).
 */
export function useEmbeddedDemo(): boolean {
    const [embedded, setEmbedded] = useState(false);

    useEffect(() => {
        const inside = window.self !== window.top;
        setEmbedded(inside);
        document.documentElement.classList.toggle('embedded-demo', inside);
    }, []);

    return embedded;
}

type Axis = 'x' | 'y';

/** The nearest element under the pointer that scrolls along the axis. */
function scrollableAncestor(start: Element | null, axis: Axis): Element | null {
    for (
        let node = start instanceof HTMLElement ? start : null;
        node && node !== document.body;
        node = node.parentElement
    ) {
        const style = getComputedStyle(node);
        const overflow = axis === 'x' ? style.overflowX : style.overflowY;
        const overflows =
            axis === 'x'
                ? node.scrollWidth > node.clientWidth
                : node.scrollHeight > node.clientHeight;

        if ((overflow === 'auto' || overflow === 'scroll') && overflows) {
            return node;
        }
    }

    return axis === 'y' ? document.scrollingElement : null;
}

/**
 * Click and drag scrolls, as a finger would on a phone: sideways on the
 * tabs, up and down on the page. A plain click still clicks: it only turns
 * into a drag after a few pixels.
 */
function useDragToScroll(enabled: boolean) {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        let start: { x: number; y: number; target: Element | null } | null =
            null;
        let last = { x: 0, y: 0 };
        let dragging: { axis: Axis; element: Element | null } | null = null;

        const onDown = (event: PointerEvent) => {
            const target = event.target as Element | null;

            if (
                event.pointerType !== 'mouse' ||
                event.button !== 0 ||
                target?.closest(
                    'input, textarea, select, [contenteditable], [data-no-drag-scroll]',
                )
            ) {
                return;
            }

            start = { x: event.clientX, y: event.clientY, target };
            last = { x: event.clientX, y: event.clientY };
            dragging = null;
        };

        const onMove = (event: PointerEvent) => {
            if (!start) {
                return;
            }

            if (!dragging) {
                const dx = event.clientX - start.x;
                const dy = event.clientY - start.y;

                if (Math.hypot(dx, dy) < 6) {
                    return;
                }

                const axis: Axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                dragging = {
                    axis,
                    element: scrollableAncestor(start.target, axis),
                };
                document.documentElement.classList.add('drag-scrolling');
            }

            if (dragging.element) {
                if (dragging.axis === 'x') {
                    dragging.element.scrollLeft -= event.clientX - last.x;
                } else {
                    dragging.element.scrollTop -= event.clientY - last.y;
                }
            }

            last = { x: event.clientX, y: event.clientY };
        };

        const swallowClick = (event: MouseEvent) => {
            event.preventDefault();
            event.stopPropagation();
        };

        const onUp = () => {
            if (dragging) {
                // The click that ends a drag isn't a click on what's below.
                window.addEventListener('click', swallowClick, {
                    capture: true,
                    once: true,
                });
                setTimeout(
                    () =>
                        window.removeEventListener('click', swallowClick, {
                            capture: true,
                        }),
                    0,
                );
                document.documentElement.classList.remove('drag-scrolling');
            }

            start = null;
            dragging = null;
        };

        // Links and pictures would start the browser's own drag.
        const onDragStart = (event: DragEvent) => event.preventDefault();

        window.addEventListener('pointerdown', onDown);
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onUp);
        window.addEventListener('dragstart', onDragStart);

        return () => {
            window.removeEventListener('pointerdown', onDown);
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
            window.removeEventListener('pointercancel', onUp);
            window.removeEventListener('dragstart', onDragStart);
        };
    }, [enabled]);
}

/**
 * A finger swiping, as the hint: a dot slides in a straight line, leaving
 * a trail that becomes an arrow, then fades — over and over.
 */
const SWIPES = {
    right: {
        size: [84, 36],
        trail: 'M10 18 H72',
        head: 'M63 9 L73 18 L63 27',
        finger: [10, 18],
        className: 'swipe-x',
    },
    down: {
        size: [36, 84],
        trail: 'M18 10 V72',
        head: 'M9 63 L18 73 L27 63',
        finger: [18, 10],
        className: 'swipe-y',
    },
} as const;

function SwipeHint({
    direction,
    color,
}: {
    direction: keyof typeof SWIPES;
    color: string;
}) {
    const swipe = SWIPES[direction];
    const [width, height] = swipe.size;
    const [cx, cy] = swipe.finger;

    return (
        <svg
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            fill="none"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={cn('overflow-visible drop-shadow', swipe.className)}
        >
            {/* A light outline under each stroke, to stand out on photos. */}
            <path
                d={swipe.trail}
                stroke="white"
                strokeOpacity={0.9}
                strokeWidth={8}
                pathLength={1}
                className="swipe-trail"
            />
            <path
                d={swipe.trail}
                stroke={color}
                strokeWidth={4}
                pathLength={1}
                className="swipe-trail"
            />
            <path
                d={swipe.head}
                stroke="white"
                strokeOpacity={0.9}
                strokeWidth={8}
                className="swipe-head"
            />
            <path
                d={swipe.head}
                stroke={color}
                strokeWidth={4}
                className="swipe-head"
            />
            <circle
                cx={cx}
                cy={cy}
                r={9}
                fill={color}
                fillOpacity={0.9}
                stroke="white"
                strokeWidth={3}
                className="swipe-finger"
            />
        </svg>
    );
}

/** How long the hints stay, unless the visitor moves first. */
const HINT_MS = 5500;

/**
 * Inside the home page's phone: drag to scroll, and two arrows, once, on
 * opening — to the right of the tabs when some don't fit, and at the bottom
 * when the page goes on below.
 */
export function EmbeddedDemoAids({ color }: { color: string }) {
    const embedded = useEmbeddedDemo();
    const [hints, setHints] = useState<{
        tabs: { top: number; height: number } | null;
        page: boolean;
    }>({ tabs: null, page: false });
    const [leaving, setLeaving] = useState(false);

    useDragToScroll(embedded);

    useEffect(() => {
        if (!embedded) {
            return;
        }

        let hideTimer: ReturnType<typeof setTimeout> | undefined;

        const hide = () => {
            setLeaving(true);
            setTimeout(() => setHints({ tabs: null, page: false }), 400);
            window.removeEventListener('scroll', hide);
            window.removeEventListener('pointerdown', hide);
            window.removeEventListener('wheel', hide);
        };

        // After the page has laid out (fonts, cover).
        const showTimer = setTimeout(() => {
            const tabs = document.querySelector('[data-tabs-scroller]');
            const rect = tabs?.getBoundingClientRect();
            const page = document.scrollingElement;

            setHints({
                tabs:
                    tabs && rect && tabs.scrollWidth > tabs.clientWidth + 4
                        ? { top: rect.top, height: rect.height }
                        : null,
                page: page
                    ? page.scrollHeight > window.innerHeight + 40
                    : false,
            });

            window.addEventListener('scroll', hide, { passive: true });
            window.addEventListener('pointerdown', hide);
            window.addEventListener('wheel', hide, { passive: true });
            hideTimer = setTimeout(hide, HINT_MS);
        }, 700);

        return () => {
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
            window.removeEventListener('scroll', hide);
            window.removeEventListener('pointerdown', hide);
            window.removeEventListener('wheel', hide);
        };
    }, [embedded]);

    if (!embedded || (!hints.tabs && !hints.page)) {
        return null;
    }

    const fade = cn(
        'pointer-events-none fixed z-[60] transition-opacity duration-400',
        leaving ? 'opacity-0' : 'opacity-100',
    );

    return (
        <>
            {hints.tabs && (
                <div
                    className={cn(fade, 'right-1')}
                    // On the line of the tabs, over the ones that don't fit.
                    style={{
                        top:
                            hints.tabs.top +
                            (hints.tabs.height - SWIPES.right.size[1]) / 2,
                    }}
                    aria-hidden
                >
                    <SwipeHint direction="right" color={color} />
                </div>
            )}
            {hints.page && (
                <div
                    className={cn(fade, 'bottom-16 left-1/2 -translate-x-1/2')}
                    aria-hidden
                >
                    <SwipeHint direction="down" color={color} />
                </div>
            )}
        </>
    );
}

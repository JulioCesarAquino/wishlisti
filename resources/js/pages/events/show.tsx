import { Head, router, useForm } from '@inertiajs/react';
import { Gift, Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { store as storeOrder } from '@/routes/orders';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { getCsrfToken } from '@/lib/csrf';
import {
    ContactFields,
    guestContactFields,
    toContactForm,
} from '@/pages/events/sections/contact-fields';
import { Footer } from '@/pages/events/sections/footer';
import { GuestbookSection } from '@/pages/events/sections/guestbook-section';
import { NoGiftsMessage } from '@/pages/events/sections/no-gifts-message';
import { HomeSection } from '@/pages/events/sections/home-section';
import { LocationSection } from '@/pages/events/sections/location-section';
import { GallerySection } from '@/pages/events/sections/gallery-section';
import { GiftsSection } from '@/pages/events/sections/gifts-section';
import { MercadoPagoCheckout } from '@/pages/events/sections/mercadopago-checkout';
import { RsvpSection } from '@/pages/events/sections/rsvp-section';
import {
    type Fulfillment,
    accentButtonStyle,
    bodyTextStyle,
    formatCurrency,
    FONT_FAMILIES,
    grainBackgroundStyle,
    mutedTextCssVars,
    headingStyle,
    type EventData,
    type Guest,
    type GuestMessage,
    type Product,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    products: Product[];
    guest: Guest | null;
    messages: GuestMessage[];
    is_preview: boolean;
    initial_section: Section | null;
    initial_location: string | null;
};

type Section =
    | 'inicio'
    | 'galeria'
    | 'presentes'
    | 'confirmar-presenca'
    | 'recados'
    | 'localizacao';

/**
 * The gift list only gets its own menu item in the "list" display mode
 * (discreet tucks it into the home section, "none" hides it), and the
 * guestbook only exists on premium events.
 */
function navItems(event: EventData): { key: Section; label: string }[] {
    return [
        { key: 'inicio', label: 'Início' },
        { key: 'galeria', label: 'Galeria' },
        ...(event.gift_display_mode === 'list'
            ? [{ key: 'presentes' as const, label: 'Presentes' }]
            : []),
        { key: 'confirmar-presenca', label: 'Confirmar presença' },
        ...(event.has_guestbook
            ? [{ key: 'recados' as const, label: 'Recados' }]
            : []),
        ...(event.locations.length > 0
            ? [{ key: 'localizacao' as const, label: 'Localização' }]
            : []),
    ];
}

function cartStorageKey(slug: string): string {
    return `wishlisti_cart_${slug}`;
}

type Place = { section: Section; location: string | null };

/**
 * Where the page is, from the URL hash ("#galeria", "#localizacao/festa").
 * Without a hash, it's where the link pointed to (a location link opens on
 * the location tab), or the home section.
 */
function placeFromUrl(items: { key: Section }[], fallback: Place): Place {
    const [hash, location = null] = window.location.hash
        .replace('#', '')
        .split('/');

    if (!hash) {
        return fallback;
    }

    const match = items.find((item) => item.key === hash);

    return match
        ? { section: match.key, location }
        : { section: 'inicio', location: null };
}

export default function EventShow({
    event,
    products,
    guest,
    messages,
    is_preview,
    initial_section,
    initial_location,
}: Props) {
    const items = useMemo(() => navItems(event), [event]);
    const linkedPlace = useMemo<Place>(
        () =>
            initial_section &&
            items.some((item) => item.key === initial_section)
                ? { section: initial_section, location: initial_location }
                : { section: 'inicio', location: null },
        [items, initial_section, initial_location],
    );
    const [section, setSection] = useState<Section>(linkedPlace.section);
    const [focusedLocation, setFocusedLocation] = useState<string | null>(
        linkedPlace.location,
    );
    const [showDiscreetGifts, setShowDiscreetGifts] = useState(false);
    const [freeAmountOpen, setFreeAmountOpen] = useState(false);
    const [freeAmount, setFreeAmount] = useState('');
    const [cart, setCart] = useState<Record<number, number>>({});
    const [cartOpen, setCartOpen] = useState(false);

    const font = FONT_FAMILIES[event.font_family ?? 'default'];

    useEffect(() => {
        const sync = () => {
            const place = placeFromUrl(items, linkedPlace);
            setSection(place.section);
            setFocusedLocation(place.location);
        };

        sync();
        window.addEventListener('hashchange', sync);

        return () => window.removeEventListener('hashchange', sync);
    }, [items, linkedPlace]);

    useEffect(() => {
        try {
            // Falls back to the key from before the project was renamed, so a
            // cart saved earlier isn't lost (it's rewritten under the new key
            // on the next change).
            const raw =
                window.localStorage.getItem(cartStorageKey(event.slug)) ??
                window.localStorage.getItem(`wishlist_cart_${event.slug}`);

            if (raw) {
                setCart(JSON.parse(raw));
            }
        } catch {
            // ignore malformed/blocked storage
        }
    }, [event.slug]);

    const navigate = (key: Section, location: string | null = null) => {
        window.location.hash = location ? `${key}/${location}` : key;
        setSection(key);
        setFocusedLocation(location);
    };

    const persistCart = (next: Record<number, number>) => {
        setCart(next);

        try {
            window.localStorage.setItem(
                cartStorageKey(event.slug),
                JSON.stringify(next),
            );
        } catch {
            // ignore blocked storage (private mode, etc.)
        }
    };

    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );

    const addToCart = (product: Product) => {
        const current = cart[product.id] ?? 0;

        if (current >= product.quantity_available) {
            return;
        }

        persistCart({ ...cart, [product.id]: current + 1 });
    };

    const changeQuantity = (productId: number, delta: number) => {
        const product = productsById.get(productId);
        const current = cart[productId] ?? 0;
        const next = current + delta;

        if (next <= 0) {
            const { [productId]: _removed, ...rest } = cart;
            persistCart(rest);
            return;
        }

        if (product && next > product.quantity_available) {
            return;
        }

        persistCart({ ...cart, [productId]: next });
    };

    const removeFromCart = (productId: number) => {
        const { [productId]: _removed, ...rest } = cart;
        persistCart(rest);
    };

    const cartLines = Object.entries(cart)
        .map(([id, quantity]) => ({
            product: productsById.get(Number(id)),
            quantity,
        }))
        .filter(
            (line): line is { product: Product; quantity: number } =>
                line.product !== undefined,
        );

    const cartCount = cartLines.reduce((sum, line) => sum + line.quantity, 0);
    const cartTotal = cartLines.reduce(
        (sum, line) => sum + line.product.price * line.quantity,
        0,
    );

    const form = useForm({
        guest: toContactForm(guest),
        message: '',
        anonymous: false,
        items: [] as { event_product_id: number; quantity: number }[],
    });

    // The guest may identify themselves after the page loaded (confirming
    // attendance, say): fill the cart with it, unless they typed already.
    useEffect(() => {
        if (guest && !form.data.guest.name) {
            form.setData('guest', toContactForm(guest));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [guest]);

    const [submitting, setSubmitting] = useState(false);
    const [orderResult, setOrderResult] = useState<{
        id: number;
        total_amount: number;
        fulfillment: Fulfillment;
        is_anonymous: boolean;
    } | null>(null);
    const [confirmedLines, setConfirmedLines] = useState<
        { product: Product; quantity: number }[]
    >([]);

    const submitOrder = async (fulfillment: Fulfillment) => {
        setSubmitting(true);
        form.clearErrors();

        try {
            const response = await fetch(
                storeOrder({ event: event.slug }).url,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({
                        guest: form.data.guest,
                        message: form.data.message,
                        anonymous: form.data.anonymous,
                        fulfillment,
                        ...(freeAmountOpen
                            ? { free_amount: Number(freeAmount) }
                            : {
                                  items: cartLines.map((line) => ({
                                      event_product_id: line.product.id,
                                      quantity: line.quantity,
                                  })),
                              }),
                    }),
                },
            );

            if (response.status === 422) {
                const body = await response.json();
                const mapped = Object.fromEntries(
                    Object.entries(body.errors as Record<string, string[]>).map(
                        ([key, messages]) => [key, messages[0]],
                    ),
                );
                form.setError(mapped as Parameters<typeof form.setError>[0]);
                return;
            }

            if (!response.ok) {
                throw new Error('unexpected response');
            }

            const body = await response.json();
            setConfirmedLines(freeAmountOpen ? [] : cartLines);
            setOrderResult(body.order);

            if (!freeAmountOpen) {
                persistCart({});
            }

            // A reservation takes the stock right away: refresh the list and
            // the guest's reservations.
            if (fulfillment === 'in_person') {
                router.reload({ only: ['products', 'guest'] });
            }
        } catch {
            toast.error(
                'Não foi possível registrar o presente. Tente novamente.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    const openFreeAmount = () => {
        setFreeAmountOpen(true);
        setCartOpen(true);
    };

    const giftsSection = (discreet: boolean) => (
        <GiftsSection
            event={event}
            products={products}
            cart={cart}
            onAddToCart={addToCart}
            reservations={guest?.reservations ?? []}
            discreet={discreet}
            onContribute={openFreeAmount}
        />
    );

    return (
        <>
            <Head title={event.title}>
                {font.googleFontsHref && (
                    <link rel="stylesheet" href={font.googleFontsHref} />
                )}
            </Head>

            {is_preview && (
                <div className="sticky top-0 z-50 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
                    Modo prévia — esta página ainda não está publicada e só você
                    pode vê-la.
                </div>
            )}

            <div
                className="event-page flex min-h-screen flex-col"
                style={{
                    ...grainBackgroundStyle(event.primary_color),
                    ...mutedTextCssVars(event),
                }}
            >
                <nav className="sticky top-0 z-40 border-b border-black/5 bg-white/40 backdrop-blur">
                    <div className="mx-auto flex max-w-4xl items-center gap-1 overflow-x-auto px-4 py-3">
                        {items.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => navigate(item.key)}
                                className="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium tracking-wide uppercase transition-colors"
                                style={{
                                    ...bodyTextStyle(event),
                                    ...(section === item.key
                                        ? accentButtonStyle(event)
                                        : {}),
                                }}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </nav>

                <div className="flex-1">
                    {section === 'inicio' && (
                        <>
                            <HomeSection event={event} />
                            {event.gift_display_mode === 'discreet' && (
                                <div className="pb-8 text-center">
                                    {showDiscreetGifts ? (
                                        giftsSection(true)
                                    ) : (
                                        <Button
                                            variant="link"
                                            style={bodyTextStyle(event)}
                                            onClick={() =>
                                                setShowDiscreetGifts(true)
                                            }
                                        >
                                            Se quiser presentear
                                        </Button>
                                    )}
                                </div>
                            )}
                            {event.gift_display_mode === 'none' && (
                                <NoGiftsMessage
                                    event={event}
                                    onContribute={openFreeAmount}
                                />
                            )}
                        </>
                    )}
                    {section === 'galeria' && (
                        <GallerySection
                            event={event}
                            galleryUrls={event.gallery_urls}
                        />
                    )}
                    {section === 'presentes' && giftsSection(false)}
                    {section === 'confirmar-presenca' && (
                        <RsvpSection event={event} guest={guest} />
                    )}
                    {section === 'localizacao' && (
                        <LocationSection
                            event={event}
                            focused={focusedLocation}
                            onFocus={(slug) => navigate('localizacao', slug)}
                        />
                    )}
                    {section === 'recados' && (
                        <GuestbookSection
                            event={event}
                            messages={messages}
                            defaultName={guest?.name ?? ''}
                        />
                    )}
                </div>

                <Footer event={event} />
            </div>

            {(event.accepts_online_gifts || event.accepts_in_person_gifts) &&
                (cartCount > 0 || orderResult || freeAmountOpen) && (
                    <Sheet
                        open={cartOpen}
                        onOpenChange={(open) => {
                            setCartOpen(open);

                            if (!open) {
                                setOrderResult(null);
                                setConfirmedLines([]);
                            }
                        }}
                    >
                        {cartCount > 0 && (
                            <SheetTrigger asChild>
                                <Button
                                    size="lg"
                                    className="fixed right-6 bottom-6 z-50 shadow-lg"
                                    style={accentButtonStyle(event)}
                                >
                                    <ShoppingCart />
                                    {cartCount}{' '}
                                    {cartCount === 1 ? 'presente' : 'presentes'}
                                </Button>
                            </SheetTrigger>
                        )}
                        <SheetContent
                            className="event-page flex w-full flex-col gap-0 overflow-y-auto sm:max-w-lg"
                            style={{
                                ...grainBackgroundStyle(event.primary_color),
                                ...mutedTextCssVars(event),
                            }}
                        >
                            <SheetHeader>
                                <SheetTitle style={headingStyle(event)}>
                                    {freeAmountOpen
                                        ? 'Sua contribuição'
                                        : 'Seus presentes'}
                                </SheetTitle>
                            </SheetHeader>

                            <div className="flex flex-1 flex-col gap-4 px-4">
                                {freeAmountOpen && !orderResult && (
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="free_amount"
                                            style={bodyTextStyle(event)}
                                        >
                                            Quanto você quer dar?
                                        </Label>
                                        <Input
                                            id="free_amount"
                                            type="number"
                                            min={1}
                                            step="0.01"
                                            inputMode="decimal"
                                            placeholder="R$ 0,00"
                                            value={freeAmount}
                                            onChange={(e) =>
                                                setFreeAmount(e.target.value)
                                            }
                                        />
                                        {(
                                            form.errors as Record<
                                                string,
                                                string | undefined
                                            >
                                        ).free_amount && (
                                            <p className="text-sm text-red-600">
                                                {
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string | undefined
                                                        >
                                                    ).free_amount
                                                }
                                            </p>
                                        )}
                                    </div>
                                )}
                                {(freeAmountOpen
                                    ? []
                                    : orderResult
                                      ? confirmedLines
                                      : cartLines
                                ).map(({ product, quantity }) => (
                                    <div
                                        key={product.id}
                                        className="flex items-center gap-3"
                                    >
                                        <div className="flex-1">
                                            <p
                                                className="text-sm font-medium"
                                                style={headingStyle(event)}
                                            >
                                                {product.name}
                                            </p>
                                            <p
                                                className="text-sm"
                                                style={bodyTextStyle(event)}
                                            >
                                                {formatCurrency(product.price)}
                                            </p>
                                        </div>
                                        {orderResult ? (
                                            <span
                                                className="text-sm"
                                                style={bodyTextStyle(event)}
                                            >
                                                {quantity}x
                                            </span>
                                        ) : (
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    onClick={() =>
                                                        changeQuantity(
                                                            product.id,
                                                            -1,
                                                        )
                                                    }
                                                >
                                                    <Minus />
                                                </Button>
                                                <span className="w-4 text-center">
                                                    {quantity}
                                                </span>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    disabled={
                                                        quantity >=
                                                        product.quantity_available
                                                    }
                                                    onClick={() =>
                                                        changeQuantity(
                                                            product.id,
                                                            1,
                                                        )
                                                    }
                                                >
                                                    <Plus />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        removeFromCart(
                                                            product.id,
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}

                                <Separator />

                                <div
                                    className="flex items-center justify-between font-medium"
                                    style={headingStyle(event)}
                                >
                                    <span>Total</span>
                                    <span>
                                        {formatCurrency(
                                            orderResult
                                                ? orderResult.total_amount
                                                : freeAmountOpen
                                                  ? Number(freeAmount) || 0
                                                  : cartTotal,
                                        )}
                                    </span>
                                </div>

                                <Separator />

                                {orderResult?.fulfillment === 'in_person' ? (
                                    <div
                                        className="space-y-2 text-center"
                                        style={bodyTextStyle(event)}
                                    >
                                        <Gift
                                            className="mx-auto size-8"
                                            style={{
                                                color:
                                                    event.secondary_color ??
                                                    undefined,
                                            }}
                                        />
                                        <p
                                            className="font-medium"
                                            style={headingStyle(event)}
                                        >
                                            Obrigado! Os presentes ficaram
                                            reservados para você.
                                        </p>
                                        <p className="text-sm">
                                            {orderResult.is_anonymous
                                                ? 'Como você preferiu não se identificar, a entrega fica por sua conta: leve no dia do evento ou combine com os anfitriões.'
                                                : 'Combine a entrega com os anfitriões. Se mudar de ideia, você pode desistir na lista de presentes.'}
                                        </p>
                                    </div>
                                ) : orderResult ? (
                                    <MercadoPagoCheckout
                                        event={event}
                                        order={orderResult}
                                        guestEmail={form.data.guest.email}
                                    />
                                ) : (
                                    <div className="space-y-3">
                                        <label
                                            className="flex cursor-pointer items-start gap-2 text-sm"
                                            style={bodyTextStyle(event)}
                                        >
                                            <input
                                                type="checkbox"
                                                className="mt-0.5 size-4 accent-current"
                                                checked={form.data.anonymous}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'anonymous',
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            <span>
                                                Presentear anonimamente
                                                <span className="block text-xs opacity-80">
                                                    Os anfitriões veem o
                                                    presente e a sua mensagem,
                                                    mas não quem enviou. Seus
                                                    dados ficam opcionais e, se
                                                    preenchidos, só a equipe do
                                                    Wishlisti tem acesso. Se
                                                    quiser se identificar para
                                                    os anfitriões, assine a
                                                    mensagem.
                                                </span>
                                            </span>
                                        </label>

                                        <ContactFields
                                            idPrefix="guest"
                                            errorPrefix="guest"
                                            value={form.data.guest}
                                            fields={guestContactFields(
                                                event.rsvp_required_fields,
                                            )}
                                            requiredFields={
                                                form.data.anonymous
                                                    ? []
                                                    : event.rsvp_required_fields
                                            }
                                            nameLabel={
                                                form.data.anonymous
                                                    ? 'Seu nome (opcional)'
                                                    : 'Seu nome'
                                            }
                                            errors={
                                                form.errors as Record<
                                                    string,
                                                    string | undefined
                                                >
                                            }
                                            onChange={(value) =>
                                                form.setData('guest', value)
                                            }
                                        />

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="message"
                                                style={bodyTextStyle(event)}
                                            >
                                                Mensagem (opcional)
                                            </Label>
                                            <Textarea
                                                id="message"
                                                value={form.data.message}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'message',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Deixe uma mensagem para os anfitriões"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>

                            {!orderResult && (
                                <SheetFooter className="gap-2">
                                    {form.errors.items && (
                                        <p className="text-sm text-red-600">
                                            {form.errors.items}
                                        </p>
                                    )}
                                    {event.accepts_online_gifts && (
                                        <Button
                                            onClick={() =>
                                                submitOrder('online')
                                            }
                                            disabled={submitting}
                                            className="w-full"
                                            style={accentButtonStyle(event)}
                                        >
                                            Pagar agora (Pix, cartão ou boleto)
                                        </Button>
                                    )}
                                    {event.accepts_in_person_gifts &&
                                        !freeAmountOpen && (
                                            <Button
                                                onClick={() =>
                                                    submitOrder('in_person')
                                                }
                                                disabled={submitting}
                                                variant={
                                                    event.accepts_online_gifts
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                                className="w-full"
                                                style={
                                                    event.accepts_online_gifts
                                                        ? undefined
                                                        : accentButtonStyle(
                                                              event,
                                                          )
                                                }
                                            >
                                                {event.accepts_online_gifts
                                                    ? 'Vou entregar pessoalmente'
                                                    : 'Reservar presentes'}
                                            </Button>
                                        )}
                                </SheetFooter>
                            )}
                        </SheetContent>
                    </Sheet>
                )}
        </>
    );
}

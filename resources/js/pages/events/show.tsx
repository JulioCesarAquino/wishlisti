import { Head, useForm } from '@inertiajs/react';
import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { store as storeOrder } from '@/routes/orders';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
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
import { Footer } from '@/pages/events/sections/footer';
import { HomeSection } from '@/pages/events/sections/home-section';
import { GallerySection } from '@/pages/events/sections/gallery-section';
import { GiftsSection } from '@/pages/events/sections/gifts-section';
import { MercadoPagoCheckout } from '@/pages/events/sections/mercadopago-checkout';
import { RsvpSection } from '@/pages/events/sections/rsvp-section';
import {
    accentButtonStyle,
    bodyTextStyle,
    formatCurrency,
    FONT_FAMILIES,
    grainBackgroundStyle,
    mutedTextCssVars,
    headingStyle,
    type EventData,
    type Guest,
    type Product,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    products: Product[];
    guest: Guest | null;
    is_preview: boolean;
};

type Section = 'inicio' | 'galeria' | 'presentes' | 'confirmar-presenca';

const NAV_ITEMS: { key: Section; label: string }[] = [
    { key: 'inicio', label: 'Início' },
    { key: 'galeria', label: 'Galeria' },
    { key: 'presentes', label: 'Presentes' },
    { key: 'confirmar-presenca', label: 'Confirmar presença' },
];

function cartStorageKey(slug: string): string {
    return `wishlist_cart_${slug}`;
}

function sectionFromHash(): Section {
    const hash = window.location.hash.replace('#', '');
    const match = NAV_ITEMS.find((item) => item.key === hash);

    return match?.key ?? 'inicio';
}

export default function EventShow({
    event,
    products,
    guest,
    is_preview,
}: Props) {
    const [section, setSection] = useState<Section>('inicio');
    const [cart, setCart] = useState<Record<number, number>>({});
    const [cartOpen, setCartOpen] = useState(false);

    const font = FONT_FAMILIES[event.font_family ?? 'default'];

    useEffect(() => {
        setSection(sectionFromHash());

        const onHashChange = () => setSection(sectionFromHash());
        window.addEventListener('hashchange', onHashChange);

        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    useEffect(() => {
        try {
            const raw = window.localStorage.getItem(cartStorageKey(event.slug));

            if (raw) {
                setCart(JSON.parse(raw));
            }
        } catch {
            // ignore malformed/blocked storage
        }
    }, [event.slug]);

    const navigate = (key: Section) => {
        window.location.hash = key;
        setSection(key);
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
        guest: {
            name: guest?.name ?? '',
            whatsapp: guest?.whatsapp ?? '',
            email: guest?.email ?? '',
        },
        message: '',
        anonymous: false,
        items: [] as { event_product_id: number; quantity: number }[],
    });

    const [submitting, setSubmitting] = useState(false);
    const [orderResult, setOrderResult] = useState<{
        id: number;
        total_amount: number;
    } | null>(null);
    const [confirmedLines, setConfirmedLines] = useState<
        { product: Product; quantity: number }[]
    >([]);

    const submitOrder = async () => {
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
                        items: cartLines.map((line) => ({
                            event_product_id: line.product.id,
                            quantity: line.quantity,
                        })),
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
            setConfirmedLines(cartLines);
            setOrderResult(body.order);
            persistCart({});
        } catch {
            toast.error(
                'Não foi possível registrar o presente. Tente novamente.',
            );
        } finally {
            setSubmitting(false);
        }
    };

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
                        {NAV_ITEMS.map((item) => (
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
                    {section === 'inicio' && <HomeSection event={event} />}
                    {section === 'galeria' && (
                        <GallerySection
                            event={event}
                            galleryUrls={event.gallery_urls}
                        />
                    )}
                    {section === 'presentes' && (
                        <GiftsSection
                            event={event}
                            products={products}
                            cart={cart}
                            onAddToCart={addToCart}
                        />
                    )}
                    {section === 'confirmar-presenca' && (
                        <RsvpSection event={event} guest={guest} />
                    )}
                </div>

                <Footer event={event} />
            </div>

            {event.accepts_online_gifts && (cartCount > 0 || orderResult) && (
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
                    <SheetContent
                        className="event-page flex w-full flex-col gap-0 overflow-y-auto sm:max-w-lg"
                        style={{
                            ...grainBackgroundStyle(event.primary_color),
                            ...mutedTextCssVars(event),
                        }}
                    >
                        <SheetHeader>
                            <SheetTitle style={headingStyle(event)}>
                                Seus presentes
                            </SheetTitle>
                        </SheetHeader>

                        <div className="flex flex-1 flex-col gap-4 px-4">
                            {(orderResult ? confirmedLines : cartLines).map(
                                ({ product, quantity }) => (
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
                                ),
                            )}

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
                                            : cartTotal,
                                    )}
                                </span>
                            </div>

                            <Separator />

                            {orderResult ? (
                                <MercadoPagoCheckout
                                    event={event}
                                    order={orderResult}
                                    guestEmail={form.data.guest.email}
                                />
                            ) : (
                                <div className="space-y-3">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="guest_name"
                                            style={bodyTextStyle(event)}
                                        >
                                            Seu nome
                                        </Label>
                                        <Input
                                            id="guest_name"
                                            value={form.data.guest.name}
                                            onChange={(e) =>
                                                form.setData('guest', {
                                                    ...form.data.guest,
                                                    name: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['guest.name'] && (
                                            <p className="text-sm text-red-600">
                                                {form.errors['guest.name']}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="guest_whatsapp"
                                            style={bodyTextStyle(event)}
                                        >
                                            WhatsApp
                                        </Label>
                                        <Input
                                            id="guest_whatsapp"
                                            value={form.data.guest.whatsapp}
                                            onChange={(e) =>
                                                form.setData('guest', {
                                                    ...form.data.guest,
                                                    whatsapp: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['guest.whatsapp'] && (
                                            <p className="text-sm text-red-600">
                                                {form.errors['guest.whatsapp']}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="guest_email"
                                            style={bodyTextStyle(event)}
                                        >
                                            E-mail (opcional)
                                        </Label>
                                        <Input
                                            id="guest_email"
                                            type="email"
                                            value={form.data.guest.email}
                                            onChange={(e) =>
                                                form.setData('guest', {
                                                    ...form.data.guest,
                                                    email: e.target.value,
                                                })
                                            }
                                        />
                                    </div>

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
                                                Os anfitriões veem o presente e
                                                a sua mensagem, mas não o seu
                                                nome. Se quiser se identificar,
                                                assine a mensagem.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            )}
                        </div>

                        {!orderResult && (
                            <SheetFooter>
                                <Button
                                    onClick={submitOrder}
                                    disabled={submitting}
                                    className="w-full"
                                    style={accentButtonStyle(event)}
                                >
                                    Continuar para pagamento
                                </Button>
                            </SheetFooter>
                        )}
                    </SheetContent>
                </Sheet>
            )}
        </>
    );
}

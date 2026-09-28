import { router } from '@inertiajs/react';
import { Gift, LayoutGrid, List as ListIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { FreeAmountCard } from '@/pages/events/sections/free-amount-card';
import {
    accentButtonStyle,
    bodyTextStyle,
    DEFAULT_SECONDARY_COLOR,
    formatCurrency,
    headingStyle,
    type EventData,
    type Reservation,
    type Product,
} from '@/pages/events/types';

const DESCRIPTION_TRUNCATE_LENGTH = 90;

type SortOrder = 'default' | 'price_asc' | 'price_desc';
type ViewMode = 'grid' | 'list';

type Props = {
    event: EventData;
    products: Product[];
    cart: Record<number, number>;
    onAddToCart: (product: Product) => void;
    reservations: Reservation[];
    /** "Se quiser presentear": no sold-out items, counters or prices in focus. */
    discreet?: boolean;
    onContribute: () => void;
};

export function GiftsSection({
    event,
    products,
    cart,
    onAddToCart,
    reservations,
    discreet = false,
    onContribute,
}: Props) {
    const [sortOrder, setSortOrder] = useState<SortOrder>('default');
    const [viewMode, setViewMode] = useState<ViewMode>('grid');

    const sortedProducts = useMemo(() => {
        const visible = discreet
            ? products.filter((product) => !product.is_sold_out)
            : products;

        if (sortOrder === 'default') {
            return visible;
        }

        return [...visible].sort((a, b) =>
            sortOrder === 'price_asc' ? a.price - b.price : b.price - a.price,
        );
    }, [products, sortOrder, discreet]);

    const freeAmountCard = event.accepts_free_amount && (
        <FreeAmountCard event={event} onContribute={onContribute} />
    );

    if (sortedProducts.length === 0 && freeAmountCard) {
        return (
            <div className="mx-auto max-w-4xl px-6 py-8">{freeAmountCard}</div>
        );
    }

    if (sortedProducts.length === 0) {
        return (
            <div
                className="mx-auto max-w-3xl px-6 py-16 text-center"
                style={bodyTextStyle(event)}
            >
                <Gift className="mx-auto mb-3 size-10 opacity-40" />
                Ainda não há presentes cadastrados.
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-4xl px-6 py-8">
            <h2
                className="mb-4 text-center text-2xl font-semibold"
                style={headingStyle(event)}
            >
                {discreet ? 'Se quiser presentear' : 'Lista de presentes'}
            </h2>

            {freeAmountCard}

            {!event.accepts_online_gifts && event.accepts_in_person_gifts && (
                <p
                    className="mb-6 text-center text-sm"
                    style={bodyTextStyle(event)}
                >
                    Escolha o que você quer dar: o presente fica reservado para
                    você, ninguém mais o escolhe, e você combina a entrega com
                    os anfitriões.
                </p>
            )}

            {reservations.length > 0 && (
                <ReservationsList event={event} reservations={reservations} />
            )}

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                {discreet ? (
                    <span />
                ) : (
                    <Select
                        value={sortOrder}
                        onValueChange={(value) =>
                            setSortOrder(value as SortOrder)
                        }
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="default">
                                Ordem padrão
                            </SelectItem>
                            <SelectItem value="price_asc">
                                Menor preço
                            </SelectItem>
                            <SelectItem value="price_desc">
                                Maior preço
                            </SelectItem>
                        </SelectContent>
                    </Select>
                )}

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={viewMode}
                    onValueChange={(value) =>
                        value && setViewMode(value as ViewMode)
                    }
                >
                    <ToggleGroupItem value="grid" aria-label="Ver em grade">
                        <LayoutGrid className="size-4" />
                    </ToggleGroupItem>
                    <ToggleGroupItem value="list" aria-label="Ver em lista">
                        <ListIcon className="size-4" />
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <div
                className={cn(
                    viewMode === 'grid'
                        ? 'grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3'
                        : 'flex flex-col gap-3',
                )}
            >
                {sortedProducts.map((product) => (
                    <GiftCard
                        key={product.id}
                        event={event}
                        product={product}
                        inCart={cart[product.id] ?? 0}
                        layout={viewMode}
                        onAddToCart={onAddToCart}
                        discreet={discreet}
                    />
                ))}
            </div>
        </div>
    );
}

function GiftCard({
    event,
    product,
    inCart,
    layout,
    onAddToCart,
    discreet,
}: {
    event: EventData;
    product: Product;
    inCart: number;
    layout: ViewMode;
    onAddToCart: (product: Product) => void;
    discreet: boolean;
}) {
    const isLongDescription =
        (product.description?.length ?? 0) > DESCRIPTION_TRUNCATE_LENGTH;

    const image = (
        <div
            className={cn(
                'shrink-0 overflow-hidden bg-black/5',
                layout === 'grid'
                    ? 'aspect-square w-full'
                    : 'size-24 sm:size-32',
            )}
        >
            {product.image_url ? (
                <img
                    src={product.image_url}
                    alt={product.name}
                    className="h-full w-full object-cover"
                />
            ) : (
                <div className="flex h-full w-full items-center justify-center">
                    <Gift className="size-8 opacity-30" />
                </div>
            )}
        </div>
    );

    const description = product.description && (
        <>
            <p
                className={cn(
                    'text-xs text-neutral-500 sm:text-sm',
                    layout === 'grid' ? 'line-clamp-3' : 'line-clamp-2',
                )}
            >
                {product.description}
            </p>
            {isLongDescription && (
                <Dialog>
                    <DialogTrigger asChild>
                        <button
                            type="button"
                            className="text-left text-xs font-medium underline underline-offset-2 sm:text-sm"
                            style={{
                                color:
                                    event.secondary_color ??
                                    DEFAULT_SECONDARY_COLOR,
                            }}
                        >
                            Ver mais
                        </button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{product.name}</DialogTitle>
                        </DialogHeader>
                        {product.image_url && (
                            <div className="aspect-video w-full overflow-hidden rounded-md bg-black/5">
                                <img
                                    src={product.image_url}
                                    alt={product.name}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                        )}
                        <p className="text-sm whitespace-pre-line text-neutral-600">
                            {product.description}
                        </p>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );

    // Discreet mode keeps the price, just out of focus.
    const price = discreet ? (
        <span className="text-xs font-normal opacity-60">
            {formatCurrency(product.price)}
        </span>
    ) : (
        formatCurrency(product.price)
    );

    const availabilityBadge = discreet ? null : product.is_sold_out ? (
        <Badge variant="destructive">
            {event.accepts_online_gifts ? 'Esgotado' : 'Já escolhido'}
        </Badge>
    ) : product.quantity_available > 1 ? (
        <Badge variant="outline" className="text-xs">
            {product.quantity_available} disp.
        </Badge>
    ) : null;

    const canGive = event.accepts_online_gifts || event.accepts_in_person_gifts;

    const button = !canGive ? null : (
        <Button
            size="sm"
            className={layout === 'grid' ? 'w-full' : ''}
            style={accentButtonStyle(event)}
            disabled={
                product.is_sold_out || inCart >= product.quantity_available
            }
            onClick={() => onAddToCart(product)}
        >
            {inCart > 0
                ? `Adicionado (${inCart})`
                : event.accepts_online_gifts
                  ? 'Presentear'
                  : 'Vou presentear'}
        </Button>
    );

    if (layout === 'list') {
        return (
            <Card className="flex flex-row items-stretch gap-3 overflow-hidden p-3">
                {image}
                <div className="flex min-w-0 flex-1 flex-col justify-between gap-2">
                    <div>
                        <CardTitle className="text-sm sm:text-base">
                            {product.name}
                        </CardTitle>
                        {description}
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium sm:text-base">
                                {price}
                            </span>
                            {availabilityBadge}
                        </div>
                        {button}
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card className="flex h-full flex-col overflow-hidden">
            {image}
            <CardHeader className="flex-1 px-3 sm:px-6">
                <CardTitle className="text-sm sm:text-base">
                    {product.name}
                </CardTitle>
                {description}
            </CardHeader>
            <CardContent className="flex items-center justify-between px-3 sm:px-6">
                <span className="text-sm font-medium sm:text-base">
                    {price}
                </span>
                {availabilityBadge}
            </CardContent>
            {button && (
                <CardFooter className="px-3 sm:px-6">{button}</CardFooter>
            )}
        </Card>
    );
}

function ReservationsList({
    event,
    reservations,
}: {
    event: EventData;
    reservations: Reservation[];
}) {
    const giveUp = (reservation: Reservation) => {
        if (
            !window.confirm('Desistir deste presente? Ele volta para a lista.')
        ) {
            return;
        }

        router.post(
            `/${event.slug}/orders/${reservation.id}/cancel`,
            {},
            { preserveScroll: true, only: ['products', 'guest', 'flash'] },
        );
    };

    return (
        <div
            className="mb-6 rounded-lg border p-4"
            style={bodyTextStyle(event)}
        >
            <p className="mb-2 font-medium" style={headingStyle(event)}>
                Seus presentes reservados
            </p>
            <ul className="space-y-2">
                {reservations.map((reservation) => (
                    <li
                        key={reservation.id}
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        <span>
                            {reservation.items.join(', ')}
                            {reservation.is_anonymous && ' (anônimo)'}
                        </span>
                        {reservation.status === 'reserved' ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => giveUp(reservation)}
                            >
                                Desistir
                            </Button>
                        ) : (
                            <Badge variant="outline">Entregue</Badge>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

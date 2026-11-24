import { Link } from '@inertiajs/react';
import { CheckCircle2, Copy, Lock, Sparkles } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    bodyTextStyle,
    formatCurrency,
    giftButtonStyle,
    headingStyle,
    type EventData,
} from '@/pages/events/types';

export type DemoData = {
    moment: 'before' | 'after';
    before_url: string;
    after_url: string;
};

/**
 * The bar on top of a demonstration event: it's an example, how the page
 * looks before the day and after it (the keepsake), and the way to make
 * one's own.
 */
export function DemoBar({ demo }: { demo: DemoData }) {
    const moments = [
        { key: 'before', label: 'Antes do evento', href: demo.before_url },
        { key: 'after', label: 'Depois do evento', href: demo.after_url },
    ] as const;

    return (
        <div className="relative z-50 border-b border-white/10 bg-neutral-950 px-3 py-2 text-white">
            <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-center gap-x-4 gap-y-2 sm:justify-between">
                <p className="flex items-center gap-1.5 text-xs sm:text-sm">
                    <Sparkles className="size-4 shrink-0 text-amber-400" />
                    <span>
                        <span className="font-semibold">Exemplo.</span> Explore
                        à vontade: nada do que você fizer aqui é salvo.
                    </span>
                </p>
                <div className="flex items-center gap-2">
                    <div
                        className="flex rounded-full bg-white/10 p-0.5 text-xs"
                        role="tablist"
                        aria-label="Momento do evento"
                    >
                        {moments.map((moment) => (
                            <Link
                                key={moment.key}
                                href={moment.href}
                                role="tab"
                                aria-selected={demo.moment === moment.key}
                                preserveScroll
                                className={cn(
                                    'rounded-full px-3 py-1 transition-colors',
                                    demo.moment === moment.key
                                        ? 'bg-white font-medium text-neutral-950'
                                        : 'text-white/80 hover:text-white',
                                )}
                            >
                                {moment.label}
                            </Link>
                        ))}
                    </div>
                    <a
                        href="/cadastro"
                        className="rounded-full bg-amber-400 px-3 py-1 text-xs font-semibold text-neutral-950 hover:bg-amber-300"
                    >
                        Criar a minha
                    </a>
                </div>
            </div>
        </div>
    );
}

/**
 * A made-up Pix code that looks the part, from the order's total — the
 * same one each time the checkout opens.
 */
function fakePixCode(total: number): string {
    const amount = total.toFixed(2);

    return `00020126580014br.gov.bcb.pix0136demo-wishlisti-0000-0000-000000000000520400005303986540${amount.length}${amount}5802BR5913WISHLISTI DEMO6009SAO PAULO62070503***6304DEMO`;
}

/** A QR-looking grid, drawn from the code, so it isn't a real payment. */
function FakeQrCode({ seed }: { seed: string }) {
    const size = 25;
    const cells = useMemo(() => {
        let hash = 0;

        for (const char of seed) {
            hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
        }

        const isFinder = (x: number, y: number) =>
            [
                [0, 0],
                [size - 7, 0],
                [0, size - 7],
            ].some(
                ([fx, fy]) => x >= fx && x < fx + 7 && y >= fy && y < fy + 7,
            );

        const finder = (x: number, y: number) => {
            const [fx, fy] =
                x < 7 && y < 7
                    ? [0, 0]
                    : x >= size - 7
                      ? [size - 7, 0]
                      : [0, size - 7];
            const dx = x - fx;
            const dy = y - fy;

            return (
                dx === 0 ||
                dy === 0 ||
                dx === 6 ||
                dy === 6 ||
                (dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4)
            );
        };

        const filled: [number, number][] = [];

        for (let y = 0; y < size; y++) {
            for (let x = 0; x < size; x++) {
                hash = (hash * 1103515245 + 12345) >>> 0;

                if (isFinder(x, y) ? finder(x, y) : (hash >>> 16) % 2 === 0) {
                    filled.push([x, y]);
                }
            }
        }

        return filled;
    }, [seed]);

    return (
        <svg
            viewBox={`-2 -2 ${size + 4} ${size + 4}`}
            className="mx-auto size-44 rounded-md bg-white"
            role="img"
            aria-label="QR Code de exemplo"
            shapeRendering="crispEdges"
        >
            {cells.map(([x, y]) => (
                <rect key={`${x}-${y}`} x={x} y={y} width={1} height={1} />
            ))}
        </svg>
    );
}

type CheckoutProps = {
    event: EventData;
    order: { total_amount: number };
    onPaid?: () => void;
};

/**
 * The demo's checkout: a Pix that looks real and a button that pretends
 * it was paid. Nothing reaches Mercado Pago.
 */
export function DemoCheckout({ event, order, onPaid }: CheckoutProps) {
    const [paid, setPaid] = useState(false);
    const code = fakePixCode(order.total_amount);

    if (paid) {
        return (
            <div className="space-y-2 text-center" style={bodyTextStyle(event)}>
                <CheckCircle2
                    className="mx-auto size-10"
                    style={{ color: event.secondary_color ?? undefined }}
                />
                <p className="font-medium" style={headingStyle(event)}>
                    Pagamento aprovado!
                </p>
                <p className="text-sm">
                    No seu evento, o valor cai na hora na sua conta Mercado Pago
                    e você vê quem deu cada presente, com a mensagem que deixou.
                </p>
                <a
                    href="/cadastro"
                    className="inline-block pt-2 text-sm font-medium underline"
                >
                    Criar a página do meu evento
                </a>
            </div>
        );
    }

    return (
        <div className="space-y-4 text-center" style={bodyTextStyle(event)}>
            <p className="text-sm">
                Pague{' '}
                <span className="font-semibold">
                    {formatCurrency(order.total_amount)}
                </span>{' '}
                com Pix escaneando o código ou copiando a chave. Cartão e boleto
                também são aceitos.
            </p>
            <FakeQrCode seed={code} />
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                    navigator.clipboard?.writeText(code).catch(() => {});
                    toast.success('Código Pix copiado (é só um exemplo).');
                }}
            >
                <Copy /> Copiar código Pix
            </Button>
            <Button
                type="button"
                className="w-full"
                style={giftButtonStyle(event)}
                onClick={() => {
                    setPaid(true);
                    onPaid?.();
                }}
            >
                Simular pagamento aprovado
            </Button>
            <p className="flex items-start justify-center gap-1.5 text-xs opacity-80">
                <Lock className="mt-0.5 size-3 shrink-0" />
                Demonstração: este Pix não é real e nada é cobrado.
            </p>
        </div>
    );
}

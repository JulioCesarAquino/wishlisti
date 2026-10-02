import { Lock } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { getCsrfToken } from '@/lib/csrf';
import {
    loadMercadoPago,
    type MercadoPagoBrickController,
} from '@/lib/mercadopago';
import { bodyTextStyle, type EventData } from '@/pages/events/types';

type Props = {
    event: EventData;
    order: { id: number; total_amount: number };
    guestEmail: string;
};

type PaymentSubmitPayload = {
    formData: Record<string, unknown>;
};

export function MercadoPagoCheckout({ event, order, guestEmail }: Props) {
    const [paymentId, setPaymentId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const controllerRef = useRef<MercadoPagoBrickController | null>(null);

    const paymentContainerId = `payment-brick-${useId().replace(/[^a-zA-Z0-9]/g, '')}`;
    const statusContainerId = `status-brick-${useId().replace(/[^a-zA-Z0-9]/g, '')}`;

    useEffect(() => {
        if (!event.mp_public_key || paymentId) {
            return;
        }

        let cancelled = false;

        loadMercadoPago(event.mp_public_key)
            .then(async (mp) => {
                if (cancelled) {
                    return;
                }

                const controller = await mp
                    .bricks()
                    .create('payment', paymentContainerId, {
                        initialization: {
                            amount: order.total_amount,
                            payer: guestEmail
                                ? { email: guestEmail }
                                : undefined,
                        },
                        customization: {
                            paymentMethods: {
                                creditCard: 'all',
                                bankTransfer: 'all', // Pix
                                ticket: 'all',
                            },
                        },
                        callbacks: {
                            onReady: () => {},
                            onSubmit: ({ formData }: PaymentSubmitPayload) =>
                                new Promise<void>((resolve, reject) => {
                                    fetch(
                                        `/${event.slug}/orders/${order.id}/mercadopago-payment`,
                                        {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type':
                                                    'application/json',
                                                Accept: 'application/json',
                                                'X-XSRF-TOKEN': getCsrfToken(),
                                            },
                                            body: JSON.stringify({ formData }),
                                        },
                                    )
                                        .then(async (response) => {
                                            if (!response.ok) {
                                                // The server's own reason when
                                                // it gives one ("Este presente
                                                // já foi pago", say).
                                                const body = await response
                                                    .json()
                                                    .catch(() => null);

                                                throw new Error(
                                                    body?.errors
                                                        ?.formData?.[0] ??
                                                        'Não foi possível processar o pagamento.',
                                                );
                                            }

                                            return response.json() as Promise<{
                                                id: string;
                                            }>;
                                        })
                                        .then((payment) => {
                                            setPaymentId(payment.id);
                                            resolve();
                                        })
                                        .catch((submitError: Error) => {
                                            setError(submitError.message);
                                            reject();
                                        });
                                }),
                            onError: () => {
                                setError(
                                    'Não foi possível carregar o formulário de pagamento.',
                                );
                            },
                        },
                    });

                controllerRef.current = controller;
            })
            .catch(() =>
                setError(
                    'Não foi possível carregar o formulário de pagamento.',
                ),
            );

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [event.mp_public_key, paymentId]);

    useEffect(() => {
        if (!paymentId || !event.mp_public_key) {
            return;
        }

        controllerRef.current?.unmount();
        controllerRef.current = null;

        let cancelled = false;

        loadMercadoPago(event.mp_public_key)
            .then(async (mp) => {
                if (cancelled) {
                    return;
                }

                const controller = await mp
                    .bricks()
                    .create('statusScreen', statusContainerId, {
                        initialization: { paymentId },
                        callbacks: {
                            onReady: () => {},
                            onError: () => {},
                        },
                    });

                controllerRef.current = controller;
            })
            .catch(() =>
                setError('Não foi possível carregar o status do pagamento.'),
            );

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [paymentId, event.mp_public_key]);

    useEffect(() => {
        return () => {
            controllerRef.current?.unmount();
            controllerRef.current = null;
        };
    }, []);

    if (!event.mp_public_key) {
        return (
            <p className="text-sm text-neutral-500">
                Presente registrado! Em breve você poderá concluir o pagamento.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {error && <p className="text-sm text-red-600">{error}</p>}
            {!paymentId && (
                // True whatever the payment method: card details go from the
                // browser straight to Mercado Pago (see the payment service).
                <p
                    className="flex items-start gap-1.5 text-xs opacity-80"
                    style={bodyTextStyle(event)}
                >
                    <Lock className="mt-0.5 size-3 shrink-0" />
                    Pagamento processado pelo Mercado Pago. O Wishlisti não vê
                    nem guarda os dados do seu cartão.
                </p>
            )}
            {!paymentId && <div id={paymentContainerId} />}
            {paymentId && <div id={statusContainerId} />}
        </div>
    );
}

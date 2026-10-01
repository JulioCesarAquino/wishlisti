import { useEffect, useId, useRef, useState } from 'react';
import { getCsrfToken } from '@/lib/csrf';
import {
    loadMercadoPago,
    type MercadoPagoBrickController,
} from '@/lib/mercadopago';
import type { EventData } from '@/pages/events/types';

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
                                debitCard: 'all',
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
                                                throw new Error(
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
            {!paymentId && <div id={paymentContainerId} />}
            {paymentId && <div id={statusContainerId} />}
        </div>
    );
}

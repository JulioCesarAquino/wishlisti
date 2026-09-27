{{--
    Mercado Pago Payment Brick for the premium plan, on the platform's own
    account. The charge itself is created server side ($wire.pay), with the
    amount from config — the amount given to the Brick is only for display.
--}}
<div wire:ignore>
    <div id="premium-payment-brick"></div>
    <div id="premium-status-brick"></div>
    <p id="premium-payment-error" class="fi-color-danger text-sm" style="display: none; color: rgb(220 38 38)"></p>
</div>

@assets
    <script src="https://sdk.mercadopago.com/js/v2"></script>
@endassets

@script
    <script>
        const mp = new MercadoPago(@js($publicKey), { locale: 'pt-BR' });
        const bricks = mp.bricks();
        const errorBox = document.getElementById('premium-payment-error');
        let paymentBrick = null;

        const showError = (message) => {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        };

        const showStatus = async (paymentId) => {
            paymentBrick?.unmount();
            paymentBrick = null;

            await bricks.create('statusScreen', 'premium-status-brick', {
                initialization: { paymentId },
                callbacks: { onReady: () => {}, onError: () => {} },
            });
        };

        // With an invalid key, or Mercado Pago unreachable, the SDK fails
        // without calling back — so don't leave the section silently empty.
        const loadTimeout = setTimeout(() => {
            if (! paymentBrick) {
                showError('Não foi possível carregar o formulário de pagamento. Recarregue a página ou tente mais tarde.');
            }
        }, 15000);

        bricks
            .create('payment', 'premium-payment-brick', {
                initialization: {
                    amount: @js($amount),
                    payer: { email: @js($payerEmail) },
                },
                customization: {
                    paymentMethods: {
                        creditCard: 'all',
                        debitCard: 'all',
                        pix: 'all',
                        ticket: 'all',
                    },
                },
                callbacks: {
                    onReady: () => clearTimeout(loadTimeout),
                    onError: () => showError('Não foi possível carregar o formulário de pagamento.'),
                    onSubmit: ({ formData }) =>
                        $wire.pay(formData).then(async (result) => {
                            if (result.error) {
                                showError(result.error);
                                throw new Error(result.error);
                            }

                            errorBox.style.display = 'none';
                            await showStatus(result.payment_id);
                        }),
                },
            })
            .then((controller) => {
                paymentBrick = controller;
            })
            .catch(() => showError('Não foi possível carregar o formulário de pagamento.'));
    </script>
@endscript

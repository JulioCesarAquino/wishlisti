{{--
    Mercado Pago's own status screen for the payment left open — for a Pix,
    the QR code, the code to copy and its validity — the same the guests
    see when giving a gift.
--}}
<div wire:ignore wire:key="premium-status-{{ $paymentId }}">
    <div id="premium-open-payment-{{ $paymentId }}"></div>
    <p id="premium-open-payment-error-{{ $paymentId }}" class="text-sm" style="display: none; color: rgb(220 38 38)">
        Não foi possível mostrar o pagamento agora. Use "Ver o código Pix" abaixo.
    </p>
</div>

@assets
    <script src="https://sdk.mercadopago.com/js/v2"></script>
@endassets

@script
    <script>
        const paymentId = @js($paymentId);
        const container = document.getElementById(`premium-open-payment-${paymentId}`);

        if (container && ! container.dataset.mounted) {
            container.dataset.mounted = '1';

            new MercadoPago(@js($publicKey), { locale: 'pt-BR' })
                .bricks()
                .create('statusScreen', container.id, {
                    initialization: { paymentId },
                    callbacks: { onReady: () => {}, onError: () => {} },
                })
                .catch(() => {
                    document.getElementById(`premium-open-payment-error-${paymentId}`).style.display = 'block';
                });
        }
    </script>
@endscript

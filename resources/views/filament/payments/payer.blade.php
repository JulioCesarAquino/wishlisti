@if ($payer === null)
    <p class="text-sm">
        Não foi possível consultar o Mercado Pago agora (a conta pode ter sido desconectada do evento). Tente de novo mais tarde.
    </p>
@else
    <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
        <dt class="font-medium">Nome</dt>
        <dd>{{ $payer['name'] ?? 'Não informado pelo Mercado Pago' }}</dd>
        <dt class="font-medium">E-mail</dt>
        <dd>{{ $payer['email'] ?? 'Não informado pelo Mercado Pago' }}</dd>
        <dt class="font-medium">Documento</dt>
        <dd>{{ $payer['document'] ?? 'Não informado pelo Mercado Pago' }}</dd>
        <dt class="font-medium">Meio de pagamento</dt>
        <dd>{{ $payer['method'] ?? '—' }}</dd>
    </dl>
    <p class="mt-4 text-xs opacity-70">
        Dados consultados agora no Mercado Pago, não guardados no Wishlisti. Esta consulta ficou registrada na Auditoria.
    </p>
@endif

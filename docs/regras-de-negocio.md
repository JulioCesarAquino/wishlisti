# Regras de negócio — Wishlisti

Referência interna para consultar **como o sistema se comporta** em cada situação. Cada regra tem um número (`RN-xx`) para ser citada em conversas, issues e commits. Onde ajuda, a regra traz o **porquê** e **onde** está no código.

Para instalação, stack e arquitetura, veja o [README](../README.md).

> Ao mudar uma regra no código, atualize a regra aqui. Não reaproveite números: regra removida fica marcada como _(removida)_.

## Sumário

- [Glossário](#glossário)
- [1. Contas e acesso](#1-contas-e-acesso) — RN-01 a RN-09
- [2. Evento](#2-evento) — RN-10 a RN-19
- [3. Convidados e identificação](#3-convidados-e-identificação) — RN-20 a RN-29
- [4. Confirmação de presença](#4-confirmação-de-presença) — RN-30 a RN-39
- [5. Lista de presentes](#5-lista-de-presentes) — RN-40 a RN-49
- [6. Formas de presentear](#6-formas-de-presentear) — RN-50 a RN-59
- [7. Presente anônimo](#7-presente-anônimo) — RN-60 a RN-64
- [8. Pagamento de presentes](#8-pagamento-de-presentes-mercado-pago-do-anfitrião) — RN-65 a RN-69
- [9. Premium](#9-premium) — RN-70 a RN-84
- [10. Mural de recados](#10-mural-de-recados) — RN-85 a RN-88
- [11. Agenda de contatos](#11-agenda-de-contatos) — RN-89 a RN-94
- [12. Exclusão, lixeira e histórico financeiro](#12-exclusão-lixeira-e-histórico-financeiro) — RN-95 a RN-109
- [13. Auditoria](#13-auditoria) — RN-110 a RN-114
- [14. Limites e proteções técnicas](#14-limites-e-proteções-técnicas) — RN-115 a RN-119
- [Lacunas conhecidas](#lacunas-conhecidas)

---

## Glossário

| Termo               | Significado                                                                                        |
| ------------------- | -------------------------------------------------------------------------------------------------- |
| **Admin**           | usuário com `is_admin`. Dono da plataforma: vê e faz tudo.                                         |
| **Anfitrião**       | usuário comum, dono dos próprios eventos.                                                          |
| **Convidado**       | quem acessa a página pública. Não tem conta. É um registro em `guests`, sempre de **um** evento.   |
| **Acompanhante**    | convidado listado por outro convidado na confirmação de presença (`companion_of_guest_id`).        |
| **Pedido**          | qualquer presente registrado (`orders`): pago online, reserva para entrega pessoal ou valor livre. |
| **Reserva**         | pedido de entrega pessoal, com status `reserved`.                                                  |
| **Recurso premium** | um item de `App\Enums\Premium\Feature`. Fica ativo por uma **concessão** (`feature_grants`).       |
| **Concessão**       | registro que ativa um recurso para um evento ou um anfitrião, com origem `admin` ou `purchase`.    |
| **Lixeira**         | exclusão reversível (`deleted_at`). O item some das telas e da página, mas pode ser restaurado.    |
| **Arquivar**        | tirar um evento de circulação sem excluí-lo (`archived_at`).                                       |

---

## 1. Contas e acesso

**RN-01. Ninguém se cadastra sozinho.** O visitante pede um convite na página inicial (nome, e-mail e WhatsApp). A conta só existe quando o admin **aprova** o pedido. — `InviteRequestStoreService`, `InviteRequestApproveService`

**RN-02. A aprovação envia por e-mail um link para o anfitrião definir a senha.** A conta é criada com senha aleatória e e-mail já verificado. O link vale por 2 horas (`AUTH_PASSWORD_RESET_EXPIRE`, em minutos); se expirar, o anfitrião pede outro em **"Esqueceu a senha?"**, porque a conta já existe desde a aprovação. O painel também mostra o link para copiar: se o e-mail não sair, a aprovação acontece mesmo assim, o erro vai para o log e o admin envia o link à mão (por WhatsApp, por exemplo). Se o link expirar ou se perder, o admin usa **"Gerar novo link"** no pedido aprovado, que manda outro e-mail e invalida o link anterior. — `InviteRequestsTable`, `InviteLinkSendService`

**RN-02a. "Esqueceu a senha?" no login do painel envia por e-mail um link para criar uma nova senha**, no mesmo visual do e-mail de convite e com a mesma validade. O e-mail sai na hora, sem fila (não há processo de fila em produção). E-mail não cadastrado não recebe nada. — `PasswordResetNotification`

**RN-03. Estados do pedido de convite:** `pendente` → `aprovado` ou `rejeitado`. Só um pedido pendente pode ser aprovado ou rejeitado, e só um aprovado ganha um novo link.

**RN-04. Todo usuário entra pelo painel em `/admin`.** A página pública não tem login.

**RN-05. O anfitrião só enxerga o que é dele:** os próprios eventos e, dentro deles, convidados, pedidos, presentes, recados e agenda. Ao tentar abrir algo de outro anfitrião, recebe "não encontrado". — `getEloquentQuery()` dos resources, policies

**RN-06. Só o admin:** catálogo geral de presentes, usuários, solicitações de convite, auditoria, vendas Premium, a aba "Liberar recursos" e a exclusão definitiva de evento.

**RN-07. `is_admin` não é alterável por formulário comum** (fica fora do mass assignment). Só o admin marca outro usuário como admin, no cadastro de usuários.

**RN-08. O admin não pode excluir a si mesmo.** — `UserPolicy::delete`

**RN-09. Senha forte só em produção:** mínimo de 12 caracteres, maiúsculas e minúsculas, números, símbolos e verificação contra senhas vazadas. Em ambiente local não há exigência. — `AppServiceProvider`

---

## 2. Evento

**RN-10. Para criar um evento bastam:** tipo, título e endereço. A data é opcional. O resto é preenchido depois, no submenu do evento.

**RN-11. Slug:** gerado a partir do título quando o anfitrião não informa um. É único no sistema **inclusive entre eventos na lixeira**. Se já existir, recebe o sufixo `-2`, `-3`, …

**RN-12. Endereço é obrigatório.** Latitude e longitude são opcionais, mas uma exige a outra. O botão "Usar minha localização atual" faz o seguinte:

- preenche as coordenadas pelo navegador;
- tenta descobrir o **endereço** pela busca reversa do OpenStreetMap (Nominatim, sem chave de API);
- se o campo de endereço já estiver preenchido, pergunta antes de substituir;
- sempre avisa que a localização pode não ser exata.

O mapa da página pública marca o local com um **pin**, pelas coordenadas ou, sem elas, pelo endereço.

**RN-13. Quem vê a página pública `/{slug}`:**

- evento **publicado** e **não arquivado**: qualquer pessoa;
- evento **não publicado** ou **arquivado**: só o dono e o admin, em "modo prévia";
- evento **na lixeira**: ninguém ("não encontrado").

**RN-14. Contagem de visitas:** conta uma visita a cada 12 horas por navegador, e só em evento publicado e não arquivado. Visitas do dono e do admin não contam.

**RN-15. Cada parte do evento é salva separadamente** (Detalhes, Localização, Página pública, Pagamentos…). Salvar uma parte não altera as outras.

**RN-16. Aparência:** a intensidade do desfoque da capa vai de 0 (foto normal) a 100 (efeito completo, o padrão).

---

## 3. Convidados e identificação

**RN-20. Um convidado pertence a um único evento.** A mesma pessoa em dois eventos são dois registros independentes. **Por quê:** o convidado não tem login, e compartilhar o cadastro deixaria qualquer pessoa alterar os dados que outro anfitrião vê. Também haveria o problema de LGPD, porque o convidado deu os dados para aquele evento. Para reaproveitar convidados entre os próprios eventos, o anfitrião usa a agenda (seção 11).

**RN-21. Só o nome é sempre obrigatório.** WhatsApp, e-mail e CPF dependem do que o evento exige (RN-31).

**RN-22. Como o sistema reconhece um convidado que volta**, na ordem:

1. pelo **cookie** do navegador, que dura 5 anos por evento;
2. por **CPF**, **telefone** (só os dígitos, ignorando a formatação) ou **e-mail** (sem diferenciar maiúsculas), dentro do mesmo evento e nessa ordem de prioridade.

Se reconhecer, atualiza o cadastro existente. Se não, cria um novo. — `GuestResolveService`, `ContactMatcher`

**RN-23. Ao atualizar um convidado reconhecido, campos em branco não apagam o que já se sabia.** Exemplo: um formulário que não pede CPF não apaga o CPF salvo.

**RN-24. O CPF é validado** pelos dígitos verificadores (sequências como `111.111.111-11` são recusadas) e guardado só com números.

**RN-25. Situação de presença:** `Sem resposta` (ainda não respondeu), `Confirmada` ou `Não vai`.

---

## 4. Confirmação de presença

**RN-30. Formulário gratuito:** nome, WhatsApp (obrigatório), e-mail (opcional), "vou" ou "não vou" e, se vai, quantas pessoas incluindo ele (1 a 20).

**RN-31. Com o Premium "Lista nominal de convidados":**

- o anfitrião escolhe, campo a campo, o que o formulário pede: telefone/WhatsApp, e-mail, CPF e **idade** (em anos), cada um como **"Não pedir"**, **"Opcional"** ou **"Obrigatório"**. O nome é sempre obrigatório. Pode ficar sem nenhum contato (só nome e idade, por exemplo); o painel avisa que aí não há como falar com os convidados e que a mesma pessoa pode confirmar duas vezes de outro aparelho. Um campo "Não pedir" é descartado mesmo que chegue preenchido;
- o anfitrião pode ligar **"Pedir os dados de cada acompanhante"**.

Sem o recurso, vale sempre o formulário gratuito, mesmo que as configurações estejam salvas. Os campos de contato também identificam quem dá um presente no carrinho (a idade, não). — `Event::rsvpFields()`, `rsvpRequiredFields()`, `collectsRsvpCompanions()`

**RN-31a. "Crianças com menos de X anos não pagam"** (qualquer plano). Com a idade de cada pessoa disponível (acompanhantes listados e idade pedida), a conta é pela idade. Senão, o formulário pergunta **"Quantas dessas pessoas têm menos de X anos?"** (só para grupos de 2 ou mais; obrigatório, de 0 até o total). O painel do evento mostra "N pessoas confirmadas · P pagantes · C crianças com menos de X anos", a coluna Idade com o selo "Não paga" e um filtro de crianças; o painel geral de convidados soma as crianças que não pagam. — `Event::childAgeLimit()`, `asksRsvpChildrenCount()`, `rsvpHeadcount()`

**RN-32. Acompanhantes:** com a opção ligada, quem vai com N pessoas preenche os dados das outras N−1, com os mesmos campos do titular. Cada acompanhante vira um convidado ligado a quem o listou.

**RN-33. A contagem de pessoas é calculada a partir de quem foi vinculado de fato.** O número digitado não vale se algum acompanhante já estiver contado em outro lugar (RN-35).

**RN-34. Individualização:** um acompanhante que confirma presença **por conta própria** passa a ser uma confirmação individual e **sai da contagem** de quem o listou.

**RN-35. Ninguém é contado duas vezes.** Se o convidado lista como acompanhante alguém que já confirmou sozinho, ou que outro convidado já listou, essa pessoa não é vinculada de novo e não entra na conta.

**RN-36. Refazer a resposta substitui a lista de acompanhantes.**

- Quem saiu da lista é excluído, **exceto** quem já deu presente: essa pessoa continua como convidado, sem confirmação.
- Responder "não vou" libera todos os acompanhantes.

**RN-37. Excluir ou restaurar um acompanhante ajusta a contagem** de quem o listou. Mandar o titular para a lixeira leva os acompanhantes junto, e restaurar traz de volta. — eventos do model `Guest`

**RN-38. "Confirmaram presença" no painel conta respostas, não pessoas.** Acompanhantes não entram. O total de pessoas é o indicador "Total de pessoas confirmadas".

---

## 5. Lista de presentes

**RN-40. Modos de exibição** (qualquer plano, botão "Exibição na página"):

| Modo                                  | Menu            | Página                                                                                       | Pedidos de itens                                    |
| ------------------------------------- | --------------- | -------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| **Lista de presentes** (padrão)       | tem "Presentes" | lista completa                                                                               | aceitos                                             |
| **Discreto** — "Se quiser presentear" | sem "Presentes" | link recolhido no fim da página inicial; esconde itens esgotados e contadores; preço apagado | aceitos                                             |
| **Sem presentes**                     | sem "Presentes" | só a mensagem do anfitrião (padrão: "Sua presença é o nosso presente.")                      | **recusados**, e os itens nem são enviados à página |

**RN-41. Plano gratuito, sem o Premium "Lista de presentes completa":**

- só **itens do catálogo** (nome, descrição e imagem vêm do catálogo e não podem ser alterados; o **preço é livre**);
- **uma unidade** por item, sem cotas;
- no máximo **`FREE_GIFT_LIMIT`** itens (padrão 15). Contam os itens que não estão na lixeira, ativos ou inativos.

As regras valem também no servidor: os campos travados são sempre gravados a partir do catálogo. — `ManageEventProducts`, `Event::giftLimit()`

**RN-42. Ao atingir o limite:** o botão "Adicionar presente" fica desabilitado e **restaurar da lixeira** também é bloqueado.

**RN-43. O que já existia antes dos limites é mantido.** Itens personalizados antigos continuam na página e editáveis. Cotas antigas mantêm a quantidade e só podem **diminuir**. Eventos acima do limite continuam com todos os itens.

**RN-44. Com o Premium:** itens personalizados, cotas (quantidade maior que 1) e sem limite.

**RN-45. Disponível = quantidade total − quantidade tomada.** A quantidade tomada (a coluna "Presenteado") soma os presentes **pagos** e as **reservas** ativas ou recebidas.

**RN-46. Item inativo** ("Ativo" desmarcado) sai da página pública, mas continua no histórico. É o caminho para tirar da página um item que já foi presenteado (RN-99).

**RN-47. Na página, o selo do item esgotado diz** "Esgotado" quando o evento recebe presentes online e "Já escolhido" quando é só reserva.

---

## 6. Formas de presentear

| Forma                         | Plano                               | Estoque                                     | Status possíveis                                      |
| ----------------------------- | ----------------------------------- | ------------------------------------------- | ----------------------------------------------------- |
| **Pagamento online** de itens | Premium (`payments`)                | tomado só quando o pagamento é **aprovado** | pendente → pago, recusado, cancelado ou não concluído |
| **Entrega pessoal** (reserva) | todos                               | tomado **na hora** da reserva               | reservado → recebido ou cancelado                     |
| **Valor livre**               | Premium (`payments`) + opção ligada | não se aplica                               | igual ao online                                       |

**RN-50. No gratuito, a única forma é a entrega pessoal.** O botão do item é "Vou presentear", e o carrinho termina em "Reservar presentes".

**RN-51. No Premium, o carrinho oferece "Pagar agora" e "Vou entregar pessoalmente".** O anfitrião pode desligar a entrega pessoal na aba Pagamentos. Aí a lista só aceita pagamento online.

**RN-52. O evento só "recebe presentes online" com três condições:** o recurso `payments`, o Access Token **e** a Public Key do Mercado Pago do anfitrião cadastrados. Faltando qualquer um, a página se comporta como gratuita para pagamentos. — `Event::acceptsOnlineGifts()`

**RN-53. Identificação no carrinho:** os mesmos dados obrigatórios da confirmação de presença (RN-31). Se o convidado já se identificou, o carrinho vem preenchido.

**RN-53c. Um pedido por intenção.** O pedido online nasce no "Pagar agora", não ao abrir o carrinho, e o carrinho só é esvaziado quando o pagamento é aprovado. Se o mesmo convidado (mesmo navegador) abre de novo o pagamento do mesmo presente — depois de recarregar a página ou fechar o carrinho —, o sistema **retoma o mesmo pedido**: sem pagamento iniciado, com os preços de agora; com Pix ou boleto em aberto, mostra **o mesmo** código, com a opção "Pagar de outro jeito" (que cancela o anterior). Pix expirado começa um pedido novo. O mesmo presente pago há menos de 2 horas volta como pago, para não ser cobrado duas vezes. Trocar de presente fecha o pedido anterior sem pagamento iniciado ("Não concluído"). — `OrderStoreService::resumeOpenOrder`

**RN-53a. Checkout abandonado vira "Não concluído".** O pedido online nasce quando o convidado abre o pagamento. Se em **2 horas** nenhum pagamento foi iniciado, ele passa a "Não concluído" (`orders:expire-abandoned`, a cada 10 minutos). Se um pagamento foi iniciado (Pix, boleto), o sistema pergunta ao Mercado Pago como ele terminou, caso o aviso não tenha chegado: Pix não pago vira "Cancelado", e boleto ainda em aberto é perguntado de novo depois. Não há estoque envolvido. Se o convidado deixou a tela aberta e paga depois, o pedido é reaberto e o pagamento vale. — `OrderExpireAbandonedService`

**RN-53b. A lista de pedidos abre nos presentes.** As abas são **Presentes** (pagos, reservados e recebidos), **Aguardando pagamento** (com contador), **Não concluídos** (não concluídos, cancelados e recusados) e **Todos**.

**RN-54. Reserva:** o item fica separado para aquele convidado, e ninguém mais escolhe a mesma unidade.

- **Convidado:** pode **desistir** pela página, em "Seus presentes reservados". Só vale no navegador de quem reservou e enquanto o item estiver reservado.
- **Anfitrião:** em Pedidos, **marca como recebido** ou **cancela a reserva**.
- **Efeito no estoque:** cancelar devolve o item à lista. Depois de recebido, não dá mais para desistir.

**RN-55. Reserva nunca vai para o Mercado Pago.** O sistema recusa enviar uma reserva para pagamento, e os avisos de pagamento ignoram reservas.

**RN-56. Valor livre:** qualquer valor de R$ 1 a R$ 100.000, sem item, sempre pago online.

- Aparece em **todos os modos de exibição**, inclusive "Sem presentes", quando o anfitrião liga "Aceitar contribuição de valor livre".
- Nos Pedidos aparece como "Contribuição de valor livre".
- Não é possível mandar valor livre e itens no mesmo pedido.

**RN-57. Mensagem ao anfitrião:** opcional, até 1.000 caracteres, em qualquer forma de presentear.

---

## 7. Presente anônimo

**RN-60. Qualquer forma de presentear pode ser anônima.** O convidado ainda informa os dados, porque o Mercado Pago exige e o sistema precisa reconhecê-lo, mas o **anfitrião** não vê quem foi.

**RN-61. O que o anfitrião vê de um presente anônimo:** item, valor e **mensagem**. Nome e WhatsApp aparecem como "Anônimo", ou "Anônimo — entrega pelo convidado" na reserva. **Data e hora ficam ocultas.** **Por quê:** o horário poderia ser cruzado com o horário da confirmação de presença. Quem quiser se identificar assina a mensagem.

**RN-62. O anonimato não pode vazar por outros caminhos:**

- o presente anônimo **não conta** no "Presentes dados" do convidado;
- quem se ligou ao evento **só** por um presente anônimo, sem confirmação de presença e sem outro pedido, **não aparece** na lista de convidados do anfitrião;
- o bloqueio de exclusão do convidado **ignora** presentes anônimos (RN-97), senão denunciaria quem deu.

**RN-63. O admin vê tudo**, com o nome seguido de "(anônimo)".

**RN-64. Na reserva anônima, a entrega fica por conta do convidado.** A tela de agradecimento diz isso explicitamente.

---

## 8. Pagamento de presentes (Mercado Pago do anfitrião)

**RN-65. O dinheiro dos presentes vai para a conta Mercado Pago do anfitrião**, cadastrada por evento na aba Pagamentos. A plataforma não intermedeia esse valor.

**RN-66. O valor cobrado é sempre calculado no servidor**, pelos preços dos itens ou pelo valor livre informado. Nunca vem do navegador.

**RN-67. Um pedido só é marcado como pago depois que o sistema reconsulta o pagamento no Mercado Pago.** Não basta o aviso do webhook, e receber o mesmo aviso duas vezes não altera nada. Um estorno depois de pago devolve o item à lista.

**RN-68. Credenciais do anfitrião:**

- ficam criptografadas;
- o Access Token **não é exibido** no painel. Salvar a aba com o campo em branco **mantém** o token atual;
- as credenciais nunca aparecem na auditoria.

**RN-69. Se o evento perder o recurso de pagamentos**, novos pedidos e cobranças online são recusados. Os pedidos existentes continuam no histórico, e os pagamentos já iniciados continuam sendo processados pelo webhook.

---

## 9. Premium

**RN-70. O plano é por evento e por data:** pagamento único (R$ 39,90 por padrão), sem mensalidade. Ele é comprado para a data do evento e termina depois dela, para que uma compra não sirva, edição após edição, para outra festa:

- só dá para comprar com a **data do evento preenchida e ainda não passada**; a compra guarda essa data;
- os recursos **do evento** valem até o fim do dia da **data do evento + a carência** (60 dias por padrão), no horário de Brasília. Os recursos **do anfitrião** (agenda de contatos) não terminam;
- enquanto o Premium vale, o anfitrião só muda a data **dentro da janela** (90 dias por padrão, para mais ou para menos) em torno da data da compra, e não pode apagá-la. O fim do Premium acompanha a mudança. Além da janela, só o admin muda;
- mudar a data **nunca traz de volta** um Premium que já terminou. A mudança feita pelo **admin** (um adiamento combinado) passa a ser a data da compra e pode trazê-lo de volta;
- depois do fim, a página continua no ar sem os recursos Premium. Os recados já aprovados continuam no mural, mas sem o formulário para novos. A aba Premium mostra "O Premium deste evento terminou em …" e permite comprar de novo para uma nova data;
- o preço, a carência e a janela são definidos pelo admin em **Configurações** (padrões em `config/premium.php`). Mudar a carência recalcula o fim do Premium de todos os eventos.

Compras feitas antes desta regra seguem a mesma conta: data do evento (ou, sem data, o dia do pagamento) + 60 dias.

**RN-71. O plano inclui:**

- para **o evento**: Receber presentes online, Lista nominal de convidados, Lista de presentes completa e Mural de recados;
- para **o anfitrião**: Agenda de contatos, que vale para todos os eventos dele.

A lista fica em `config/premium.php`.

**RN-72. O Premium é pago na conta Mercado Pago da plataforma** (`.env`), nunca na do anfitrião.

**RN-73. O preço cobrado é o das Configurações**, nunca o enviado pelo navegador. Pagamento com valor abaixo do preço **não libera** nada.

**RN-74. Quando os recursos são liberados:**

- **cartão aprovado:** na hora, e a página recarrega mostrando "Este evento é Premium";
- **Pix e boleto:** quando o Mercado Pago avisar. A aba Premium mostra "Pagamento aguardando confirmação" com o botão **"Verificar pagamento"**.

**RN-75. Estorno ou chargeback de uma compra retira só o que aquela compra liberou.** O que o admin liberou manualmente continua.

**RN-76. Um evento que já tem todos os recursos do plano não pode ser cobrado de novo.** A aba mostra "Este evento é Premium".

**RN-77. O admin pode liberar ou retirar qualquer recurso manualmente**, sem cobrança: a aba "Liberar recursos" do evento e o cadastro do usuário, no caso da agenda. **Retirar remove a concessão qualquer que seja a origem, inclusive se foi comprada.**

**RN-78. Uma concessão pode ter validade** (`expires_at`). Vencida, o recurso deixa de valer. Hoje nenhuma tela define validade: as compras e as liberações manuais são permanentes.

**RN-79. Recurso bloqueado nunca some da tela:** aparece com **cadeado** e selo "Premium", explica o que faz e tem o botão **"Conhecer o Premium — R$ 39,90"**, que leva à aba Premium do evento.

**RN-80. Configurações premium feitas sem o recurso não valem.**

- **Anfitrião sem o recurso:** não consegue editá-las (a tela fica travada e o servidor recusa).
- **Admin:** pode deixar tudo pronto antes de liberar, e vê o aviso "não está liberado neste evento".

**RN-81. Migrações de recursos já aplicadas:**

- eventos que já tinham credencial do Mercado Pago ganharam "Receber presentes online";
- eventos que já recebiam pagamentos ganharam "Lista de presentes completa" e "Mural de recados", ligados à mesma compra.

Assim, quem já era premium não perdeu nada nem é cobrado de novo.

---

## 10. Mural de recados

**RN-85. Recurso premium do evento.** Sem ele, a seção "Recados" nem aparece na página, e o envio é recusado.

**RN-86. Qualquer visitante pode deixar recado** com nome (até 100 caracteres) e mensagem (até 1.000), com ou sem presente. Se o navegador já é de um convidado reconhecido, o recado fica ligado a ele.

**RN-87. Nada aparece publicamente sem aprovação.** O recado entra como "Aguardando", e o anfitrião **aprova**, **tira da página** ou **exclui**. A página mostra os aprovados, do mais recente para o mais antigo.

**RN-88. Excluir o evento de vez exclui os recados.**

---

## 11. Agenda de contatos

**RN-89. Recurso premium do anfitrião.** Sem ele, o menu aparece com cadeado e a lista fica bloqueada: não dá para criar nem editar contatos.

**RN-90. A agenda é privada de cada anfitrião.** Nunca é compartilhada nem cruzada com eventos de outros anfitriões.

**RN-91. Um contato precisa de nome e de pelo menos um entre telefone, e-mail e CPF.** Sem isso, não seria possível reconhecê-lo depois.

**RN-92. Importar um evento para a agenda:** traz os convidados daquele evento. Quem já está na agenda, reconhecido por CPF, telefone ou e-mail, **não é duplicado**: o contato só recebe os dados que faltavam, e o nome dado pelo anfitrião é mantido.

**RN-93. Adicionar contatos a um evento:** eles entram como convidados "Sem resposta". Quando confirmarem presença, o sistema os reconhece e atualiza o mesmo registro. Quem já está na lista do evento é ignorado, e contatos de outro anfitrião nunca entram.

**RN-94. Excluir um contato é definitivo** (não há lixeira) e não afeta os convidados dos eventos.

---

## 12. Exclusão, lixeira e histórico financeiro

**RN-95. Pedidos nunca são apagados em cascata.** O banco recusa apagar um convidado ou evento que tenha pedidos. **Por quê:** antes, excluir um convidado apagava os presentes e o financeiro dele sem volta.

**RN-96. "Excluir" no painel manda para a lixeira** (convidados, presentes e eventos). Na lixeira, o item some das telas e da página e pode ser **restaurado** pelo filtro "Lixeira".

**RN-97. Convidado com presente pago identificado não pode ir para a lixeira** pelo anfitrião. A opção é **Anonimizar**. Presentes anônimos não bloqueiam (RN-62).

**RN-98. Anonimizar (LGPD):**

- apaga nome, WhatsApp, e-mail e CPF, e o nome vira "Convidado removido";
- troca o identificador do cookie, para o navegador antigo não reconhecer mais a pessoa;
- mantém a confirmação de presença e os pedidos;
- **não pode ser desfeito**.

**RN-99. Presente que já está em algum pedido não pode ser excluído.** O caminho é desmarcar "Ativo" (RN-46).

**RN-100. Evento com presentes pagos:**

- o anfitrião **não exclui**, **arquiva**: o evento sai da lista e a página fica inacessível para os convidados (RN-13). Pode desarquivar quando quiser;
- sem presentes pagos, o evento vai para a lixeira normalmente.

**RN-101. Só o admin exclui um evento de vez**, com tudo o que há nele: convidados, presentes, pedidos (inclusive pagos), recados e configurações. O histórico financeiro do evento se perde.

**RN-102. Anfitrião cujos eventos têm pedidos não pode ser excluído.** Excluir um anfitrião exclui os eventos dele.

**RN-103. Exclusão em massa pula o que tem vínculo financeiro** e avisa quantos itens foram mantidos e por quê.

**RN-104. Limpeza automática da lixeira** (`trash:purge`, diária): exclui de vez o que está na lixeira há **mais de 30 dias**, com estas ressalvas:

- **nunca** exclui o que tem pedido pago, que fica guardado e oculto;
- presente que está em algum pedido não é excluído;
- pedidos não pagos do convidado (checkouts abandonados) vão junto;
- reservas ativas do convidado são canceladas, e o item volta à lista.

---

## 13. Auditoria

**RN-110. Só o admin vê a auditoria.** O anfitrião não vê nem apaga o rastro das próprias ações.

**RN-111. O que é registrado:**

- alterações de evento, aparência, formulário de presença e opções de presente;
- presentes (criar, editar, lixeira, restaurar);
- recursos premium liberados ou removidos;
- convidados: **somente** lixeira, restauração, exclusão definitiva e anonimização.

**RN-112. A auditoria não guarda dados pessoais de convidados nem credenciais.** Se guardasse, a anonimização não teria efeito.

**RN-113. O registro diz quem fez**, ou "Sistema" quando foi automático (webhook, limpeza da lixeira).

**RN-114. Ações do próprio convidado não viram auditoria.** Isso inclui confirmar presença, refazer a resposta e mexer na própria lista de acompanhantes.

---

## 14. Limites e proteções técnicas

**RN-115. Limite de requisições por minuto, por visitante:**

| Ação                | Limite |
| ------------------- | ------ |
| Criar pedido        | 20     |
| Pagar pedido        | 10     |
| Desistir de reserva | 20     |
| Enviar recado       | 10     |

**RN-116. Tamanhos máximos:**

| Campo                            | Máximo |
| -------------------------------- | ------ |
| Nome                             | 255    |
| WhatsApp                         | 30     |
| E-mail                           | 255    |
| Mensagem de presente             | 1.000  |
| Recado: nome                     | 100    |
| Recado: mensagem                 | 1.000  |
| Mensagem do modo "Sem presentes" | 300    |
| Pessoas por confirmação          | 20     |

**RN-117. Avisos do Mercado Pago:** os webhooks (`/{slug}/orders/mercadopago-webhook` e `/webhooks/mercadopago/premium`) dispensam o token de formulário (CSRF), porque quem chama é o Mercado Pago. Como o status é sempre reconsultado na API (RN-67), um aviso falso não altera nada.

**RN-118. Endereço de aviso só quando é público.** Em `localhost`, o sistema não envia a `notification_url`, senão o Mercado Pago recusaria o pagamento inteiro. Por isso, localmente, Pix e boleto só se confirmam pelo botão "Verificar pagamento", no caso do Premium.

**RN-119. E-mail do pagador:** o Mercado Pago recusa e-mails com domínio inválido. No sandbox, recusa também usuários de teste que não existem. O sistema mostra "Não foi possível processar o pagamento" e registra a compra ou pedido como recusado.

---

## Lacunas conhecidas

Comportamentos que hoje **não** são como deveriam, ou que ainda não têm regra. Servem de ponto de partida para decisões:

- **Posição do presente anônimo:** na lista de pedidos, ordenada por data, a posição de um pedido anônimo dá uma pista aproximada de quando ele foi feito (RN-61).
- **Mesmo navegador:** um acompanhante que confirma presença pelo navegador de quem o listou é reconhecido pelo cookie como essa pessoa (RN-22).
- **Convite com e-mail repetido:** o pedido de convite não verifica duplicidade. Aprovar um pedido cujo e-mail já tem conta gera erro.
- **Retirada de recurso comprado:** o admin consegue retirar em "Liberar recursos" um recurso que o anfitrião pagou (RN-77).
- **Limpeza da auditoria:** está configurada para 365 dias (`config/activitylog.php`), mas o comando de limpeza não está agendado. Na prática, a auditoria nunca é limpa.
- **Agendador local:** o `trash:purge` só roda sozinho se houver um cron ou um `schedule:work`. O Docker local não tem esse serviço.

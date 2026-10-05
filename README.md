# Wishlisti

Plataforma de páginas de evento (casamento, chá de bebê, chá de panela, aniversário) com confirmação de presença e lista de presentes. O anfitrião monta a página no painel. O convidado acessa sem criar conta, confirma presença, deixa recados e presenteia de uma destas formas:

- pagando online, pelo Mercado Pago do anfitrião;
- reservando o item para entregar pessoalmente;
- contribuindo com qualquer valor.

A plataforma é gratuita, com um **Premium por evento** (pagamento único, R$ 39,90) que libera recursos avançados. O Premium é cobrado na conta Mercado Pago **da plataforma**.

> **Regras de negócio:** para consultar como o sistema se comporta em cada situação (quem vê o quê, o que o Premium libera, quando algo pode ser excluído…), veja [docs/regras-de-negocio.md](docs/regras-de-negocio.md), com regras numeradas (RN-xx).

---

## Sumário

1. [Stack](#stack)
2. [Rodando localmente](#rodando-localmente)
3. [Variáveis de ambiente](#variáveis-de-ambiente)
4. [Perfis de acesso](#perfis-de-acesso)
5. [Gratuito × Premium](#gratuito--premium)
6. [Funcionalidades](#funcionalidades)
7. [Pagamentos (Mercado Pago)](#pagamentos-mercado-pago)
8. [Proteção de dados e histórico financeiro](#proteção-de-dados-e-histórico-financeiro)
9. [Arquitetura do código](#arquitetura-do-código)
10. [Modelo de dados](#modelo-de-dados)
11. [Testes e qualidade](#testes-e-qualidade)
12. [Operação e produção](#operação-e-produção)
13. [Limitações conhecidas e próximos passos](#limitações-conhecidas-e-próximos-passos)

---

## Stack

| Camada                     | Tecnologia                                                             |
| -------------------------- | ---------------------------------------------------------------------- |
| Back-end                   | PHP 8.5, Laravel 13                                                    |
| Painel (admin e anfitrião) | Filament 4, em `/admin`                                                |
| Página pública do evento   | Inertia + React + TypeScript (Vite, Tailwind)                          |
| Banco                      | MySQL 8.4 (testes usam SQLite em memória)                              |
| Pagamentos                 | Mercado Pago: SDK `mercadopago/dx-php` e Payment Brick                 |
| Auditoria                  | `spatie/laravel-activitylog`                                           |
| Infra local                | Docker Compose (`app`, `scheduler`, `nginx`, `mysql`, `redis`, `node`) |

---

## Rodando localmente

```bash
cp .env.example .env            # ajuste as variáveis (veja abaixo)
docker compose --profile dev up -d   # o profile dev inclui o Vite (serviço node)
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed   # o seed cria o catálogo de presentes
```

| Serviço                   | Endereço                                    |
| ------------------------- | ------------------------------------------- |
| Aplicação                 | http://localhost:8080                       |
| Painel                    | http://localhost:8080/admin                 |
| Vite (dev server)         | http://localhost:5173                       |
| MySQL (de fora do Docker) | `127.0.0.1:3307`, usuário e senha do `.env` |

No DataGrip e em outros clientes de banco, use a porta **3307**. `DB_HOST=mysql` e `DB_PORT=3306` só valem de dentro da rede do Docker.

**Criando o primeiro admin.** Não há seeder de usuários, e `is_admin` fica de fora do mass assignment de propósito:

```bash
docker compose exec app php artisan tinker --execute='
  $u = App\Models\User::create(["name" => "Admin", "email" => "admin@exemplo.com", "password" => "troque-esta-senha"]);
  $u->forceFill(["is_admin" => true, "email_verified_at" => now()])->save();'
```

**Página em branco depois de editar o `.env`?** O Vite reinicia sozinho quando o `.env` muda e às vezes trava por "porta 5173 em uso". Resolva com `docker compose restart node`.

---

## Variáveis de ambiente

Além das padrão do Laravel:

| Variável                                              | Para quê                                                                                                                          |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `MERCADOPAGO_ACCESS_TOKEN` / `MERCADOPAGO_PUBLIC_KEY` | Conta Mercado Pago **da plataforma**, que recebe o pagamento do Premium. Sem elas, a aba Premium mostra "Pagamento indisponível". |
| `PREMIUM_PRICE`                                       | Preço inicial do Premium por evento (padrão `39.90`). Depois, o admin muda em **Configurações**, no painel.                       |
| `FREE_GIFT_LIMIT`                                     | Máximo de presentes na lista do plano gratuito (padrão `15`).                                                                     |
| `FORWARD_DB_PORT` / `APP_PORT`                        | Portas expostas pelo Docker (padrão 3307 e 8080).                                                                                 |

As credenciais Mercado Pago **de cada anfitrião**, que recebem os presentes, não ficam no `.env`. Elas são cadastradas por evento, na aba **Pagamentos** do painel.

Depois de mudar o `.env`: `docker compose exec app php artisan config:clear`.

---

## Perfis de acesso

| Perfil        | Como chega                                                                                                                          | O que faz                                                                                                               |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Admin**     | criado à mão (veja acima)                                                                                                           | vê e edita tudo; aprova convites; libera recursos premium; exclui eventos de vez; vê a auditoria e as vendas do Premium |
| **Anfitrião** | solicita convite na página inicial, o admin aprova em _Solicitações de convite_ e ele recebe por e-mail o link para definir a senha | cria e gerencia os próprios eventos; só vê os próprios dados                                                            |
| **Convidado** | abre a página pública do evento (`/{slug}`), sem conta                                                                              | confirma presença, presenteia, deixa recados; é reconhecido por um cookie do navegador e pelos dados de contato         |

---

## Gratuito × Premium

Os recursos premium são **concessões** (`feature_grants`) ligadas a um evento ou a um anfitrião. Cada concessão guarda a origem (`admin` ou `purchase`), a compra que a gerou e uma validade opcional. O catálogo de recursos está em `app/Enums/Premium/Feature.php`, e o que o plano inclui está em `config/premium.php`.

| Recurso (`Feature`)                           | Escopo    | Sem ele (gratuito)                                                    | Com ele (Premium)                                                                                       |
| --------------------------------------------- | --------- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `payments`: Receber presentes online          | evento    | lista só com reserva para entrega pessoal; aba Pagamentos com cadeado | Pix, cartão e boleto pelo Mercado Pago do anfitrião; valor livre; o anfitrião vê quem deu cada presente |
| `guest_list`: Lista nominal de convidados     | evento    | confirmação com nome, WhatsApp e número de pessoas                    | anfitrião escolhe os dados obrigatórios (telefone, e-mail, CPF) e pede os dados de cada acompanhante    |
| `full_gift_list`: Lista de presentes completa | evento    | só itens do catálogo, 1 unidade cada, até `FREE_GIFT_LIMIT` itens     | presentes personalizados, cotas e lista sem limite                                                      |
| `guestbook`: Mural de recados                 | evento    | aba Recados com cadeado                                               | convidados deixam recados, e o anfitrião aprova o que aparece                                           |
| `contacts`: Agenda de contatos                | anfitrião | menu com cadeado                                                      | agenda própria, importada dos eventos e reaproveitada nos próximos                                      |

Livre em qualquer plano: página do evento, localização com mapa, galeria, aparência, confirmação de presença, reserva de presentes, presente anônimo e os modos de exibição dos presentes.

**Como um evento vira Premium:**

- **Pela compra:** o anfitrião paga na aba **Premium** do evento. O plano libera os recursos de evento para aquele evento e os de anfitrião para o anfitrião.
- **Pelo admin:** no submenu **Liberar recursos** do evento, ou no cadastro do usuário, no caso da agenda.

**Onde o Premium aparece para o anfitrião.** Toda tela ou célula bloqueada mostra um cadeado ou o selo "Premium", junto com o botão **"Conhecer o Premium"**, que leva à aba Premium.

---

## Funcionalidades

### Painel do evento

Cada evento tem um submenu lateral com uma página por assunto, cada uma com seu próprio "Salvar":

| Página                  | Conteúdo                                                                                                   |
| ----------------------- | ---------------------------------------------------------------------------------------------------------- |
| Detalhes                | anfitrião, tipo, título, slug, data, textos, publicado; arquivar, lixeira e exclusão definitiva (só admin) |
| Anfitriões              | co-anfitriões (contas já existentes): editam tudo, menos esta lista e a lixeira; só o dono adiciona/remove |
| Localização             | uma ou mais (cerimônia, festa…) com nome, link próprio, endereço, link do mapa, coordenadas                |
| Página pública          | capa, galeria, imagem do link compartilhado, desfoque da capa, fonte, cores e cor dos botões               |
| Abas da página          | liga/desliga e ordena as abas do menu público; a página abre na primeira aba visível                       |
| Presentes               | itens da lista; botão **Exibição na página**; contador do plano gratuito                                   |
| Convidados              | respostas e acompanhantes; lixeira, restaurar, anonimizar                                                  |
| Recados                 | mural (Premium): aprovar ou tirar da página                                                                |
| Confirmação de presença | dados obrigatórios e acompanhantes (Premium)                                                               |
| Pagamentos              | credenciais Mercado Pago do evento; entrega pessoal; valor livre (Premium)                                 |
| Premium                 | vitrine do plano e checkout                                                                                |
| Liberar recursos        | só admin: concede ou retira recursos do evento                                                             |

Fora do evento, o menu principal tem: Painel (widgets), Agenda de contatos, Eventos, Convidados e Pedidos. O admin vê também Catálogo Geral, Solicitações de convite, Usuários, Auditoria e Vendas Premium.

### Página pública

Menu: **Início**, **Galeria**, **Presentes** (só no modo "Lista"), **Confirmar presença** e **Recados** (só com o mural).

### Confirmação de presença

- **Gratuito:** nome, WhatsApp (obrigatório), e-mail (opcional), se vai e quantas pessoas vão.
- **Premium (`guest_list`):** o anfitrião escolhe entre telefone, e-mail e CPF quais são obrigatórios, sempre com o nome, e pode pedir os dados de cada acompanhante.
- **Reconhecimento do convidado:** primeiro pelo cookie; depois, dentro do mesmo evento, por CPF, telefone (só dígitos) ou e-mail (sem diferenciar maiúsculas), nessa ordem.
- **Individualização:** se um acompanhante listado por alguém confirmar por conta própria, ele sai da contagem de quem o listou e vira uma confirmação individual. Quem já confirmou sozinho não é contado de novo quando outra pessoa o lista.

### Presentes

- **Modos de exibição** (qualquer plano, botão _Exibição na página_):
    - **Lista de presentes:** item próprio no menu.
    - **Discreto, "Se quiser presentear":** fora do menu, recolhido no fim da página inicial, sem itens esgotados, sem contadores e com o preço apagado.
    - **Sem presentes:** só uma mensagem personalizável ("Sua presença é o nosso presente."). Os itens nem são enviados à página, e pedidos de itens são recusados.
- **Entrega pessoal (reserva):** o convidado se identifica com os mesmos dados obrigatórios da confirmação de presença e reserva o item. A quantidade é separada na hora ("Já escolhido" para os outros). Ele pode **desistir** pela página, em "Seus presentes reservados", e o anfitrião marca como **Recebido** ou **cancela a reserva** em Pedidos. No gratuito, essa é a única forma de presentear. No Premium, o convidado escolhe entre esta e o pagamento online, e o anfitrião pode desligar a entrega pessoal.
- **Pagamento online (Premium):** o carrinho gera um pedido pendente, e o Payment Brick cobra na conta Mercado Pago do anfitrião.
- **Valor livre (Premium):** contribuição de qualquer valor, sem item, pelo checkout online. Aparece em qualquer modo de exibição quando o anfitrião liga a opção na aba Pagamentos.
- **Presente anônimo** (só no pagamento online; na entrega pessoal o anfitrião vê quem entregou): nome e contatos ficam opcionais e, se preenchidos, só o admin vê. O anfitrião vê item, valor e mensagem, mas **não** o nome, o contato nem as datas, que permitiriam cruzar com o horário da confirmação de presença. O presente anônimo não entra no "Presentes dados" do convidado, e quem só se ligou ao evento por ele não aparece na lista de convidados. O admin vê tudo.
- **Limites do gratuito:** só itens do catálogo (nome, descrição e imagem vêm do catálogo; o preço é livre), uma unidade cada, até `FREE_GIFT_LIMIT`. O que um evento já tinha antes dos limites é mantido: itens personalizados continuam editáveis e cotas antigas só podem diminuir. As regras valem também no servidor.

### Mural de recados (Premium)

O convidado deixa nome e recado. O recado só aparece na página depois que o anfitrião **aprova** na aba Recados.

### Agenda de contatos (Premium do anfitrião)

É uma agenda privada de cada anfitrião, que nunca é compartilhada com outros anfitriões. Ela permite:

- **importar** os convidados de um evento, sem duplicar quem já está na agenda (o contato só recebe os dados que faltavam);
- **adicionar** contatos como convidados de outro evento. Eles entram como "Sem resposta" e são reconhecidos quando confirmarem presença.

---

## Pagamentos (Mercado Pago)

Existem **duas contas**, e o código nunca as mistura:

|                    | Presentes dos convidados                                 | Premium da plataforma                                         |
| ------------------ | -------------------------------------------------------- | ------------------------------------------------------------- |
| Credenciais        | por evento, em `event_payment_settings`, criptografadas  | `.env` (`MERCADOPAGO_*`) → `App\Support\MercadoPagoPlatform`  |
| Serviços           | `OrderPaymentCreateService`, `OrderPaymentUpdateService` | `PremiumPurchaseStoreService`, `PremiumPurchaseUpdateService` |
| Webhook            | `POST /{slug}/orders/mercadopago-webhook`                | `POST /webhooks/mercadopago/premium`                          |
| Referência externa | id do pedido                                             | `premium-{id}`                                                |

Regras comuns:

- **Valor:** vem sempre do servidor (total do pedido ou `PREMIUM_PRICE`), nunca do navegador.
- **Status:** o webhook só informa o id do pagamento, e o status é **reconsultado** na API do Mercado Pago. Aplicar o mesmo pagamento duas vezes não tem efeito extra.
- **Quem pagou (só admin):** o botão "Ver pagador" em Pedidos e em Vendas Premium consulta nome, e-mail e documento do pagador no Mercado Pago na hora (`app/Services/Payments/PaymentPayerLookupService.php`). Nada é gravado no banco, e cada consulta fica na Auditoria (tipo "Pagamento").
- **Pagamento em dobro:** evitado em três camadas (`app/Support/MercadoPagoPayments.php`):
    - o Pix vale 15 minutos;
    - um pagamento por vez: pagar de novo o mesmo pedido, pedir de novo o mesmo presente no mesmo navegador ou comprar o Premium com um pagamento em aberto **cancela** o anterior no Mercado Pago. Se ele não puder ser cancelado por já ter sido pago, o novo é recusado;
    - só o pagamento do próprio pedido muda o status dele (um Pix abandonado que expira não cancela um pedido pago no cartão), e um segundo pagamento aprovado para algo já pago é **estornado** automaticamente. Se o estorno falhar, fica um erro no log pedindo estorno manual.
- **`notification_url`:** só é enviada quando o endereço é público. Em `localhost`, o Mercado Pago recusaria o pagamento inteiro.
- **Premium:**
    - pagamento abaixo do preço não libera nada;
    - estorno ou chargeback retira só o que aquela compra liberou;
    - evento que já tem tudo não é cobrado de novo;
    - com Pix ou boleto, a aba Premium tem o botão "Verificar pagamento", útil localmente, onde o webhook não chega.

### Testando no sandbox

Com credenciais de teste (`TEST-...`):

- **Cartão:** Mastercard `5031 4332 1540 6351`, validade `11/30`, CVV `123`, titular **`APRO`** (aprovado), CPF `12345678909`.
- **E-mail do pagador:**
    - precisa ser um endereço com formato real. Domínios como `@example.test` são recusados ("payer.email must be a valid email");
    - não pode ser um `@testuser.com` que não exista de fato como usuário de teste ("Payer email forbidden").
- **Parcelas:** o Brick exige que o número de parcelas seja escolhido. Conforme a configuração da conta, o valor exibido pode incluir juros de parcelamento.
- **Pix:** só aparece se a conta tiver uma chave Pix cadastrada.

---

## Proteção de dados e histórico financeiro

- **Pedidos nunca somem em cascata.** O banco recusa (`restrictOnDelete`) apagar convidado ou evento que tenha pedidos.
- **Lixeira** (exclusão reversível) para convidados, presentes e eventos, com filtro e botão "Restaurar". O comando diário `trash:purge` exclui de vez o que está há mais de 30 dias na lixeira, **exceto** o que tem pedido pago. Reservas do convidado excluído voltam para a lista.
- **Quem tem vínculo financeiro:**
    - **Convidado com presente pago identificado:** não é excluído, mas pode ser **anonimizado** (LGPD). Nome, contatos e CPF são apagados e o pedido é mantido.
    - **Presente já em pedidos:** é desativado em vez de excluído.
    - **Evento com presentes pagos:** é **arquivado** (sai da lista e da página pública). Só o admin o exclui de vez, com tudo o que há nele.
    - **Anfitrião com pedidos:** não pode ser excluído.
- **Credenciais Mercado Pago:** ficam fora da auditoria. Na aba Pagamentos o token não é exibido e, se o campo ficar em branco, o valor atual é mantido.
- **Auditoria** (admin): alterações de eventos e presentes; exclusões, restaurações e anonimizações de convidados, sem guardar dados pessoais; concessões premium.

---

## Arquitetura do código

**Organização por domínio** (package-by-feature), com nomes rígidos:

```
app/Models/{Domínio}/...            Events, Guests, Catalog, Orders, Premium, Contacts (+ User)
app/Http/Controllers/{Domínio}/...  controllers de ação única: {Substantivo}{Verbo}Controller
app/Http/Requests/{Domínio}/...     {Substantivo}{Verbo}Request (só Store/Update)
app/Services/{Domínio}/...          {Substantivo}{Verbo}Service com um único execute()
database/factories/{Domínio}/...    espelham o namespace do model
```

Sem `Route::apiResource()`: cada rota é registrada à mão em `routes/web.php`. Migrations e seeders ficam na pasta raiz de cada um.

Peças transversais:

| Onde                                                   | O quê                                                                |
| ------------------------------------------------------ | -------------------------------------------------------------------- |
| `app/Enums/Premium/Feature.php`                        | recursos premium: rótulos, textos de venda, escopo                   |
| `app/Models/Concerns/HasFeatureGrants.php`             | `hasFeature()`, `syncFeatures()`, `grantFeature()` para Event e User |
| `app/Models/Concerns/HasContactDetails.php`            | CPF normalizado e dados de contato (Guest e Contact)                 |
| `app/Support/ContactMatcher.php`                       | reconhece a mesma pessoa por CPF, telefone ou e-mail                 |
| `app/Support/MercadoPagoPlatform.php`                  | credenciais da conta da plataforma                                   |
| `app/Filament/Support/PremiumLock.php`                 | cadeado e botão "Conhecer o Premium"                                 |
| `app/Filament/Support/SafeDeleteBulkAction.php`        | exclusão em massa que pula o que tem vínculo financeiro              |
| `app/Filament/Resources/Events/Events/Pages/Concerns/` | ações do cabeçalho e trava premium das páginas do evento             |
| `resources/js/pages/events/`                           | página pública (`show.tsx`) e suas seções                            |

---

## Modelo de dados

```
users ──< events ──1:1── event_appearances      (cores, fonte, desfoque)
  │         │     ──1:1── event_rsvp_settings    (dados obrigatórios, acompanhantes)
  │         │     ──1:1── event_payment_settings (credenciais Mercado Pago do anfitrião)
  │         │     ──1:1── event_gift_settings    (modo de exibição, mensagem, entrega pessoal, valor livre)
  │         ├──< event_products >── product_templates (catálogo do admin)
  │         ├──< guests (companion_of_guest_id → guests)
  │         ├──< guest_messages (mural)
  │         ├──< orders ──< order_items >── event_products
  │         └──< premium_purchases
  ├──< contacts (agenda do anfitrião)
  └── feature_grants (polimórfico: evento ou usuário; → premium_purchases)
```

- **`orders`:**
    - `fulfillment`: `online` ou `in_person`;
    - `status`: `pending`, `paid`, `failed` ou `cancelled` no online; `reserved`, `received` ou `cancelled` na entrega pessoal;
    - `is_anonymous`: presente anônimo;
    - `is_free_amount`: contribuição sem itens.
- **`guests`:** pertence a um evento. Os dados de convidado não são reaproveitados entre eventos de anfitriões diferentes, por privacidade (LGPD) e porque o convidado não tem login. Quem reaproveita os próprios convidados é o anfitrião, pela agenda. Só `event_id`, `name` e `identifier` são obrigatórios.
- **Exclusão reversível:** `guests`, `event_products` e `events` têm `deleted_at`. `events` tem também `archived_at`.

---

## Testes e qualidade

```bash
docker compose exec app php artisan test                                  # PHPUnit (Feature)
docker compose exec app vendor/bin/pint                                   # estilo PHP
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G      # análise estática (Larastan)
docker compose exec node npm run check                                    # formatação e lint do front (vp)
docker compose exec node npx tsc --noEmit                                 # tipos TypeScript
```

A suíte cobre os fluxos principais: confirmação de presença, pedidos, reservas, pagamentos e webhooks (com o Mercado Pago simulado por `tests/Support/FakeMercadoPagoHttpClient.php`), compra do Premium, limites do gratuito, lixeira, anonimização, acesso ao painel e travas premium.

Validado também **de ponta a ponta no navegador**, com pagamentos reais no sandbox:

- anfitrião cria e publica o evento e cadastra presentes;
- convidado confirma presença, reserva, desiste e reserva de novo;
- anfitrião marca como recebido;
- anfitrião contrata o Premium com cartão e os recursos são liberados;
- anfitrião configura o Mercado Pago do evento, e o convidado paga um valor livre com cartão;
- recado enviado, aprovado e exibido.

---

## Operação e produção

- **Como subir:** o passo a passo está em [docs/deploy.md](docs/deploy.md). A stack é o `docker-compose.prod.yml`, com HTTPS automático pelo Caddy.
- **Agendador:** `trash:purge` roda diariamente pelo scheduler do Laravel (`routes/console.php`). O serviço `scheduler` roda `php artisan schedule:work`, tanto no Docker local quanto no de produção.
- **Webhooks:** as duas URLs precisam ser acessíveis publicamente. Em produção, `APP_URL` deve ser o domínio real, porque o sistema monta a `notification_url` a partir dele.
- **Credenciais:** troque as credenciais de teste pelas de produção (`APP_USR-...`) só no ambiente de produção.
- **Deploy:** rode as migrations. Algumas migrations movem dados (configurações para tabelas próprias, conversão de recursos premium) e têm rollback.

---

## Limitações conhecidas e próximos passos

- **Presente anônimo:** na lista de pedidos ordenada por data, a posição de um pedido anônimo ainda dá uma pista aproximada de quando ele foi feito.
- **Retirada de recurso pelo admin:** retirar um recurso em "Liberar recursos" remove a concessão mesmo que ela tenha sido comprada.
- **Mesmo navegador:** um acompanhante que confirma presença pelo navegador de quem o listou é reconhecido pelo cookie como essa pessoa.
- **Pedidos não pagos:** checkouts abandonados ficam como "Pendente" até a limpeza da lixeira do convidado, sem expiração própria.
- **Ainda não feito:**
    - landing page (`welcome.tsx`) mais atrativa, com os recursos e os planos;
    - contador que vira "dias desde o evento" depois da data.

# Deploy em produção

Tudo roda em Docker, a partir do [docker-compose.prod.yml](../docker-compose.prod.yml). O servidor só precisa do Docker. PHP, Node e o resto vão dentro das imagens.

As imagens do `app` e do `nginx` são montadas pelo **GitHub Actions** a cada push na branch `production`, depois que os testes passam, e publicadas no GitHub Container Registry (`ghcr.io/juliocesaraquino/wishlisti-app` e `wishlisti-nginx`). O servidor só baixa as imagens prontas, sem precisar compilar nada. Cada imagem sai com duas tags: `latest` e o hash do commit.

Enquanto a máquina A1 não sai, o site roda em **duas máquinas micro**, uma para o banco e outra para o resto. Veja [Duas máquinas micro](#duas-máquinas-micro).

| Serviço     | O que faz                                                                         |
| ----------- | --------------------------------------------------------------------------------- |
| `caddy`     | porta de entrada pública (80/443); emite e renova o certificado HTTPS sozinho     |
| `nginx`     | serve os arquivos estáticos e os uploads; repassa o resto para o `app`            |
| `app`       | a aplicação Laravel (PHP-FPM)                                                     |
| `scheduler` | roda as tarefas agendadas (ex.: `trash:purge`, todo dia à meia-noite UTC)         |
| `mysql`     | banco de dados, acessível só de dentro do Docker (ou da rede privada, nas micros) |
| `redis`     | sessões, cache e fila                                                             |

Os dados ficam em volumes do Docker e sobrevivem a atualizações e reinícios: `mysql_data` (banco), `storage_public` e `storage_private` (uploads) e `caddy_data` (certificados).

Todos os comandos abaixo rodam **no servidor**, dentro da pasta do projeto. Para encurtar:

```bash
alias dcp='docker compose -f docker-compose.prod.yml'
```

---

## Antes de começar

O guia usa a **Oracle Cloud (Always Free)**, mas vale para qualquer servidor Linux com Docker.

1. **Crie a máquina** em _Compute → Instances → Create instance_:
    - **Imagem:** Ubuntu 24.04.
    - **Shape:** `VM.Standard.A1.Flex` (Ampere, ARM), com 2 OCPUs e 12 GB de RAM. O Always Free permite até 4 OCPUs e 24 GB no total. Todas as imagens do projeto têm versão ARM.
    - Se aparecer _Out of capacity_, tente outro _Availability Domain_ ou tente de novo mais tarde. É comum nas máquinas gratuitas.
2. **Libere as portas 80 e 443** em dois lugares, porque a Oracle bloqueia nos dois:
    - No console: _Networking → Virtual Cloud Networks → (sua VCN) → Security Lists → Default_, adicione regras de entrada TCP para as portas 80 e 443, origem `0.0.0.0/0`.
    - No servidor, o firewall do Ubuntu da Oracle também bloqueia:
        ```bash
        sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
        sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
        sudo netfilter-persistent save
        ```
3. **Instale o Docker** no servidor e saia e entre de novo no SSH, para o grupo `docker` valer:
    ```bash
    curl -fsSL https://get.docker.com | sudo sh
    sudo usermod -aG docker $USER
    ```
4. **Aponte o domínio** para o IP público da máquina, com um registro **A** no DNS. Prefira um IP público **reservado** (_Networking → Reserved public IPs_), que não muda se a máquina for recriada.

**Atenção:** a Oracle pode recuperar máquinas Always Free que ficam **ociosas** por dias seguidos. Para evitar isso, faça o _upgrade_ da conta para **Pay As You Go**. Os recursos Always Free continuam gratuitos e você só paga se passar dos limites, mas é preciso ter um cartão cadastrado.

## Primeiro deploy

```bash
git clone git@github.com:JulioCesarAquino/wishlisti.git
cd wishlisti
git switch production

cp .env.production.example .env
nano .env                                   # preencha tudo que está marcado com TROCAR

dcp pull
dcp run --rm app php artisan key:generate --show   # copie o valor para APP_KEY no .env

dcp up -d
dcp exec app php artisan migrate --force --seed    # o seed cria o catálogo de presentes
```

Depois, crie o primeiro admin como descrito no [README](../README.md#rodando-localmente), trocando `docker compose` por `dcp`.

Para conferir, acesse `https://SEU-DOMINIO`. O certificado pode levar alguns segundos para sair no primeiro acesso.

## Atualizar o site

É automático. Crie uma branch a partir da `production`, abra um PR para a `production` e aprove o merge. O workflow `deploy` roda os testes, publica as imagens novas e atualiza o servidor por SSH (job `deploy`, com os secrets `DEPLOY_*` do repositório). Leva uns 5 minutos; acompanhe em _Actions_.

Se os testes falharem, nada é publicado e o site continua na versão anterior. Corrija em outro PR.

Para atualizar à mão (por exemplo, se o deploy falhar por SSH), na máquina da aplicação:

```bash
git pull                                   # traz mudanças no compose, Caddyfile etc.
dcp pull
dcp up -d
dcp exec app php artisan migrate --force
```

O deploy automático baixa as imagens pelo hash do commit e marca essas mesmas imagens como `latest` no servidor. Por isso, um `dcp up -d` rodado à mão (para recarregar o `.env`, por exemplo) sobe a versão que está no ar, e não uma `latest` antiga.

**Voltar para uma versão anterior:** o jeito mais simples é reverter o PR no GitHub (_Revert_), que gera um PR novo e passa pelo deploy normal. Na pressa, à mão: coloque `WISHLISTI_TAG=<hash do commit>` no `.env` e rode `dcp pull && dcp up -d`. O próximo deploy automático volta para a versão mais nova. Depois dele, tire o `WISHLISTI_TAG` do `.env`; enquanto a linha estiver lá, os comandos rodados à mão continuam subindo a versão antiga.

Na primeira publicação, o GitHub pode criar os pacotes como **privados**, mesmo com o repositório público. Se o `dcp pull` der _denied_, abra o pacote em _github.com/JulioCesarAquino?tab=packages_, vá em _Package settings → Change visibility_ e deixe **Public**. Só precisa fazer isso uma vez para cada pacote.

## Mudou o `.env`?

As configurações são lidas quando os containers sobem, então é preciso reiniciar:

```bash
dcp up -d --force-recreate app scheduler
```

## Backup

**Sem backup, um problema no servidor apaga os dados de vez.** Todo dia, o [docker/backup.sh](../docker/backup.sh) copia o banco e os uploads e envia tudo para uma pasta no **Google Drive**, fora da Oracle: se a conta for suspensa, os backups continuam lá. Ficam só as 5 cópias mais recentes (`BACKUP_KEEP`), no Drive e na pasta `backups/` do servidor. As mais antigas são apagadas só depois que a do dia chega ao Drive; assim, se o backup falhar, as cópias boas continuam lá.

O envio é feito pelo [rclone](https://rclone.org/drive/), que roda num container e não precisa ser instalado no servidor. Ele também funciona com S3, Dropbox, OneDrive e outros: basta criar outro remote e trocar o `BACKUP_REMOTE`.

### Configurar uma vez

1. **Instale o rclone no seu computador**, que tem navegador (ex.: `sudo apt install rclone`). Ele só serve para fazer o login no Google.
2. **Crie o remote no servidor**, na máquina da aplicação:
    ```bash
    mkdir -p ~/.config/rclone
    docker run --rm -it --user "$(id -u):$(id -g)" -v ~/.config/rclone:/config/rclone rclone/rclone:1.75 config
    ```
    Responda: `n` (novo remote), nome **`gdrive`**, tipo **`drive`**, `client_id` e `client_secret` em branco, scope **`drive.file`** (o rclone só enxerga os arquivos que ele mesmo criou, não o resto do seu Drive), `root_folder_id` e `service_account_file` em branco, `n` para a configuração avançada.
3. **Faça o login.** Quando ele perguntar se pode abrir o navegador, responda `n`. Ele mostra um comando `rclone authorize "drive" "..."`: rode esse comando **no seu computador**, entre na conta do Google e copie o token que aparece no terminal. Cole o token no servidor, responda `n` para Shared Drive e `y` para confirmar. O token fica em `~/.config/rclone/rclone.conf`; ele dá acesso ao Drive, então não o copie para outro lugar.
4. **Preencha no `.env`:** `BACKUP_REMOTE=gdrive:wishlisti-backups` (`gdrive` é o nome do remote e `wishlisti-backups` é a pasta, que é criada sozinha) e, se quiser outro número de cópias, `BACKUP_KEEP`.
5. **Teste** rodando uma vez à mão e confira se os dois arquivos aparecem na pasta `wishlisti-backups` do Drive:
    ```bash
    ./docker/backup.sh
    ```
6. **Agende** para rodar todo dia às 3h. Rode `crontab -e` e adicione a linha abaixo, trocando o caminho pelo da pasta do projeto:
    ```
    0 3 * * * /home/ubuntu/wishlisti/docker/backup.sh >> /home/ubuntu/wishlisti/backups/backup.log 2>&1
    ```

O Drive gratuito tem 15 GB, divididos com o Gmail e o Fotos. O arquivo dos uploads costuma ser o maior, e são 5 cópias dele; de vez em quando, confira o tamanho da pasta.

De vez em quando, confira também o `backups/backup.log`. Se o backup falhar, o erro aparece lá.

### Restaurar

Serve tanto para voltar os dados num servidor que já existe quanto para montar um servidor novo depois de perder o antigo. Neste segundo caso, faça antes o [primeiro deploy](#primeiro-deploy), **sem** o `migrate --seed`: o backup já traz as tabelas e os dados.

1. **Pare o site**, para ninguém gravar nada durante a restauração:
    ```bash
    dcp stop app scheduler
    ```
2. **Baixe os arquivos** do dia escolhido. Se estiverem na pasta `backups/` do servidor, pule este passo. Num servidor novo, faça antes os passos 2 e 3 da configuração (ou copie o `rclone.conf` do seu computador para `~/.config/rclone/`).
    ```bash
    mkdir -p backups
    docker run --rm --user "$(id -u):$(id -g)" -v ~/.config/rclone:/config/rclone -v "$PWD/backups:/backup" \
        rclone/rclone:1.75 copy gdrive:wishlisti-backups /backup --include '*2026-09-28*'
    ```
    Troque a data pela do backup escolhido. Também dá para baixar pelo site do Drive e mandar para o servidor com `scp`.
3. **Restaure o banco.** Isso substitui todos os dados atuais pelos do backup:
    ```bash
    gzip -dc backups/db-2026-09-28-0300.sql.gz \
        | dcp exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
    ```
4. **Restaure os uploads:**
    ```bash
    docker run --rm -v wishlisti-prod_storage_public:/dados -v "$PWD/backups:/backup:ro" alpine \
        tar xzf /backup/uploads-2026-09-28-0300.tar.gz -C /dados
    ```
5. **Religue o site** e rode as migrations. Elas só fazem alguma coisa se o código for mais novo que o backup.
    ```bash
    dcp up -d
    dcp exec app php artisan migrate --force
    ```

**Teste uma restauração de vez em quando**, num servidor de teste, antes de precisar dela de verdade.

## Duas máquinas micro

Solução temporária enquanto a A1 não sai. As duas máquinas são `VM.Standard.E2.1.Micro` (x86, 1 GB de RAM e 1/8 de OCPU cada), e o Always Free permite duas. Elas ficam na mesma subnet e conversam pela rede privada.

| Máquina           | Compose                                                         | O que roda                                    |
| ----------------- | --------------------------------------------------------------- | --------------------------------------------- |
| `wishlisti-micro` | [docker-compose.micro-app.yml](../docker-compose.micro-app.yml) | `caddy`, `nginx`, `app`, `scheduler`, `redis` |
| `wishlisti-db`    | [docker-compose.micro-db.yml](../docker-compose.micro-db.yml)   | `mysql`, com a memória reduzida               |

As imagens do CI são só para x86 (`linux/amd64`). Na A1, que é ARM, adicione `linux/arm64` em `platforms`, no job `publish` do [deploy.yml](../.github/workflows/deploy.yml).

### Rede

Na security list da subnet, além do SSH:

- TCP 80 e 443 e UDP 443, origem `0.0.0.0/0`, para o site.
- TCP 3306, origem **só o IP privado da micro da aplicação** (ex.: `10.0.0.72/32`). O MySQL nunca pode ficar aberto para a internet.

O MySQL também só escuta no IP privado da máquina do banco (`DB_BIND_IP`), então não aparece no IP público dela.

### Preparar as duas máquinas

Em cada uma, instale o Docker (passo 3 de [Antes de começar](#antes-de-começar)) e crie 2 GB de swap, para um pico de memória não derrubar nada:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Depois, clone o projeto nas duas, como no [primeiro deploy](#primeiro-deploy).

### Máquina do banco

Crie o `.env` só com o que o MySQL usa. As senhas têm que ser **as mesmas** do `.env` da aplicação:

```bash
DB_BIND_IP=10.0.0.224          # IP privado desta máquina
DB_DATABASE=wishlisti
DB_USERNAME=wishlisti
DB_PASSWORD=TROCAR
DB_ROOT_PASSWORD=TROCAR
APP_DOMAIN=nao-usado           # exigido pelo docker-compose.prod.yml (via extends); não é usado aqui
```

```bash
docker compose -f docker-compose.micro-db.yml up -d
```

### Máquina da aplicação

Siga o [primeiro deploy](#primeiro-deploy) com duas diferenças:

- no `.env`, `DB_HOST` é o IP privado da máquina do banco (ex.: `DB_HOST=10.0.0.224`);
- use o outro compose: `alias dcp='docker compose -f docker-compose.micro-app.yml'`.

O resto do guia vale igual, com esse `dcp`. O [backup.sh](../docker/backup.sh) percebe pelo `DB_HOST` que o banco está em outra máquina e faz o dump pela rede. Por isso, ele roda na máquina da aplicação, onde também estão os uploads.

Na **restauração**, o passo 3 muda, porque não existe `mysql` no compose da aplicação:

```bash
gzip -dc backups/db-2026-09-28-0300.sql.gz | docker run --rm -i -e MYSQL_PWD="$(grep '^DB_ROOT_PASSWORD=' .env | cut -d= -f2-)" \
    mysql:8.4 mysql -h "$(grep '^DB_HOST=' .env | cut -d= -f2-)" -u root "$(grep '^DB_DATABASE=' .env | cut -d= -f2-)"
```

### Ao migrar para a A1

Faça um backup, monte a A1 com o [docker-compose.prod.yml](../docker-compose.prod.yml) (com `DB_HOST=mysql`), [restaure](#restaurar) o backup nela e aponte o DNS para o IP novo. Depois, apague as duas micros.

## Logs e problemas

```bash
dcp ps                  # o que está rodando
dcp logs -f app         # erros da aplicação (ou nginx, caddy, scheduler...)
```

Os logs da aplicação também ficam em arquivos diários no volume `storage_logs`, que sobrevive aos deploys e guarda `LOG_DAILY_DAYS` dias (14 por padrão). Para lê-los sem entrar na máquina, use o painel: **Logs do sistema**, em `/admin/logs` (só o admin). Lá dá para buscar, filtrar por nível e baixar os arquivos.

O Docker guarda no máximo 50 MB de saída por container (5 arquivos de 10 MB), para não encher o disco.

### Alertas de erro no Telegram

Com `telegram` no `LOG_STACK`, cada erro vai na hora para o seu Telegram. A mesma mensagem é mandada no máximo uma vez a cada `LOG_TELEGRAM_QUIET_MINUTES` minutos (10 por padrão). Sem token, o canal não faz nada.

1. No Telegram, abra o **@BotFather**, mande `/newbot` e escolha um nome (ex.: "Wishlisti Alertas"). Ele responde com o **token** (algo como `123456789:AAH...`).
2. Abra a conversa com o bot novo e mande qualquer mensagem (ex.: "oi"). Sem isso, o bot não pode escrever para você.
3. No navegador, abra `https://api.telegram.org/bot<TOKEN>/getUpdates` (troque `<TOKEN>`). Na resposta, o número em `"chat":{"id":...}` é o **chat id**.
4. No `.env` do servidor:

    ```
    LOG_STACK=daily,stderr,telegram
    LOG_TELEGRAM_BOT_TOKEN=123456789:AAH...
    LOG_TELEGRAM_CHAT_ID=987654321
    ```

5. Recrie o app para ler o `.env` (`dcp up -d app scheduler`) e teste:

    ```bash
    dcp exec app php artisan tinker --execute="Log::error('Teste de alerta do Wishlisti');"
    ```

    A mensagem deve chegar no Telegram em alguns segundos.

Só vão erros (`error` ou pior). Avisos, como "não foi possível cancelar o pagamento em aberto", ficam só no arquivo e na tela de logs.

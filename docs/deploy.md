# Deploy em produção

Tudo roda em Docker, a partir do [docker-compose.prod.yml](../docker-compose.prod.yml). O servidor só precisa do Docker. PHP, Node e o resto vão dentro das imagens.

| Serviço     | O que faz                                                                   |
| ----------- | --------------------------------------------------------------------------- |
| `caddy`     | porta de entrada pública (80/443); emite e renova o certificado HTTPS sozinho |
| `nginx`     | serve os arquivos estáticos e os uploads; repassa o resto para o `app`        |
| `app`       | a aplicação Laravel (PHP-FPM)                                               |
| `scheduler` | roda as tarefas agendadas (ex.: `trash:purge`, todo dia à meia-noite UTC)    |
| `mysql`     | banco de dados, acessível só de dentro do Docker                            |
| `redis`     | sessões, cache e fila                                                       |

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

dcp build
dcp run --rm app php artisan key:generate --show   # copie o valor para APP_KEY no .env

dcp up -d
dcp exec app php artisan migrate --force --seed    # o seed cria o catálogo de presentes
```

Depois, crie o primeiro admin como descrito no [README](../README.md#rodando-localmente), trocando `docker compose` por `dcp`.

Para conferir, acesse `https://SEU-DOMINIO`. O certificado pode levar alguns segundos para sair no primeiro acesso.

## Atualizar o site

Depois de juntar as mudanças na branch `production`:

```bash
git pull
dcp up -d --build
dcp exec app php artisan migrate --force
```

## Mudou o `.env`?

As configurações são lidas quando os containers sobem, então é preciso reiniciar:

```bash
dcp up -d --force-recreate app scheduler
```

## Backup

**Sem backup, um problema no servidor apaga os dados de vez.** O [docker/backup.sh](../docker/backup.sh) copia o banco e os uploads e envia tudo para um bucket S3, **fora do servidor**. No servidor ficam só as cópias dos últimos 7 dias, na pasta `backups/`.

### Configurar uma vez

O exemplo usa o **Object Storage da Oracle**, que tem 20 GB no Always Free e aceita a mesma API do S3. Na AWS, use um bucket S3 e deixe `BACKUP_S3_ENDPOINT` vazio.

1. **Crie o bucket** em _Storage → Buckets_ (ex.: `wishlisti-backups`), no compartimento **root**, porque é nele que a API compatível com S3 procura os buckets. Deixe a visibilidade **Private**, que é o padrão.
2. **Anote o namespace** da conta. Ele aparece nos detalhes do bucket, em _Namespace_.
3. **Gere as chaves de acesso:** clique no seu perfil, no canto superior direito, e vá em _Customer secret keys → Generate secret key_. O valor da chave aparece **uma vez só**. Ele é o `AWS_SECRET_ACCESS_KEY`, e o _Access key_ da lista é o `AWS_ACCESS_KEY_ID`.
4. **Preencha no `.env`:** `BACKUP_S3_BUCKET`, `BACKUP_S3_ENDPOINT` (com o namespace), as duas chaves e `AWS_DEFAULT_REGION` (ex.: `sa-saopaulo-1`).
5. **Teste** rodando uma vez à mão e confira se os dois arquivos aparecem no bucket, na pasta `wishlisti/`:
    ```bash
    ./docker/backup.sh
    ```
6. **Agende** para rodar todo dia às 3h. Rode `crontab -e` e adicione a linha abaixo, trocando o caminho pelo da pasta do projeto:
    ```
    0 3 * * * /home/ubuntu/wishlisti/docker/backup.sh >> /home/ubuntu/wishlisti/backups/backup.log 2>&1
    ```

Os 20 GB gratuitos duram bastante, mas as cópias antigas se acumulam. De tempos em tempos, apague as mais velhas pelo console, no bucket. Outra opção é criar uma regra automática em _Lifecycle Policy Rules_. Para ela funcionar, a Oracle exige uma policy de IAM que autorize o serviço de Object Storage; o próprio console mostra o aviso e o texto da policy.

De vez em quando, confira o `backups/backup.log`. Se o backup falhar, o erro aparece lá.

### Restaurar

Serve tanto para voltar os dados num servidor que já existe quanto para montar um servidor novo depois de perder o antigo. Neste segundo caso, faça antes o [primeiro deploy](#primeiro-deploy), **sem** o `migrate --seed`: o backup já traz as tabelas e os dados.

1. **Pare o site**, para ninguém gravar nada durante a restauração:
    ```bash
    dcp stop app scheduler
    ```
2. **Baixe os arquivos** do dia escolhido. Se estiverem na pasta `backups/` do servidor, pule este passo.
    ```bash
    mkdir -p backups
    docker run --rm -v "$PWD/backups:/backup" --env-file .env \
        -e AWS_REQUEST_CHECKSUM_CALCULATION=when_required -e AWS_RESPONSE_CHECKSUM_VALIDATION=when_required \
        amazon/aws-cli --endpoint-url "$(grep '^BACKUP_S3_ENDPOINT=' .env | cut -d= -f2-)" \
        s3 cp s3://wishlisti-backups/wishlisti/ /backup/ --recursive --exclude '*' --include '*2026-09-28*'
    ```
    Troque `wishlisti-backups` pelo nome do bucket e a data pela do backup escolhido.
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

## Logs e problemas

```bash
dcp ps                  # o que está rodando
dcp logs -f app         # erros da aplicação (ou nginx, caddy, scheduler...)
```

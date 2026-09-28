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

- Um servidor Linux com Docker e o plugin Compose (ex.: AWS Lightsail ou EC2, 2 GB de RAM ou mais).
- As portas **80** e **443** liberadas para a internet. Na AWS, isso fica no _Security Group_ (EC2) ou em _Networking_ (Lightsail). A porta 22 (SSH) fica liberada só para o seu IP.
- O domínio apontando para o IP do servidor: um registro **A** no DNS. Na AWS, use um IP fixo (_Elastic IP_ ou _Static IP_), senão ele muda quando o servidor reinicia.

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

1. **Crie o bucket** no S3 (ex.: `wishlisti-backups`), com o acesso público bloqueado, que é o padrão. Em _Management → Lifecycle rules_, crie uma regra que apaga os arquivos depois de 30 dias, para o custo não crescer para sempre.
2. **Dê permissão de envio** para o servidor, só nesse bucket (`s3:PutObject` em `arn:aws:s3:::wishlisti-backups/*`):
    - **EC2:** anexe uma _IAM Role_ com essa permissão ao servidor e deixe as chaves `AWS_*` do `.env` vazias. Se aparecer um erro de credenciais, ajuste o _metadata hop limit_ do servidor para 2 (_Actions → Instance settings → Modify instance metadata options_).
    - **Lightsail:** crie um usuário IAM com essa permissão e preencha `AWS_ACCESS_KEY_ID` e `AWS_SECRET_ACCESS_KEY` no `.env`.
3. **Preencha** `BACKUP_S3_BUCKET` e `AWS_DEFAULT_REGION` no `.env`.
4. **Teste** rodando uma vez à mão e confira se os dois arquivos aparecem no bucket, na pasta `wishlisti/`:
    ```bash
    ./docker/backup.sh
    ```
5. **Agende** para rodar todo dia às 3h. Rode `crontab -e` e adicione a linha abaixo, trocando o caminho pelo da pasta do projeto:
    ```
    0 3 * * * /home/ubuntu/wishlisti/docker/backup.sh >> /home/ubuntu/wishlisti/backups/backup.log 2>&1
    ```

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
    docker run --rm -v "$PWD/backups:/backup" -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY -e AWS_DEFAULT_REGION \
        amazon/aws-cli s3 cp s3://wishlisti-backups/wishlisti/ /backup/ --recursive --exclude '*' --include '*2026-09-28*'
    ```
    No Lightsail, antes do comando, defina as chaves na sessão: `export AWS_ACCESS_KEY_ID=... AWS_SECRET_ACCESS_KEY=... AWS_DEFAULT_REGION=...`.
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

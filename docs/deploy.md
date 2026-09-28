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

**Faça todo dia.** Sem backup, um problema no servidor apaga os dados de vez.

```bash
# Banco
dcp exec mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' > backup-$(date +%F).sql

# Uploads (fotos de capa e dos presentes)
docker run --rm -v wishlisti-prod_storage_public:/dados -v "$PWD":/backup alpine \
    tar czf /backup/uploads-$(date +%F).tar.gz -C /dados .
```

Guarde os arquivos **fora do servidor** (ex.: num bucket S3). Na AWS, também dá para ativar os _snapshots_ automáticos do disco.

## Logs e problemas

```bash
dcp ps                  # o que está rodando
dcp logs -f app         # erros da aplicação (ou nginx, caddy, scheduler...)
```

#!/bin/sh
# Daily production backup: database dump + uploads, sent to S3.
# Runs on the server (host), usually from cron. Setup and restore: docs/deploy.md
#
#   0 3 * * * /caminho/do/wishlisti/docker/backup.sh >> /var/log/wishlisti-backup.log 2>&1
set -eu

cd "$(dirname "$0")/.."

# Reads one variable from .env without executing the file.
env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

BUCKET=$(env_value BACKUP_S3_BUCKET)
KEEP_LOCAL_DAYS=7
DIR=backups
STAMP=$(date +%F-%H%M)
DB_FILE="$DIR/db-$STAMP.sql.gz"
UPLOADS_FILE="$DIR/uploads-$STAMP.tar.gz"
COMPOSE="docker compose -f docker-compose.prod.yml"

mkdir -p "$DIR"

echo "[$(date)] Início do backup"

$COMPOSE exec -T mysql sh -c \
    'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
    | gzip > "$DB_FILE"

# sh has no pipefail: a dump that failed halfway would still leave a file.
if ! gzip -dc "$DB_FILE" | tail -n 1 | grep -q 'Dump completed'; then
    echo "ERRO: o dump do banco saiu incompleto ($DB_FILE)."
    exit 1
fi

docker run --rm \
    -v wishlisti-prod_storage_public:/dados:ro \
    -v "$PWD/$DIR:/backup" \
    alpine tar czf "/backup/$(basename "$UPLOADS_FILE")" -C /dados .

if [ -n "$BUCKET" ]; then
    # Access keys from .env if set; otherwise the EC2 instance role is used.
    set --
    for var in AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION; do
        value=$(env_value "$var")
        if [ -n "$value" ]; then
            set -- "$@" -e "$var=$value"
        fi
    done

    for file in "$DB_FILE" "$UPLOADS_FILE"; do
        docker run --rm "$@" -v "$PWD/$DIR:/backup:ro" amazon/aws-cli \
            s3 cp "/backup/$(basename "$file")" "s3://$BUCKET/wishlisti/$(basename "$file")"
    done
else
    echo "AVISO: BACKUP_S3_BUCKET vazio no .env; o backup ficou só neste servidor."
fi

# Local copies are only a convenience; the real copy is the one in S3.
find "$DIR" -name '*.gz' -mtime +"$KEEP_LOCAL_DAYS" -delete

echo "[$(date)] Backup concluído: $DB_FILE, $UPLOADS_FILE"

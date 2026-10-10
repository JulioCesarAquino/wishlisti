#!/bin/sh
# Daily production backup: database dump + uploads, sent with rclone to
# Google Drive (or any other rclone remote). Keeps the BACKUP_KEEP most recent
# copies, both there and in backups/.
# Runs on the server (host), usually from cron. Setup and restore: docs/deploy.md
#
#   0 3 * * * /caminho/do/wishlisti/docker/backup.sh >> /var/log/wishlisti-backup.log 2>&1
set -eu

cd "$(dirname "$0")/.."

# Reads one variable from .env without executing the file.
env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

REMOTE=$(env_value BACKUP_REMOTE)
KEEP=$(env_value BACKUP_KEEP)
KEEP=${KEEP:-5}
DIR=backups
STAMP=$(date +%F-%H%M)
DB_FILE="$DIR/db-$STAMP.sql.gz"
UPLOADS_FILE="$DIR/uploads-$STAMP.tar.gz"
COMPOSE="docker compose -f docker-compose.prod.yml"

mkdir -p "$DIR"

echo "[$(date)] Início do backup"

DB_HOST=$(env_value DB_HOST)
DB_PORT=$(env_value DB_PORT)
if [ "$DB_HOST" = mysql ]; then
    $COMPOSE exec -T mysql sh -c \
        'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
        | gzip > "$DB_FILE"
else
    # MySQL on another machine (docker-compose.micro-db.yml): dump over the
    # network. MYSQL_PWD goes by name, so the password stays out of `ps`.
    MYSQL_PWD=$(env_value DB_ROOT_PASSWORD) docker run --rm -e MYSQL_PWD mysql:8.4 \
        mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u root \
        --single-transaction --routines "$(env_value DB_DATABASE)" \
        | gzip > "$DB_FILE"
fi

# sh has no pipefail: a dump that failed halfway would still leave a file.
if ! gzip -dc "$DB_FILE" | tail -n 1 | grep -q 'Dump completed'; then
    echo "ERRO: o dump do banco saiu incompleto ($DB_FILE)."
    exit 1
fi

docker run --rm \
    -v wishlisti-prod_storage_public:/dados:ro \
    -v "$PWD/$DIR:/backup" \
    alpine tar czf "/backup/$(basename "$UPLOADS_FILE")" -C /dados .

# rclone.conf (with the Google token) lives on the host, created once with
# `rclone config`. Same uid as the host, so rclone can save a refreshed token.
rclone() {
    docker run --rm --user "$(id -u):$(id -g)" \
        -v "$HOME/.config/rclone:/config/rclone" \
        -v "$PWD/$DIR:/backup:ro" \
        rclone/rclone:1.75 "$@"
}

# Reads file names, newest first by the stamp in the name, and prints the
# ones beyond the KEEP most recent.
beyond_keep() {
    sort -r | tail -n +"$((KEEP + 1))"
}

if [ -n "$REMOTE" ]; then
    for file in "$DB_FILE" "$UPLOADS_FILE"; do
        rclone copyto "/backup/$(basename "$file")" "$REMOTE/$(basename "$file")"
    done

    # Only reached when both uploads worked (set -e), so a failing backup
    # never deletes the good copies that are already there.
    for prefix in db- uploads-; do
        rclone lsf "$REMOTE" --files-only --include "$prefix*.gz" | beyond_keep |
            while read -r old; do
                # Straight to deletion: Drive's trash counts against the quota.
                rclone deletefile --drive-use-trash=false "$REMOTE/$old"
                echo "Apagado do remoto: $old"
            done
    done
else
    echo "AVISO: BACKUP_REMOTE vazio no .env; o backup ficou só neste servidor."
fi

for prefix in db- uploads-; do
    find "$DIR" -maxdepth 1 -name "$prefix*.gz" | beyond_keep | xargs -r rm -f
done

echo "[$(date)] Backup concluído: $DB_FILE, $UPLOADS_FILE"

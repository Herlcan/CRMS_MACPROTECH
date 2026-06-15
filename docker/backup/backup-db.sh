#!/usr/bin/env sh
set -eu

if [ -f .env ]; then
    set -a
    . ./.env
    set +a
fi

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
BACKUP_DIR="${BACKUP_DIR:-backups}"
DB_SERVICE="${DB_SERVICE:-db}"
DB_NAME="${DB_NAME:-crms_macprotech}"
DB_USER="${DB_USER:-macprotech}"

if [ -z "${DB_PASSWORD:-}" ]; then
    echo "DB_PASSWORD is not set. Copy .env.example to .env and set production secrets." >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

timestamp="$(date +%Y%m%d_%H%M%S)"
backup_file="$BACKUP_DIR/macprotech_${timestamp}.sql"

docker compose -f "$COMPOSE_FILE" exec -T "$DB_SERVICE" \
    mariadb-dump -u "$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" > "$backup_file"

if command -v gzip >/dev/null 2>&1; then
    gzip -f "$backup_file"
    echo "Backup written: ${backup_file}.gz"
else
    echo "Backup written: $backup_file"
fi

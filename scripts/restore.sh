#!/usr/bin/env bash
# Restaura um backup feito por scripts/backup.sh no ambiente Docker local.
#   ./scripts/restore.sh                      → lista os backups disponíveis
#   ./scripts/restore.sh 2026-11-26_153000    → restaura esse backup (SUBSTITUI banco e uploads atuais)
set -euo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1

if [ $# -eq 0 ]; then
  echo "Backups disponíveis:"; ls -1 backups/ 2>/dev/null || echo "  (nenhum)"; exit 0
fi

NAME="$1"; SRC="backups/$NAME"
[ -f "$SRC/db.sql.gz" ] || { echo "Backup não encontrado: $SRC"; exit 1; }

read -r -p "Isso SUBSTITUI o banco e os uploads atuais pelo backup $NAME. Continuar? [s/N] " ok
[ "$ok" = "s" ] || [ "$ok" = "S" ] || { echo "Cancelado."; exit 1; }

run() { docker compose run --rm -T wpcli "$@"; }

echo "==> Banco"
run sh -c "gunzip -c /backups/$NAME/db.sql.gz > /tmp/db.sql && wp db import /tmp/db.sql --quiet"

if [ -f "$SRC/uploads.tar.gz" ]; then
  echo "==> Uploads"
  run sh -c "rm -rf /var/www/html/wp-content/uploads && tar xzf /backups/$NAME/uploads.tar.gz -C /var/www/html/wp-content"
fi

run wp cache flush >/dev/null 2>&1 || true
echo "Restaurado: $NAME"

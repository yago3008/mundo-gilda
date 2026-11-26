#!/usr/bin/env bash
# Backup do banco + uploads do ambiente Docker local.
#   ./scripts/backup.sh            → backups/AAAA-MM-DD_HHMMSS/{db.sql.gz,uploads.tar.gz}
#   KEEP=30 ./scripts/backup.sh    → mantém os 30 mais recentes (padrão 14)
#
# Em produção a lógica é a mesma (wp db export + tar dos uploads); ver docs/development/deploy.md.
set -euo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1

KEEP="${KEEP:-14}"
STAMP="$(date +%Y-%m-%d_%H%M%S)"
DEST="backups/$STAMP"
mkdir -p "$DEST"

run() { docker compose run --rm -T wpcli "$@"; }

echo "==> Banco"
run wp db export "/backups/$STAMP/db.sql" --add-drop-table --quiet
run gzip "/backups/$STAMP/db.sql"

echo "==> Uploads"
run tar czf "/backups/$STAMP/uploads.tar.gz" -C /var/www/html/wp-content uploads 2>/dev/null || echo "  (sem uploads)"

echo "==> Versões"
{
  echo "date=$STAMP"
  echo "git=$(git rev-parse --short HEAD 2>/dev/null || echo '?')"
  echo "wp=$(run wp core version 2>/dev/null | tail -1)"
  run wp plugin list --fields=name,version --format=csv 2>/dev/null | tail -n +2 | sed 's/^/plugin=/'
} > "$DEST/manifest.txt"

# Retenção
ls -1d backups/*/ 2>/dev/null | sort | head -n -"$KEEP" | xargs -r rm -rf

du -sh "$DEST" | awk '{print "Backup pronto: " $2 " (" $1 ")"}'

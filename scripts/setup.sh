#!/usr/bin/env bash
# Sobe e configura o WordPress/WooCommerce do zero (ou atualiza um existente).
# Pode rodar quantas vezes quiser. Uso: ./scripts/setup.sh   (rode da raiz do projeto ou de qualquer lugar)
#
# Conteúdo é SEMENTE: peças, cupom, endereço da loja, fotos do site e páginas só são criados quando
# ainda não existem. O que as donas editarem no painel nunca é desfeito por uma nova execução.
# Parte técnica (plugins, tema, moeda, checkout, frete, URLs) é reaplicada sempre.
#   IMPORT_IMAGES=1          baixa as fotos dos produtos do Wix (só peças novas ou sem foto)
#   SITE_IMAGES=1            importa as fotos do site (só papéis sem foto); =refresh regrava do JSON
#   IMPORT_PRODUCTS=refresh  ressincroniza as peças com o Wix (sobrescreve; só antes da virada)
set -euo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1 # Git Bash no Windows: não converter caminhos /scripts, /data

[ -f .env ] || { cp .env.example .env; echo "Criado .env a partir do .env.example (troque a senha do admin)."; }
set -a; . ./.env; set +a

# Versões fixas dos plugins de terceiros (atualize aqui, com teste).
PLUGINS=(
  "woocommerce:11.3.1"
  "woo-better-shipping-calculator-for-brazil:5.2.6"
  "infixs-correios-automatico:1.15.0"
)
# Tema: o da GILDA; (K1venn) assim que existir wp-content/themes/gilda/style.css; senão o padrão.
if [ -f wp-content/themes/gilda/style.css ]; then THEME="gilda"; else THEME="twentytwentyfive"; fi

# As opções de importação precisam chegar ao container: os scripts PHP leem as variáveis lá dentro.
wp() { docker compose run --rm -T -e IMPORT_IMAGES="${IMPORT_IMAGES:-}" -e IMPORT_PRODUCTS="${IMPORT_PRODUCTS:-}" -e SITE_IMAGES="${SITE_IMAGES:-}" wpcli wp "$@"; }

echo "==> Subindo containers"
docker compose up -d --wait

echo "==> WordPress"
if ! wp core is-installed 2>/dev/null; then
  wp core install --url="http://localhost:${WP_PORT:-8080}" --title="GILDA;" \
    --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" --skip-email
fi
wp language core install pt_BR --activate >/dev/null 2>&1 || true
wp option update timezone_string America/Sao_Paulo >/dev/null
wp option update date_format 'd/m/Y' >/dev/null
# Slogan: conteúdo (Configurações → Geral). Só na primeira vez (vazio ou o padrão do WordPress).
case "$(wp option get blogdescription 2>/dev/null || true)" in
  ''|'Just another WordPress site'|'Só mais um site WordPress'|'Apenas mais um site WordPress') wp option update blogdescription 'Alfaiataria criativa' >/dev/null ;;
esac
wp rewrite structure '/%postname%/' >/dev/null
[ "$THEME" = "gilda" ] || wp theme is-installed "$THEME" || wp theme install "$THEME"
wp theme activate "$THEME" >/dev/null

echo "==> Plugins"
for entry in "${PLUGINS[@]}"; do
  slug="${entry%%:*}"; version="${entry##*:}"
  current="$(wp plugin get "$slug" --field=version 2>/dev/null || true)"
  if [ "$current" != "$version" ]; then
    wp plugin install "$slug" --version="$version" --force
  fi
  wp plugin activate "$slug" >/dev/null 2>&1 || true
  echo "  • $slug $version"
done
wp language plugin install --all pt_BR >/dev/null 2>&1 || true
wp plugin delete akismet hello >/dev/null 2>&1 || true

echo "==> WooCommerce"
wp wc update >/dev/null 2>&1 || true
wp eval-file /scripts/setup-store.php

echo "==> Produtos"
if [ -f data/products.json ]; then
  wp eval-file /scripts/import-products.php
else
  echo "  (sem data/products.json — rode: node scripts/export-wix-products.mjs)"
fi

# Fotos de cada cor (data/product-color-images.json). Só peças ainda sem fotos por cor.
echo "==> Fotos por cor"
wp eval-file /scripts/import-color-images.php

# Páginas legais de docs/legal/ (política de privacidade publicada; o resto em rascunho).
echo "==> Páginas legais"
wp eval-file /scripts/import-legal-pages.php --user="$WP_ADMIN_USER"

# Fotos do site (logo, banners, capítulos, Quem Cria) de data/site-images.json.
# Baixa só o que ainda não foi importado. Opção separada de IMPORT_IMAGES (que traz as fotos dos produtos).
if [ -n "${SITE_IMAGES:-}" ]; then
  echo "==> Fotos do site"
  wp eval-file /scripts/import-site-images.php
fi

echo
echo "Pronto!"
echo "  Site:        http://localhost:${WP_PORT:-8080}"
echo "  Admin:       http://localhost:${WP_PORT:-8080}/wp-admin  (usuário/senha no .env)"
echo "  E-mails:     http://localhost:${MAIL_PORT:-8026}"
echo "  phpMyAdmin:  http://localhost:${PMA_PORT:-8081}"

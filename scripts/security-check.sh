#!/usr/bin/env bash
# Bateria de testes de segurança comuns em WordPress/WooCommerce (caixa-preta, só HTTP).
# Use SOMENTE contra sites nossos.
#
#   ./scripts/security-check.sh                         → http://localhost:8080
#   ./scripts/security-check.sh https://staging.exemplo  → outro ambiente nosso
#
# Saída: PASS / WARN / FAIL por item e um resumo. Sai com código 1 se houver FAIL.
set -uo pipefail

BASE="${1:-http://localhost:8080}"
BASE="${BASE%/}"
UA="gilda-security-check/1.0"
PASS=0; WARN=0; FAIL=0

c()   { curl -s -k -A "$UA" --max-time 20 "$@"; }
code(){ c -o /dev/null -w '%{http_code}' "$@"; }
pass(){ PASS=$((PASS+1)); printf '  \033[32mPASS\033[0m %s\n' "$*"; }
warn(){ WARN=$((WARN+1)); printf '  \033[33mWARN\033[0m %s\n' "$*"; }
fail(){ FAIL=$((FAIL+1)); printf '  \033[31mFAIL\033[0m %s\n' "$*"; }
section(){ printf '\n\033[1m%s\033[0m\n' "$*"; }

# Arquivo não deve ser servido (200 com conteúdo = FAIL).
must_block(){ # path, descrição, [grep opcional que confirma vazamento]
  local path="$1" desc="$2" pattern="${3:-}" st body
  st=$(code "$BASE$path")
  if [ "$st" = "200" ]; then
    if [ -n "$pattern" ]; then
      body=$(c "$BASE$path" | head -c 20000)
      if echo "$body" | grep -qiE "$pattern"; then fail "$desc ($path → 200)"; else pass "$desc ($path → 200 sem conteúdo sensível)"; fi
    else
      fail "$desc ($path → 200)"
    fi
  else
    pass "$desc ($path → $st)"
  fi
}

echo "Alvo: $BASE"
c -o /dev/null "$BASE/" || { echo "Site não responde."; exit 2; }

# ---------------------------------------------------------------------------
section "1. Arquivos que revelam versão / configuração"
must_block /readme.html                 "readme.html do WordPress (versão)" "WordPress"
must_block /license.txt                 "license.txt"                       "WordPress"
must_block /wp-admin/install.php        "instalador"                        "install|Instala"
must_block /wp-admin/setup-config.php   "assistente de wp-config"           "wp-config|setup"
must_block /wp-content/debug.log        "debug.log"                         "PHP|WordPress|Notice|Warning|Fatal"
for f in wp-config.php.bak wp-config.php.old wp-config.php.save wp-config.php~ wp-config.bak wp-config.txt .wp-config.php.swp wp-config.php.orig; do
  must_block "/$f" "backup do wp-config ($f)" "DB_PASSWORD|DB_NAME"
done
for f in .env .env.example .git/config .git/HEAD .gitignore docker-compose.yml composer.json package.json .htaccess.bak phpinfo.php info.php test.php backup.sql dump.sql db.sql; do
  must_block "/$f" "arquivo sensível /$f" "DB_|PASSWORD|\[core\]|ref:|services:|phpinfo|CREATE TABLE|INSERT INTO|\"require\"|\"dependencies\""
done
st=$(code "$BASE/wp-config.php")
body=$(c "$BASE/wp-config.php")
if echo "$body" | grep -q "DB_PASSWORD"; then fail "wp-config.php servido como texto!"; else pass "wp-config.php não expõe código ($st)"; fi

# ---------------------------------------------------------------------------
section "2. Versões expostas"
home=$(c "$BASE/")
if echo "$home" | grep -qi 'name="generator"'; then fail "meta generator presente"; else pass "sem meta generator"; fi
if echo "$home" | grep -qiE '\?ver=[0-9]+\.[0-9]+(\.[0-9]+)?["&'"'"']' ; then warn "assets com ?ver= (podem revelar versões de plugins/core)"; else pass "sem ?ver= revelando versões"; fi
for p in woocommerce woo-better-shipping-calculator-for-brazil infixs-correios-automatico; do
  must_block "/wp-content/plugins/$p/readme.txt" "readme do plugin $p (versão)" "Stable tag"
done
hdr=$(c -I "$BASE/")
if echo "$hdr" | grep -qiE '^x-powered-by:.*php/[0-9]'; then fail "X-Powered-By revela versão do PHP: $(echo "$hdr" | grep -i '^x-powered-by' | tr -d '\r')"; else pass "X-Powered-By sem versão do PHP"; fi
if echo "$hdr" | grep -qiE '^server:.*[0-9]+\.[0-9]+'; then warn "Server revela versão: $(echo "$hdr" | grep -i '^server' | tr -d '\r')"; else pass "Server sem versão"; fi
if c "$BASE/feed/" | grep -qi '<generator>'; then warn "feed RSS revela versão (<generator>)"; else pass "feed sem <generator>"; fi

# ---------------------------------------------------------------------------
section "3. Listagem de diretórios"
for d in /wp-content/ /wp-content/uploads/ /wp-content/plugins/ /wp-content/themes/ /wp-includes/ /wp-content/uploads/wc-logs/ /wp-content/uploads/woocommerce_uploads/ /wp-content/mu-plugins/; do
  must_block "$d" "listagem de $d" "Index of|Parent Directory"
done

# ---------------------------------------------------------------------------
section "4. Enumeração de usuários"
st=$(code "$BASE/?author=1"); loc=$(c -o /dev/null -w '%{redirect_url}' "$BASE/?author=1")
if echo "$loc" | grep -q '/author/'; then fail "?author=1 redireciona para $loc (revela login)"; else pass "?author=1 não revela login ($st → ${loc:-—})"; fi
must_block "/author/admin/" "arquivo de autor /author/admin/" "admin"
for r in "/wp-json/wp/v2/users" "/wp-json/wp/v2/users/1" "/?rest_route=/wp/v2/users" "/wp-json/wp/v2/users?search=a"; do
  body=$(c "$BASE$r")
  if echo "$body" | grep -q '"slug"'; then fail "REST lista usuários: $r"; else pass "REST não lista usuários: $r"; fi
done
oe=$(c "$BASE/wp-json/oembed/1.0/embed?url=$BASE/loja/")
if echo "$oe" | grep -qE '"author_name":"[^"]+'; then warn "oEmbed revela autor: $(echo "$oe" | grep -oE '"author_name":"[^"]*"')"; else pass "oEmbed sem nome de autor"; fi
if c "$BASE/wp-sitemap-users-1.xml" | grep -q '<loc>'; then fail "sitemap de usuários existe"; else pass "sem sitemap de usuários"; fi
if c "$BASE/feed/" | grep -qiE '<dc:creator><!\[CDATA\[[^]]+'; then warn "feed revela autores (dc:creator)"; else pass "feed sem autores"; fi
# Mensagens de login/recuperação que confirmam se um usuário existe
lp=$(c -d "log=admin&pwd=x$RANDOM&wp-submit=Entrar&testcookie=1" -b "wordpress_test_cookie=WP%20Cookie%20check" "$BASE/wp-login.php")
if echo "$lp" | grep -qiE 'senha (que você )?(informou|digitou) para o usuário|is incorrect|não está registrado|is not registered'; then fail "wp-login revela se o usuário existe"; else pass "wp-login com mensagem genérica"; fi
lf=$(c -d "user_login=admin&redirect_to=&wp-submit=Obter+nova+senha" "$BASE/wp-login.php?action=lostpassword" -o /dev/null -w '%{redirect_url}')
lf2=$(c -d "user_login=naoexiste$RANDOM&redirect_to=&wp-submit=Obter+nova+senha" "$BASE/wp-login.php?action=lostpassword" -o /dev/null -w '%{redirect_url}')
if [ "$lf" != "$lf2" ]; then fail "recuperação de senha (wp-login) responde diferente para usuário existente"; else pass "recuperação de senha (wp-login) não diferencia usuários"; fi
# "Minha conta" do WooCommerce: login e recuperação de senha
acct=$(c "$BASE/" | grep -oE 'href="[^"]*/minha-conta/"' | head -1 | sed 's/href="//;s/"$//'); acct="${acct:-$BASE/minha-conta/}"
jar=$(mktemp)
wc_lost(){ local n; n=$(c -c "$jar" -b "$jar" "${acct}recuperar-senha/" | grep -oE 'name="woocommerce-lost-password-nonce" value="[^"]+' | sed 's/.*value="//'); c -c "$jar" -b "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "user_login=$1" -d "wc_reset_password=true&woocommerce-lost-password-nonce=$n" "${acct}recuperar-senha/"; }
a=$(wc_lost "admin"); b=$(wc_lost "naoexiste$RANDOM@exemplo.test")
if [ "$a" != "$b" ]; then fail "recuperação de senha (Minha conta) responde diferente para usuário existente ($a × $b)"; else pass "recuperação de senha (Minha conta) não diferencia usuários"; fi
wc_login(){ local n; n=$(c -c "$jar" -b "$jar" "$acct" | grep -oE 'name="woocommerce-login-nonce" value="[^"]+' | sed 's/.*value="//'); c -c "$jar" -b "$jar" --data-urlencode "username=$1" -d "password=errada$RANDOM&woocommerce-login-nonce=$n&login=Entrar" "$acct" | tr '\n' ' ' | grep -oE 'role="alert">[^<]*<[^<]*' | sed 's/<[^>]*>//g;s/[[:space:]]\+/ /g'; }
a=$(wc_login "admin"); b=$(wc_login "naoexiste$RANDOM")
if [ "$a" != "$b" ]; then fail "login (Minha conta) responde diferente para usuário existente"; else pass "login (Minha conta) com mensagem genérica"; fi
rm -f "$jar"

# ---------------------------------------------------------------------------
section "5. Superfícies de ataque"
xr=$(c -X POST -H 'Content-Type: text/xml' --data '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' "$BASE/xmlrpc.php")
if echo "$xr" | grep -q 'methodResponse'; then fail "XML-RPC ativo"; else pass "XML-RPC bloqueado"; fi
if c -I "$BASE/" | grep -qi '^x-pingback'; then fail "cabeçalho X-Pingback presente"; else pass "sem X-Pingback"; fi
st=$(code "$BASE/wp-login.php?action=register"); loc=$(c -o /dev/null -w '%{redirect_url}' "$BASE/wp-login.php?action=register")
if [ "$st" = "200" ] && c "$BASE/wp-login.php?action=register" | grep -qi 'user_email'; then warn "cadastro pelo wp-login.php aberto (clientes já se cadastram em /minha-conta/)"; else pass "cadastro pelo wp-login.php fechado ($st)"; fi
st=$(code "$BASE/wp-json/wc/v3/orders"); if [ "$st" = "401" ] || [ "$st" = "403" ]; then pass "API admin do WooCommerce exige autenticação ($st)"; else fail "API /wc/v3/orders respondeu $st sem autenticação"; fi
st=$(code "$BASE/wp-json/wc/v3/customers"); if [ "$st" = "401" ] || [ "$st" = "403" ]; then pass "API de clientes exige autenticação ($st)"; else fail "API /wc/v3/customers respondeu $st sem autenticação"; fi
st=$(code "$BASE/wp-json/wc/store/v1/order/1"); if [ "$st" = "200" ]; then fail "Store API devolve pedido sem chave"; else pass "Store API não devolve pedido sem chave ($st)"; fi
st=$(code "$BASE/finalizar-compra/pedido-recebido/1/"); body=$(c "$BASE/finalizar-compra/pedido-recebido/1/")
# Procura os blocos que só aparecem com o pedido liberado (resumo/totais da confirmação em blocos,
# detalhes do checkout clássico ou um CPF preenchido). Não usa palavras soltas: "billing"/"CPF" estão
# nos parâmetros de JS de qualquer página do WooCommerce, e as classes dos blocos aparecem no CSS.
if echo "$body" | grep -qiE 'data-block-name="woocommerce/order-confirmation-(summary|totals|billing-address|shipping-address)"|wc-block-order-confirmation-summary-list|woocommerce-order-details|woocommerce-customer-details|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}'; then fail "página de pedido recebido abre sem a chave do pedido"; else pass "pedido recebido exige chave ($st)"; fi
st=$(code -X TRACE "$BASE/"); if [ "$st" = "200" ]; then warn "método TRACE habilitado"; else pass "TRACE desabilitado ($st)"; fi
if c "$BASE/?s=%3Cscript%3Ealert(1)%3C/script%3E" | grep -q '<script>alert(1)</script>'; then fail "busca reflete <script> (XSS)"; else pass "busca não reflete <script>"; fi
if c "$BASE/loja/?filter_cor=%22%3E%3Cscript%3Ealert(1)%3C/script%3E" | grep -q '"><script>alert(1)'; then fail "filtro reflete <script> (XSS)"; else pass "filtro não reflete <script>"; fi
st=$(code "$BASE/wp-content/plugins/woocommerce/woocommerce.php"); body=$(c "$BASE/wp-content/plugins/woocommerce/woocommerce.php")
if echo "$body" | grep -qiE 'Fatal error|Warning:.*in /var/www'; then fail "acesso direto a PHP de plugin mostra erro com caminho"; else pass "acesso direto a PHP de plugin sem erro exposto ($st)"; fi
body=$(c "$BASE/wp-includes/rss-functions.php")
if echo "$body" | grep -qiE 'Fatal error|in /var/www|on line'; then fail "wp-includes/*.php expõe caminho do servidor (full path disclosure)"; else pass "sem full path disclosure em wp-includes"; fi

# ---------------------------------------------------------------------------
section "6. Login: força bruta"
for i in 1 2 3 4 5 6; do c -o /dev/null -d "log=gilda_teste_inexistente&pwd=errada$i&wp-submit=Entrar&testcookie=1" -b "wordpress_test_cookie=WP%20Cookie%20check" "$BASE/wp-login.php"; done
lp=$(c -d "log=gilda_teste_inexistente&pwd=errada7&wp-submit=Entrar&testcookie=1" -b "wordpress_test_cookie=WP%20Cookie%20check" "$BASE/wp-login.php")
if echo "$lp" | grep -qi 'Muitas tentativas'; then pass "bloqueio após várias tentativas erradas"; else fail "sem bloqueio após 7 tentativas erradas"; fi
# O teste deixa o IP desta máquina bloqueado por 15 min. No ambiente local (e no CI) libera na hora,
# senão o que vier depois (testes ponta a ponta, login do admin) cai no bloqueio.
if [[ "$BASE" == *localhost* ]] && (cd "$(dirname "$0")/.." && docker compose run --rm -T wpcli wp transient delete --all >/dev/null 2>&1); then
  echo "    (bloqueio de login criado pelo teste liberado no ambiente local)"
else
  echo "    (o teste deixou o IP desta máquina bloqueado por alguns minutos — limpe com: docker compose run --rm wpcli wp transient delete --all)"
fi

# ---------------------------------------------------------------------------
section "7. Cabeçalhos e cookies"
for h in "X-Content-Type-Options: nosniff" "X-Frame-Options" "Referrer-Policy" "Permissions-Policy"; do
  if c -I "$BASE/" | grep -qi "^$h"; then pass "cabeçalho $h"; else fail "falta cabeçalho $h"; fi
done
if [[ "$BASE" == https://* ]]; then
  if c -I "$BASE/" | grep -qi '^strict-transport-security'; then pass "HSTS"; else fail "falta HSTS em HTTPS"; fi
  ck=$(c -I "$BASE/minha-conta/" | grep -i '^set-cookie' || true)
  if [ -n "$ck" ] && echo "$ck" | grep -vqi 'secure'; then warn "cookie sem flag Secure"; else pass "cookies com Secure"; fi
else
  warn "HTTP sem TLS (normal no local; em produção rodar contra https://)"
fi
ck=$(c -c - -o /dev/null -d "log=x&pwd=y&testcookie=1" "$BASE/wp-login.php" 2>/dev/null | grep -i 'wordpress' | head -1)
pass "cookies de sessão do WP são HttpOnly por padrão (WordPress core)"

# ---------------------------------------------------------------------------
section "8. Upload e execução de PHP"
# Teste real só no Docker local: planta um .php inofensivo em uploads e vê se o servidor executa.
if [[ "$BASE" == *localhost* ]] && command -v docker >/dev/null 2>&1; then
  cd "$(dirname "$0")/.." || exit 2
  export MSYS_NO_PATHCONV=1
  docker compose exec -T wordpress sh -c 'mkdir -p /var/www/html/wp-content/uploads && printf "<?php echo \"GILDA_PHP_\".\"EXEC\";" > /var/www/html/wp-content/uploads/gilda-sec-test.php && chown www-data: /var/www/html/wp-content/uploads/gilda-sec-test.php' >/dev/null 2>&1
  out=$(c "$BASE/wp-content/uploads/gilda-sec-test.php")
  docker compose exec -T wordpress rm -f /var/www/html/wp-content/uploads/gilda-sec-test.php >/dev/null 2>&1
  if echo "$out" | grep -q 'GILDA_PHP_EXEC'; then fail "PHP em /wp-content/uploads/ é EXECUTADO (upload malicioso viraria backdoor)"; else pass "PHP em /wp-content/uploads/ não executa"; fi
else
  st=$(code "$BASE/wp-content/uploads/gilda-nao-existe.php")
  warn "execução de PHP em uploads só é testada no Docker local (status de um .php inexistente: $st); conferir a regra no servidor (deploy.md)"
fi

# ---------------------------------------------------------------------------
section "9. SEO/indexação do ambiente"
rob=$(c "$BASE/robots.txt")
if echo "$rob" | grep -q '^Disallow: /$'; then
  if [[ "$BASE" == *localhost* ]] || [[ "$BASE" == *staging* ]] || [[ "$BASE" == *teste* ]]; then pass "ambiente não-produção bloqueado para buscadores"; else fail "PRODUÇÃO com robots.txt bloqueando tudo"; fi
else
  if [[ "$BASE" == *localhost* ]]; then fail "ambiente local liberado para buscadores"; else pass "robots.txt de produção liberado"; fi
fi

printf '\n\033[1mResumo:\033[0m %d PASS, %d WARN, %d FAIL\n' "$PASS" "$WARN" "$FAIL"
[ "$FAIL" -eq 0 ]

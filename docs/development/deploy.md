# Deploy em produção — GILDA;

Roteiro para levar a loja do ambiente local para a hospedagem definitiva. O que ainda falta (das donas e nosso) está em [antes-de-producao.md](antes-de-producao.md). A hospedagem ainda não foi escolhida (ver [requisitos-cliente.md](../requisitos-cliente.md), item 3), então este guia serve para qualquer hospedagem com **SSH + WP-CLI** (VPS ou WordPress gerenciado).

## 1. Requisitos do servidor

| Item | Mínimo | Recomendado |
|---|---|---|
| PHP | 8.1 | **8.3** (o mesmo do local) com `opcache`, `intl`, `gd` ou `imagick`, `mbstring`, `zip` |
| Banco | MySQL 8 / MariaDB 10.6 | MariaDB 11 |
| Memória PHP | 256 MB | 512 MB |
| HTTPS | obrigatório | certificado automático (Let's Encrypt) |
| Acesso | SSH + WP-CLI | + cron do sistema |
| Cache | — | cache de página do próprio host + Redis (object cache) |

> Evitar hospedagem compartilhada sem SSH. Sem WP-CLI, os scripts deste repositório não rodam e tudo vira clique no painel.

## 2. Configuração do `wp-config.php` (produção)

Adicionar antes de `/* That's all, stop editing! */`:

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' ); // liga o Meta Pixel e o HSTS; desliga o pagamento de teste
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );          // também forçado pelo mu-plugin gilda-hardening
define( 'DISALLOW_FILE_MODS', false );         // true só se as atualizações forem 100% via deploy
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_POST_REVISIONS', 10 );
define( 'DISABLE_WP_CRON', true );             // usamos o cron do sistema (seção 5)
define( 'WP_AUTO_UPDATE_CORE', 'minor' );      // só correções de segurança automáticas
// Chaves/salts novos: https://api.wordpress.org/secret-key/1.1/salt/
```

## 2.1 Regras do servidor web (obrigatório)

Sem isso, a bateria de segurança falha: arquivos de versão ficam expostos, o `debug.log` fica público e **PHP enviado por upload é executado**.

- **Apache:** usar [`config/apache/gilda-security.conf`](../../config/apache/gilda-security.conf). Incluir no vhost e ajustar `/var/www/html` para o caminho real. Se o host só permite `.htaccess`, adaptar: tirar os blocos `<Directory>`, colocar um `.htaccess` em `wp-content/uploads/` com o bloco de PHP e deixar as regras `<FilesMatch>` no `.htaccess` da raiz.
- **PHP:** [`config/php/gilda.ini`](../../config/php/gilda.ini). O essencial é `expose_php = Off` e `display_errors = Off`.
- **Nginx** (equivalente):

```nginx
server_tokens off;
location ~* ^/(readme\.html|license\.txt|wp-config-sample\.php)$ { deny all; }
location ~* /(readme\.txt|changelog\.txt|README\.md|composer\.(json|lock)|package(-lock)?\.json)$ { deny all; }
location ~* \.(log|sql|sql\.gz|bak|old|orig|save|swp|dist|ini|sh)$ { deny all; }
location ~ /\.(?!well-known) { deny all; }
location = /wp-config.php { deny all; }
location ~ ^/wp-admin/(install|setup-config)\.php$ { deny all; }
location ~* ^/wp-content/uploads/.*\.(php[0-9]?|phtml|phar|pht|phps)$ { deny all; }
location ~* ^/wp-includes/(?!js/tinymce/wp-tinymce\.php$|ms-files\.php$).*\.php$ { deny all; }
autoindex off;
```

- `WP_DEBUG_LOG`: em produção fica `false`. Se precisar ligar para investigar algo, aponte para um caminho **fora** da pasta pública (ex.: `define( 'WP_DEBUG_LOG', '/home/usuario/logs/wp-debug.log' );`).

**Depois do deploy, rodar a bateria contra o site real:**

```bash
./scripts/security-check.sh https://www.mundogilda.com.br
```

Deve terminar com **0 FAIL**. O teste de PHP em uploads só roda de verdade no Docker local; no servidor, conferir à mão que um `.php` colocado em `wp-content/uploads/` responde 403.

## 3. Variáveis de ambiente

Os mu-plugins leem configuração por variável de ambiente (`getenv`). Se o host não permitir variáveis de ambiente, defina-as no topo do `wp-config.php` com `putenv( 'NOME=valor' );`.

| Variável | Produção | Para que serve |
|---|---|---|
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` / `SMTP_SECURE` | dados do serviço de e-mail (ex.: Brevo, `tls`, 587) | envio dos e-mails da loja (`gilda-smtp`) |
| `MAIL_FROM` | `loja@mundogilda.com.br` | remetente padrão |
| `STORE_ADDRESS` / `STORE_CITY` / `STORE_STATE` / `STORE_CEP` | endereço real de envio | origem do frete (`setup-store.php`) |
| `META_PIXEL_ID` | `2143468076438110` | Meta Pixel (`gilda-meta-pixel`) |
| `META_PIXEL_DEBUG` | **não definir** | — |
| `GILDA_CLIENT_IP_HEADER` | ex.: `HTTP_CF_CONNECTING_IP` se houver Cloudflare/CDN | IP real para o limite de login |
| `GILDA_LOGIN_MAX_ATTEMPTS` / `GILDA_LOGIN_LOCKOUT_MIN` | 5 / 15 (padrão) | limite de login |
| `GILDA_DATA_DIR` / `GILDA_LEGAL_DIR` | `<pasta-de-deploy>/data` e `<pasta-de-deploy>/docs/legal` | onde os scripts de carga acham os JSON e os textos legais (só no WP-CLI; padrão `/data` e `/legal`, do Docker) |
| `GILDA_CATALOG_CACHE` / `GILDA_CATALOG_CACHE_DAYS` | não definir (ligado, 3 dias) | cache do catálogo para visitantes (`gilda-catalog-cache`); `0` desliga |

## 4. Primeira instalação

Reproduz no servidor o que o `./scripts/setup.sh` faz no Docker local. **O site precisa de três partes do repositório:** os mu-plugins (funcionalidades), o **tema `gilda`** (todo o visual: sem ele o site sobe com o tema padrão do WordPress) e as pastas de carga (`scripts/`, `data/`, `docs/legal/`).

```bash
# No servidor, na pasta do WordPress
wp core download --locale=pt_BR
wp config create --dbname=... --dbuser=... --dbpass=... --dbhost=...   # depois aplicar a seção 2
wp core install --url=https://www.mundogilda.com.br --title="GILDA;"   --admin_user=<usuario-nao-obvio> --admin_email=<email-da-gilda> --prompt=admin_password

# Ajustes do site (iguais ao setup local)
wp option update timezone_string America/Sao_Paulo
wp option update date_format 'd/m/Y'
wp option update blogdescription 'Alfaiataria criativa'
wp rewrite structure '/%postname%/'
wp plugin delete akismet hello

# Plugins de terceiros — MESMAS versões do PLUGINS=(…) em scripts/setup.sh
wp plugin install woocommerce --version=11.3.1 --activate
wp plugin install woo-better-shipping-calculator-for-brazil --version=5.2.6 --activate
wp plugin install infixs-correios-automatico --version=1.15.0 --activate
wp language core install pt_BR --activate && wp language plugin install --all pt_BR
wp wc update

# Nosso código (do repositório, branch main) — mu-plugins E tema
rsync -av --delete wp-content/mu-plugins/   <servidor>:<wp>/wp-content/mu-plugins/
rsync -av --delete wp-content/themes/gilda/ <servidor>:<wp>/wp-content/themes/gilda/
wp theme activate gilda

# Pastas de carga, fora da pasta pública (os scripts leem GILDA_DATA_DIR e GILDA_LEGAL_DIR;
# no Docker local elas ficam em /data e /legal)
rsync -avR scripts data docs/legal <servidor>:<pasta-de-deploy>/   # -R mantém scripts/, data/ e docs/legal/
export GILDA_DATA_DIR=<pasta-de-deploy>/data
export GILDA_LEGAL_DIR=<pasta-de-deploy>/docs/legal

# Configuração da loja e carga inicial — mesma ordem do setup.sh
wp eval-file <pasta-de-deploy>/scripts/setup-store.php
# Produtos: só na PRIMEIRA carga (o import sobrescreve estoque!). Com fotos do Wix: IMPORT_IMAGES=1
IMPORT_IMAGES=1 wp eval-file <pasta-de-deploy>/scripts/import-products.php
wp eval-file <pasta-de-deploy>/scripts/import-color-images.php           # fotos por cor
wp eval-file <pasta-de-deploy>/scripts/import-legal-pages.php --user=<admin>   # páginas legais
SITE_IMAGES=1 wp eval-file <pasta-de-deploy>/scripts/import-site-images.php    # logo, banners, capítulos
wp eval-file <pasta-de-deploy>/scripts/import-chapters.php               # capítulos
wp eval-file <pasta-de-deploy>/scripts/import-skus.php                   # SKUs
wp rewrite flush
```

Depois da instalação, conferir:

- Home, loja, uma peça, carrinho e checkout abrem **com o visual da GILDA;** (se aparecer o tema padrão, o tema não foi copiado ou ativado).
- `wp theme list` mostra `gilda` como `active`; `wp plugin list --status=must-use` lista os `gilda-*`.
- As sementes são seguras de rodar de novo: elas só preenchem o que falta e nunca desfazem o que as donas editaram no painel.

> **Usuário admin:** não usar `admin`. Criar um usuário para cada pessoa (item 14 dos requisitos), com perfil **Gerente de loja** para quem só cuida de pedidos e estoque.

## 5. Tarefas agendadas (cron)

O WooCommerce depende de tarefas agendadas (e-mails, estoque reservado, cancelamento de pedidos não pagos, Action Scheduler). Com `DISABLE_WP_CRON`, configure no crontab do servidor:

```cron
*/5 * * * * cd /caminho/do/wp && wp cron event run --due-now --quiet
*/5 * * * * cd /caminho/do/wp && wp action-scheduler run --quiet
```

## 6. Backups

Os scripts `scripts/backup.sh` e `scripts/restore.sh` fazem isso no Docker local. Em produção, a lógica é a mesma:

```cron
# todo dia às 3h: banco + uploads, guardando 14 dias
0 3 * * * cd /caminho/do/wp && wp db export - | gzip > ~/backups/db-$(date +\%F).sql.gz \
          && tar czf ~/backups/uploads-$(date +\%F).tar.gz -C wp-content uploads \
          && find ~/backups -mtime +14 -delete
```

- Guardar **uma cópia fora do servidor** (bucket S3/Backblaze ou o backup do próprio host).
- **Testar a restauração** pelo menos uma vez antes do lançamento.

## 7. Cache e desempenho

- Cache de página do host (ou plugin de cache). **Excluir do cache:** `/carrinho/`, `/finalizar-compra/`, `/minha-conta/` e qualquer URL com o cookie `woocommerce_items_in_cart`. Os hosts gerenciados de WooCommerce já fazem isso.
- Object cache com Redis, se o host oferecer.
- Imagens em WebP (o WordPress já gera ao fazer upload, se o servidor suportar).

## 8. Virada (dia D)

> Checklist do que falta antes e no dia: [antes-de-producao.md](antes-de-producao.md).

1. **Congelar o Wix:** nada de novos produtos nem mudanças de estoque (requisitos, item 13).
2. Rodar `node scripts/export-wix-products.mjs` para pegar **o estoque do dia** e importar na produção.
3. Importar clientes/pedidos exportados do Wix (se combinado).
4. Conferir o pagamento real (gateway em produção) com uma **compra de valor baixo** e estornar.
5. Conferir: e-mails chegando (fora do spam), frete calculando, cupom, Pixel (Meta Pixel Helper).
6. **DNS:** baixar o TTL para 300 um dia antes; apontar `www` e a raiz para o servidor novo.
7. Ligar o HTTPS e conferir os redirecionamentos 301 das URLs antigas do Wix (`/product-page/...`, `/category/all-products`, `/cart-page`).
8. Search Console: enviar `https://www.mundogilda.com.br/wp-sitemap.xml`.
9. Manter o Wix ativo (sem domínio) por alguns dias, para consulta, antes de cancelar.

## 9. Voltar atrás (rollback)

- Se der problema **antes** de mudar o DNS: nada muda para o cliente; corrigir com calma.
- Se der problema **depois** de mudar o DNS: apontar o DNS de volta para o Wix (por isso o TTL baixo) e restaurar o backup da produção.

## 10. Atualizações

- Plugins de terceiros: mudar a versão em `scripts/setup.sh`, testar localmente (`./scripts/setup.sh` + roteiro de compra), depois atualizar a produção com `wp plugin update <slug> --version=<nova>`.
- **Sempre fazer backup antes** de atualizar o WooCommerce.
- Nosso código (`wp-content/mu-plugins/` **e** `wp-content/themes/gilda/`): deploy por `git pull` ou `rsync` a partir da branch `main`. Depois de um deploy do tema, o cache do catálogo se renova sozinho; se o host tiver cache de página próprio, limpar também.

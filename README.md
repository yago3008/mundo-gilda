# mundo-gilda

Nova loja da **GILDA;** ([mundogilda.com.br](https://www.mundogilda.com.br)), migrando do Wix para WordPress + WooCommerce.

## Rodando localmente

Requisitos: Docker Desktop e Git Bash (Windows) ou qualquer shell bash (Linux/macOS).

```bash
./scripts/setup.sh
```

Esse comando sobe os containers, instala o WordPress em pt_BR, instala os plugins (versões fixas), configura a loja e importa os produtos. Pode rodar de novo sempre que quiser (por exemplo, depois de um `git pull`):

- **A parte técnica é reaplicada sempre:** plugins, tema, moeda, checkout brasileiro, frete PAC/SEDEX, URLs e atributos.
- **O conteúdo é só semente:** peças, cupom `primeiracompra`, endereço da loja, remetente dos e-mails, avisos de privacidade, slogan, fotos do site, capítulos e páginas legais só são criados quando ainda não existem. O que as donas editarem no painel (preço, estoque, cupom, textos, fotos, capítulos, uma peça apagada) **nunca é desfeito** por uma nova execução.

| Opção | O que faz |
|---|---|
| `IMPORT_IMAGES=1 ./scripts/setup.sh` | Baixa as fotos dos produtos do Wix (só para peças novas ou ainda sem foto) |
| `SITE_IMAGES=1 ./scripts/setup.sh` | Importa as fotos do site de `data/site-images.json` (só os papéis que ainda não têm foto) |
| `SITE_IMAGES=refresh ./scripts/setup.sh` | Regrava todas as fotos do site com o que está no JSON (desenvolvimento) |
| `IMPORT_PRODUCTS=refresh ./scripts/setup.sh` | Ressincroniza as peças com o Wix: nome, preço, estoque, peso e variações (**sobrescreve**; só antes da virada) |

| Serviço     | URL                     |
|-------------|-------------------------|
| Loja        | http://localhost:8080   |
| Admin       | http://localhost:8080/wp-admin (usuário/senha no `.env`) |
| E-mails     | http://localhost:8026 (Mailpit: todo e-mail enviado pelo site cai aqui) |
| phpMyAdmin  | http://localhost:8081   |

Portas, senhas e endereço de origem da loja ficam no `.env` (criado a partir do `.env.example` na primeira execução).

### WP-CLI

```bash
docker compose run --rm wpcli wp <comando>
# ex.: docker compose run --rm wpcli wp wc product list --user=admin
```

### Parar / resetar

```bash
docker compose down        # para (mantém banco e arquivos)
docker compose down -v     # apaga TUDO (banco, plugins, uploads); depois rode ./scripts/setup.sh
```

## Testes automáticos (ponta a ponta)

A suíte em [`tests/e2e/`](tests/e2e) usa [Playwright](https://playwright.dev) e roda contra o ambiente local. Ela cobre os fluxos de negócio:

| Arquivo | O que testa |
|---|---|
| `specs/account.spec.ts` | Cadastro em `/minha-conta/` (e o erro "As senhas não coincidem."), login e recuperação de senha com o link que chega no Mailpit |
| `specs/purchase.spec.ts` | Produto variável (cor + tamanho), "Adicionar ao carrinho", botão de compra direta (`gilda_buy_now`) indo para `/finalizar-compra/`, checkout com CEP 01310-100 preenchendo o endereço, CPF de teste, PAC/SEDEX com preço, cupom `primeiracompra` (10%), "Pagamento de teste", pedido recebido, e-mails do pedido e estoque da variação baixando. Depois, o mesmo e-mail tentando usar o cupom de novo (tem que ser recusado) |
| `specs/shipping-simulator.spec.ts` | Simulador de frete da página do produto mostrando PAC e SEDEX |
| `specs/filters.spec.ts` | Filtros por URL (`?filter_cor=…`, cores com OU, `?filter_tamanho=…`, categoria) contra os atributos dos produtos |

Para rodar (com o ambiente de pé, via `./scripts/setup.sh`):

```bash
cd tests/e2e
npm ci
npx playwright install chromium   # só na primeira vez
npx playwright test               # roda tudo (≈1–2 min)
npx playwright test specs/purchase.spec.ts --headed   # um arquivo, vendo o navegador
npx playwright show-report        # relatório HTML com print, vídeo e trace das falhas
```

- URLs e credenciais vêm do `.env` da raiz (`WP_PORT`, `MAIL_PORT`, `WP_ADMIN_*`). Pra apontar pra outro lugar: `E2E_BASE_URL`, `E2E_MAILPIT_URL` ou `E2E_ENV_FILE`.
- Cada execução usa e-mails únicos (`e2e-…@mundogilda.test`). No fim, os testes apagam as contas, os pedidos e os e-mails que criaram e devolvem o estoque da variação comprada. Para isso usam a API REST do WooCommerce logados como admin.
- Os seletores usam papéis, rótulos e textos (o que a cliente vê), não classes do tema. Os contratos de backend usados de propósito são `name="gilda_buy_now"` e o `data-product_variations` do formulário de compra.
- Mais de um ambiente na mesma máquina (ex.: uma worktree do git): no `.env` daquela pasta, mude `WP_PORT`/`MAIL_PORT`/`PMA_PORT` e acrescente `COMPOSE_PROJECT_NAME=<nome>`. Assim o `setup.sh` cria containers e volumes separados.

### CI (GitHub Actions)

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) roda em todo pull request e push na `main`:

1. `php -l` em `wp-content/mu-plugins/` e `scripts/`, e `bash -n` nos scripts;
2. cria o `.env` a partir do `.env.example`, sobe o Docker e roda `./scripts/setup.sh` (sem `IMPORT_IMAGES`);
3. `./scripts/security-check.sh` (falha se tiver algum FAIL);
4. a suíte Playwright. O relatório HTML fica nos artefatos da execução (`playwright-report`), e quando algo falha vão junto os traces/vídeos (`playwright-test-results`) e os logs do WordPress.

## O que já funciona

- **Contas de cliente:** cadastro com nome, e-mail, senha (com medidor de força) e confirmação de senha; login, recuperação de senha por e-mail, histórico de pedidos e endereços. Também dá pra criar a conta no checkout ou comprar sem cadastro.
- **Checkout brasileiro:** CPF validado, CEP primeiro com autopreenchimento de rua/bairro/cidade/UF, número (com opção S/N), bairro e celular com DDD.
- **Produtos:** os 7 produtos do Wix, com variações de cor e tamanho, estoque por variação e "Esgotado".
- **Cupom `primeiracompra`:** 10% e só na primeira compra. A regra confere a conta e também o e-mail, então não dá pra reusar sem login.
- **E-mails transacionais:** conta criada, pedido recebido, novo pedido para a loja e redefinição de senha.
- **URLs:** produtos continuam em `/product-page/<slug>`, como no Wix (requisito 11.3). As outras URLs antigas (`/category/all-products`, `/cart-page`, slugs com acento, etc.) têm redirecionamento 301.
- **Frete (Correios Automático):** PAC e SEDEX com cotação real a partir de Curitiba (CEP 82015-384), mais 2 dias de postagem. Aparece no simulador da página do produto (com "Não sei meu CEP"), no carrinho e no checkout. Também cria a página "Rastrear encomenda". Funciona sem contrato. Se a GILDA; tiver contrato com os Correios, é só ligar "Contrato próprio" no painel do plugin: os métodos continuam os mesmos.
- **Botão "Comprar"** na página do produto: adiciona e vai direto pro checkout (5.9).
- **Avise-me quando chegar:** tamanho/cor esgotada mostra um formulário de e-mail; quando o estoque volta, a pessoa recebe um e-mail (configurável em WooCommerce → Configurações → E-mails) e sai da lista. As donas veem quantas pessoas esperam cada tamanho em **WooCommerce → Avise-me**. Tem link de cancelamento e limpeza automática (LGPD).
- **"Restam só N"** quando o estoque da peça está no limite (padrão 2, em WooCommerce → Configurações → Produtos → Inventário).
- **Filtros da loja** por categoria, cor, tamanho e preço, via URL (`?filter_cor=marrom`, `?min_price=…`). Cada cor tem o código hex salvo para as bolinhas de cor. Detalhes em [backend-para-frontend.md](docs/development/backend-para-frontend.md).
- **SEO:** meta description (editável por página/produto/categoria), Open Graph para prévia no WhatsApp/Instagram, sitemap sem páginas de sistema nem usuários, schema de Produto. Fora de produção nada é indexado.
- **Segurança:** bloqueio após 5 logins errados, sem XML-RPC, sem expor usuários (inclusive na recuperação de senha), cabeçalhos de segurança, editor de código do painel desligado. O servidor bloqueia arquivos de versão, logs e backups, e PHP em uploads não executa (`config/apache`, `config/php`). Bateria de testes: `./scripts/security-check.sh [url]`. Resultado atual no local: 78 PASS, 0 FAIL.
- **Backup:** `./scripts/backup.sh` e `./scripts/restore.sh` (banco + uploads). Produção: ver [deploy.md](docs/development/deploy.md).
- **Meta Pixel:** usa o mesmo ID do site atual (`2143468076438110`) e manda PageView, ViewContent, AddToCart, InitiateCheckout e Purchase (sem duplicar se o cliente recarregar a página). **Só envia em produção e só depois do consentimento de marketing.** Localmente, com `META_PIXEL_DEBUG=1`, os eventos aparecem no console do navegador quando você aceita os cookies (antes disso, o console avisa que nada é enviado).
- **Consentimento de cookies (LGPD):** banner com Aceitar / Recusar / Preferências (necessários sempre ligados, marketing opcional), escolha guardada por 6 meses no cookie `gilda_consent`. Sem aceite, nada da Meta carrega e os cookies de atribuição do WooCommerce (`sbjs_*`) não são gravados. Se a pessoa aceitar na página de pedido recebido, o Purchase ainda dispara (uma vez). Também publica o consent mode do Google (negado por padrão). Para reabrir as preferências: shortcode `[gilda_consent_preferences]`. Pra testar do zero, apague o cookie `gilda_consent` no navegador.

## Pendências que dependem da GILDA;

A lista completa do que pedir ou conversar com as donas, com prioridade e checklist, está em **[docs/requisitos-cliente.md](docs/requisitos-cliente.md)**. As principais:

| Item | Situação atual | O que falta |
|---|---|---|
| **Pagamento (11.5)** | Gateway de teste (só no ambiente local) | Escolher o gateway com Visa/Master/Pix (ex.: Mercado Pago, Pagar.me, PagBank) e passar as credenciais |
| **Contrato Correios** | Tabela pública (sem contrato) | Se tiver contrato (o app Meu Frete do Wix tem plano com contrato), passar usuário do Meu Correios, código de acesso e cartão de postagem. Também falta aceitar os termos de uso do plugin no painel |
| **Conversions API (Meta)** | Só o pixel no navegador | Token gerado no Gerenciador de Eventos da Meta |
| **Endereço de origem** | Curitiba/PR, CEP 82015-384 (requisito 10.1) | Rua e número de onde saem os envios |
| **Peso/medidas** | Valores provisórios por categoria | Peso e embalagem reais de cada peça (o frete é calculado com isso) |
| **Fotos dos produtos** | Não importadas | Autorizar a importação do Wix (`IMPORT_IMAGES=1`) ou mandar as fotos originais |
| **Textos legais** | Rascunhos em [docs/legal/](docs/legal/) (privacidade, trocas, termos/frete), importados como páginas em rascunho | Revisão das donas/contador e os campos **[PREENCHER]**; depois publicar |

## Estrutura

```
docker-compose.yml          # WordPress, MariaDB, Mailpit, phpMyAdmin, WP-CLI
scripts/
  setup.sh                  # sobe e configura tudo (rodar sempre que precisar)
  setup-store.php           # configurações do WooCommerce (moeda, contas, checkout BR, frete, cupom...)
  import-products.php       # importa data/products.json para o WooCommerce
  import-legal-pages.php    # páginas legais de docs/legal/ (privacidade publicada, resto em rascunho); roda no setup.sh
  import-chapters.php       # semente dos 3 primeiros capítulos (fotos por ID, peças ligadas); roda no setup.sh
  export-wix-products.mjs   # lê a loja Wix e gera data/products.json
data/products.json          # produtos exportados do Wix
wp-content/mu-plugins/      # nosso código que roda sempre
  gilda-smtp.php                  # envio de e-mail via SMTP (local: Mailpit)
  gilda-first-purchase-coupon.php # regra "só na primeira compra"
  gilda-legacy-redirects.php      # 301 das URLs do Wix
  gilda-account-fields.php        # nome + confirmar senha no cadastro (7.2)
  gilda-meta-pixel.php            # Meta Pixel com eventos de e-commerce (só em produção, só com consentimento)
  gilda-consent.php (+ gilda-consent/)  # banner de cookies LGPD, cookie gilda_consent, consent mode
  gilda-buy-now.php               # botão "Comprar" → checkout (5.9)
  gilda-images.php                # versões das fotos geradas em WebP (q82)
  gilda-seo.php                   # meta description, Open Graph, sitemap limpo, noindex fora de produção (11.3)
  gilda-hardening.php             # segurança: limite de login, sem XML-RPC/enumeração de usuários, headers
  gilda-stock-alerts.php (+ pasta) # "avise-me quando chegar": formulário, lista de espera, e-mail de volta ao estoque
  gilda-low-stock.php             # "Restam só N" (últimas unidades)
  gilda-painel.php (+ pasta)      # painel das donas: login da marca, perfil "Dona da loja" com visual GILDA;, Início, Dados da loja, Capítulos, Nova peça (guia: docs/painel-gilda.md)
wp-content/themes/gilda/    # tema da loja (K1venn), ativado pelo setup.sh quando existir style.css
scripts/backup.sh           # backup do banco + uploads em backups/ (retenção de 14)
scripts/restore.sh          # restaura um backup
tests/e2e/                  # testes ponta a ponta (Playwright), com package.json próprio
.github/workflows/ci.yml    # lint, setup, security-check e testes a cada PR
docs/
  requisitos-cliente.md           # o que pedir/conversar com as donas
  painel-gilda.md                 # guia do painel para as donas
  legal/                          # rascunhos: privacidade, trocas e devoluções, termos de uso (revisão das donas)
  development/tasks.md            # backlog (K1venn)
  development/backend-para-frontend.md  # o que o backend oferece pro tema
  development/deploy.md           # roteiro de produção (servidor, wp-config, cron, backup, virada)
  design/                         # design system (K1venn)
```

O core do WordPress, os plugins de terceiros e os uploads ficam num volume Docker, não no git. Os plugins são instalados pelo `setup.sh` com versão fixa. Para atualizar um plugin, mude a versão lá e teste.

Para atualizar produtos e estoque a partir do Wix (antes da virada, por exemplo):

```bash
node scripts/export-wix-products.mjs && IMPORT_PRODUCTS=refresh ./scripts/setup.sh
```

> ⚠️ Sem opção, o import só cria as peças novas do JSON e não mexe nas que já existem. Para puxar preço e estoque atuais do Wix (antes da virada), use `IMPORT_PRODUCTS=refresh`, que **sobrescreve** as peças. Depois que a loja nova estiver no ar, o estoque é controlado só no WooCommerce: nunca use `refresh` em produção.

## Skills do Claude Code

O projeto tem skills em `.claude/skills/` (WordPress oficial, frontend-design, find-skills), com versões fixadas no `skills-lock.json`.

## Equipe

- Requisitos: K1venn
- WordPress: yago3008

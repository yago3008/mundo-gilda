# mundo-gilda

Nova loja da **GILDA;** ([mundogilda.com.br](https://www.mundogilda.com.br)), migrando do Wix para WordPress + WooCommerce.

## Rodando localmente

Requisitos: Docker Desktop e Git Bash (Windows) ou qualquer shell bash (Linux/macOS).

```bash
./scripts/setup.sh
```

Esse comando sobe os containers, instala o WordPress em pt_BR, instala os plugins (versões fixas), configura a loja e importa os produtos. Ele é **idempotente**, então pode rodar de novo sempre que quiser (por exemplo, depois de um `git pull`).

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

## O que já funciona

- **Contas de cliente:** cadastro com nome, e-mail, senha (com medidor de força) e confirmação de senha; login, recuperação de senha por e-mail, histórico de pedidos e endereços. Também dá pra criar a conta no checkout ou comprar sem cadastro.
- **Checkout brasileiro:** CPF validado, CEP primeiro com autopreenchimento de rua/bairro/cidade/UF, número (com opção S/N), bairro e celular com DDD.
- **Produtos:** os 7 produtos do Wix, com variações de cor e tamanho, estoque por variação e "Esgotado".
- **Cupom `primeiracompra`:** 10% e só na primeira compra. A regra confere a conta e também o e-mail, então não dá pra reusar sem login.
- **E-mails transacionais:** conta criada, pedido recebido, novo pedido para a loja e redefinição de senha.
- **URLs:** produtos continuam em `/product-page/<slug>`, como no Wix (requisito 11.3). As outras URLs antigas (`/category/all-products`, `/cart-page`, slugs com acento, etc.) têm redirecionamento 301.
- **Frete (Correios Automático):** PAC e SEDEX com cotação real a partir de Curitiba (CEP 82015-384), mais 2 dias de postagem. Aparece no simulador da página do produto (com "Não sei meu CEP"), no carrinho e no checkout. Também cria a página "Rastrear encomenda". Funciona sem contrato. Se a GILDA; tiver contrato com os Correios, é só ligar "Contrato próprio" no painel do plugin: os métodos continuam os mesmos.
- **Botão "Comprar"** na página do produto: adiciona e vai direto pro checkout (5.9).
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
  import-legal-pages.php    # cria/atualiza as páginas legais (rascunho) a partir de docs/legal/
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
  gilda-seo.php                   # meta description, Open Graph, sitemap limpo, noindex fora de produção (11.3)
  gilda-hardening.php             # segurança: limite de login, sem XML-RPC/enumeração de usuários, headers
wp-content/themes/gilda/    # tema da loja (K1venn), ativado pelo setup.sh quando existir style.css
scripts/backup.sh           # backup do banco + uploads em backups/ (retenção de 14)
scripts/restore.sh          # restaura um backup
docs/
  requisitos-cliente.md           # o que pedir/conversar com as donas
  legal/                          # rascunhos: privacidade, trocas e devoluções, termos de uso (revisão das donas)
  development/tasks.md            # backlog (K1venn)
  development/backend-para-frontend.md  # o que o backend oferece pro tema
  development/deploy.md           # roteiro de produção (servidor, wp-config, cron, backup, virada)
  design/                         # design system (K1venn)
```

O core do WordPress, os plugins de terceiros e os uploads ficam num volume Docker, não no git. Os plugins são instalados pelo `setup.sh` com versão fixa. Para atualizar um plugin, mude a versão lá e teste.

Para atualizar produtos e estoque a partir do Wix (antes da virada, por exemplo):

```bash
node scripts/export-wix-products.mjs && ./scripts/setup.sh
```

> ⚠️ O import **sobrescreve preço e estoque** com os dados do Wix. Ele serve para a migração. Depois que a loja nova estiver no ar, o estoque passa a ser controlado só no WooCommerce: não rode o import em produção.

## Skills do Claude Code

O projeto tem skills em `.claude/skills/` (WordPress oficial, frontend-design, find-skills), com versões fixadas no `skills-lock.json`.

## Equipe

- Requisitos: K1venn
- WordPress: yago3008

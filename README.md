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
- **Meta Pixel:** usa o mesmo ID do site atual (`2143468076438110`) e manda PageView, ViewContent, AddToCart, InitiateCheckout e Purchase (sem duplicar se o cliente recarregar a página). **Só envia em produção.** Localmente, com `META_PIXEL_DEBUG=1`, os eventos aparecem no console do navegador.

## Pendências que dependem da GILDA;

| Item | Situação atual | O que falta |
|---|---|---|
| **Pagamento (11.5)** | Gateway de teste (só no ambiente local) | Escolher o gateway com Visa/Master/Pix (ex.: Mercado Pago, Pagar.me, PagBank) e passar as credenciais |
| **Contrato Correios** | Tabela pública (sem contrato) | Se tiver contrato (o app Meu Frete do Wix tem plano com contrato), passar usuário do Meu Correios, código de acesso e cartão de postagem. Também falta aceitar os termos de uso do plugin no painel |
| **Conversions API (Meta)** | Só o pixel no navegador | Token gerado no Gerenciador de Eventos da Meta |
| **Endereço de origem** | Curitiba/PR, CEP 82015-384 (requisito 10.1) | Rua e número de onde saem os envios |
| **Peso/medidas** | Valores provisórios por categoria | Peso e embalagem reais de cada peça (o frete é calculado com isso) |
| **Fotos dos produtos** | Não importadas | Autorizar a importação do Wix (`IMPORT_IMAGES=1`) ou mandar as fotos originais |
| **Política de privacidade / trocas** | Páginas em rascunho | Textos da GILDA; (exigência da LGPD/CDC) |

## Estrutura

```
docker-compose.yml          # WordPress, MariaDB, Mailpit, phpMyAdmin, WP-CLI
scripts/
  setup.sh                  # sobe e configura tudo (rodar sempre que precisar)
  setup-store.php           # configurações do WooCommerce (moeda, contas, checkout BR, frete, cupom...)
  import-products.php       # importa data/products.json para o WooCommerce
  export-wix-products.mjs   # lê a loja Wix e gera data/products.json
data/products.json          # produtos exportados do Wix
wp-content/mu-plugins/      # nosso código que roda sempre
  gilda-smtp.php                  # envio de e-mail via SMTP (local: Mailpit)
  gilda-first-purchase-coupon.php # regra "só na primeira compra"
  gilda-legacy-redirects.php      # 301 das URLs do Wix
  gilda-account-fields.php        # nome + confirmar senha no cadastro (7.2)
  gilda-meta-pixel.php            # Meta Pixel com eventos de e-commerce (só em produção)
docs/                       # requisitos e design system (K1venn)
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

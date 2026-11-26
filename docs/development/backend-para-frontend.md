# Backend → Front-end: o que já existe e como usar

Para o K1venn, que é o dono do tema, do layout e do visual.

Tudo o que está aqui **já funciona** no ambiente local (`./scripts/setup.sh`). O tema só precisa **mostrar** essas peças do jeito do [design system](../design/README.md). Nada aqui exige mexer em PHP de backend. Se precisar de algum dado ou comportamento novo, abra uma issue com a label `backend`.

---

## 1. Onde fica o tema

- Pasta: **`wp-content/themes/gilda/`**. Já está versionada no git e montada no Docker.
- Tipo recomendado: **tema de blocos** (`theme.json` + `templates/` + `parts/` + `patterns/`). Os tokens de [01-cores](../design/01-cores.md) e [02-tipografia](../design/02-tipografia.md) entram no `theme.json` (no WordPress, é ele que faz o papel do `src/styles/tokens.css` citado no design system).
- Assim que existir `wp-content/themes/gilda/style.css`, o `./scripts/setup.sh` **ativa o tema sozinho**. Sem ele, fica o Twenty Twenty-Five.
- O projeto tem skills do Claude Code para isso em `.claude/skills/`: `wp-block-themes`, `wp-patterns`, `wp-block-development`, `wp-interactivity-api` e `frontend-design`.

## 2. Páginas e URLs

| Página | URL | Observação |
|---|---|---|
| Início | `/` | página "Início" (vazia, o conteúdo vem do template `front-page`) |
| Loja (todos os produtos) | `/loja/` | template `archive-product` |
| Categoria | `/categoria/<slug>/` | `camisas`, `calcas`, `bermudas`, `lencos` |
| Produto | `/product-page/<slug>/` | igual ao Wix (requisito 11.3). Ex.: `camisa-grao`, `calca` (Calça Torra), `calca-asas` |
| Carrinho | `/carrinho/` | bloco de carrinho do WooCommerce |
| Finalizar compra | `/finalizar-compra/` | bloco de checkout do WooCommerce |
| Pedido recebido | `/finalizar-compra/pedido-recebido/<id>/` | |
| Minha conta | `/minha-conta/` | login + cadastro na mesma página |
| ↳ endpoints | `/minha-conta/pedidos/`, `/enderecos/`, `/editar-conta/`, `/recuperar-senha/`, `/sair/` | |
| Rastrear encomenda | `/rastrear-encomenda/` | criada pelo plugin dos Correios (shortcode `[infixs_correios_automatico_tracking_view]`) |
| Quem Cria / Capítulos | `/quem-cria/`, `/capitulos/` | **ainda não existem**; os links antigos do Wix (`/contact`, `/contact-2`) já redirecionam para essas URLs |

Links antigos do Wix (`/product-page/...` com acento, `/category/all-products`, `/cart-page`, `/contact-1`) têm 301 para as URLs novas (`wp-content/mu-plugins/gilda-legacy-redirects.php`).

## 3. Produto (Epic 5)

**Dados disponíveis**
- Atributos globais: **`pa_cor`** e **`pa_tamanho`**. Os tamanhos já vêm na ordem PP→GG e 36→44.
- **Cor da bolinha (swatch):** cada termo de `pa_cor` tem o term meta **`gilda_hex`** (ex.: Marrom → `#b4522d`), vindo do Wix:
  ```php
  get_term_meta( $term->term_id, 'gilda_hex', true ); // "#b4522d"
  ```
- Estoque por variação. Hoje, ao escolher uma combinação esgotada, o WooCommerce mostra "Fora de estoque" e desabilita os botões. O produto todo esgotado mostra "Esgotado".
  - O requisito 5.6 pede **marcar os tamanhos esgotados já no seletor**. Isso é front: os dados de cada variação (atributos e `is_in_stock`) estão no atributo `data-product_variations` do `<form class="variations_form">`.
- Guia de tamanhos: hoje está **dentro da descrição** do produto, como no Wix.
- Fotos: **ainda não importadas** (dependem das donas). Os produtos aparecem com o placeholder do WooCommerce.

**Botões de compra (5.8 e 5.9)**
- "Adicionar ao carrinho": padrão do WooCommerce.
- **"Comprar"**: já existe (`gilda-buy-now.php`). Se o tema registrar `gilda_buy_now_button()` em `woocommerce_after_add_to_cart_button` (como o tema `gilda` faz hoje), o mu-plugin **não desenha** o botão dele, só cuida do redirecionamento, pra não aparecerem dois. O botão do mu-plugin é assim: É um `<button type="submit" name="gilda_buy_now" value="1" class="gilda-buy-now single_add_to_cart_button button alt">` dentro do formulário de compra. Ele valida cor e tamanho como o outro botão e leva direto pro checkout.
  - Pra estilizar: `.gilda-buy-now`. Pela hierarquia do design system, Comprar é sólido e Adicionar é contorno.
  - Pra usar fora do formulário: um link com `?gilda_buy_now=1&add-to-cart=<id_variacao>&quantity=1`.
- Enquanto cor e tamanho não forem escolhidos, os dois botões recebem a classe `disabled wc-variation-selection-needed` (pra estilizar o estado desabilitado).

**Simulador de frete (5.10)**
- Já aparece na página do produto, antes da descrição, via plugin "Correios Automático" (classe `.infixs-correios-automatico-calculator`). Inclui o "Não sei meu CEP" e a máscara de CEP.
- Pra posicionar em outro lugar do template: shortcode **`[infixs_correios_automatico_calculator]`**. A posição automática e as cores também podem ser mudadas no painel: WooCommerce → Correios Automático → Configurações.
- Resultado: PAC e SEDEX com preço e prazo.

## 4. Loja e filtros (Epic 4)

Os filtros funcionam **por parâmetro na URL**. Os blocos de filtro do WooCommerce ("Product Filters") já leem e escrevem esses parâmetros:

| Filtro | Parâmetro | Exemplo |
|---|---|---|
| Categoria | URL da categoria | `/categoria/camisas/` |
| Cor | `filter_cor` | `/loja/?filter_cor=marrom` |
| Várias cores (OU) | `filter_cor` + `query_type_cor=or` | `/loja/?filter_cor=preto,verde-musgo&query_type_cor=or` |
| Tamanho | `filter_tamanho` | `/loja/?filter_tamanho=gg` |
| Preço | `min_price` / `max_price` | `/loja/?min_price=100&max_price=450` |
| Ordenação | `orderby` | `price`, `price-desc`, `date` |

- A tabela de atributos do WooCommerce (usada para contagens dos filtros) é gerada no import.
- Slugs das cores: `off-white`, `marrom`, `laranja`, `branco`, `azul-claro`, `preto`, `verde-musgo`.
- Faixa de preço atual: R$ 97 a R$ 569, vinda dos produtos.
- O `robots.txt` de produção bloqueia URLs com filtro (pra não gerar páginas duplicadas no Google).

## 5. Carrinho, checkout e conta (Epics 6 e 7)

- **Carrinho e checkout** usam os **blocos** do WooCommerce. O que já está configurado e não precisa ser refeito:
  - Campos brasileiros: CPF (com máscara e validação), CEP primeiro com preenchimento automático do endereço, número com opção S/N, bairro e celular com DDD.
  - Cupom: campo "Adicionar cupons". O `primeiracompra` vale 10% só na primeira compra.
  - Frete: PAC e SEDEX.
  - Pagamento: hoje só o "Pagamento de teste (ambiente local)".
- **Contador do carrinho no header (1.5):** use o bloco **Mini-cart** do WooCommerce, que já atualiza em tempo real.
- **Carrinho vazio (6.3):** o texto da marca entra no template/bloco "Empty cart" do bloco de carrinho.
- **Login e cadastro (7.1 e 7.2):** formulário em `/minha-conta/`. O cadastro tem os campos **Nome** (`#reg_gilda_full_name`), e-mail, senha (com medidor de força do WooCommerce) e **Confirmar senha** (`#reg_gilda_password2`). Os erros aparecem como avisos do WooCommerce (`.woocommerce-error`).
- **Recuperar senha (7.3):** `/minha-conta/recuperar-senha/`. O e-mail chega com o link de redefinição.
- **Ícone de login no header (1.4):** link para `/minha-conta/`. Pra saber se a pessoa está logada no template, use a classe `logged-in` do `<body>`.

## 6. O que o tema NÃO deve duplicar

- **SEO:** a meta description, as tags Open Graph/Twitter e o noindex já são gerados por `gilda-seo.php`. O schema de Produto, Oferta e Breadcrumb vem do WooCommerce. O tema só precisa declarar `title-tag` (temas de blocos já fazem isso) e usar a hierarquia certa de headings (H1 único por página).
- **Meta Pixel:** `gilda-meta-pixel.php` já dispara PageView, ViewContent, AddToCart, InitiateCheckout e Purchase. O tema não deve incluir o pixel de novo.
- **Cabeçalhos de segurança:** já existem. Se o tema carregar fontes ou scripts de outro domínio, tudo bem: não há Content Security Policy restritiva.

## 6.1 Imagens novas para o tema

- **`story_banner`** (em `data/site-images.json`): foto escolhida pelo Yago pra substituir o "túnel de livros" do Wix no banner **"DEPOIS DO PONTO E VÍRGULA, VOCÊ CONTA A HISTÓRIA"**. No site atual ele aparece no fim do Quem Cria e no carrinho vazio; no tema esses dois lugares hoje só têm texto.
  - É uma parede de livros e papéis antigos abertos, em tons creme. Foto de Jimmy Liu, licença Unsplash (uso comercial livre).
  - Entra na biblioteca de mídia junto com as outras pelo `scripts/import-site-images.php` (`IMPORT_IMAGES=1 ./scripts/setup.sh`). No tema: `gilda_image( 'story_banner' )`.
  - Onde e como usar (faixa terracota, texto por cima, corte) é decisão de design, ou seja, sua.

## 7. E-mails da loja

- Os e-mails do WooCommerce (pedido, conta, senha) usam o template padrão. Cores, logo e rodapé se configuram em **WooCommerce → Configurações → E-mails**. Também dá pra sobrescrever os templates no tema, em `gilda/woocommerce/emails/`.
- Localmente, todo e-mail cai no **Mailpit**: http://localhost:8026.

## 8. Dados de teste

- Usuário admin: `admin`, com a senha no `.env` (`WP_ADMIN_PASSWORD`).
- Clientes de teste: credenciais `TEST_CUSTOMER_*` no `.env`.
- Pra testar o checkout: CEP `01310-100`, CPF de teste `529.982.247-25` e o "Pagamento de teste".
- Pra recomeçar do zero: `docker compose down -v && ./scripts/setup.sh`.

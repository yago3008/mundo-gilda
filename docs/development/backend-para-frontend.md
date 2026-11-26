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

## 3.1 Avise-me e últimas unidades

Dois mu-plugins: `gilda-stock-alerts.php` (código em `gilda-stock-alerts/`) e `gilda-low-stock.php`. Os dois já funcionam e entregam marcação **sem estilo nenhum**: o visual é seu.

### "Avise-me quando chegar"

Quando a cor/tamanho escolhida (ou o produto simples) está esgotada, aparece um formulário de e-mail. Quando o estoque volta, cada pessoa recebe **um** e-mail com o link da peça (já com cor e tamanho selecionados) e sai da lista.

**Onde aparece**
- **Produto variável:** dentro do `form.variations_form`, em `.single_variation_wrap`, **entre** a disponibilidade (`.woocommerce-variation`) e o botão de compra (`.woocommerce-variation-add-to-cart`). Começa com `hidden`. O JS do plugin tira o `hidden` no `found_variation` quando `is_in_stock` é falso e põe de volta no `reset_data` / `hide_variation` / variação disponível. O botão "Adicionar ao carrinho" continua lá, desabilitado pelo WooCommerce (classe `disabled wc-variation-is-unavailable`); se quiser escondê-lo enquanto o avise-me aparece, use `.single_variation_wrap:has(.gilda-notify:not([hidden])) .woocommerce-variation-add-to-cart`.
  - Formulário dentro de formulário não é HTML válido. Por isso os campos ficam no variations_form, mas apontam (atributo `form="gilda-notify-form-<id>"`) para um `<form id="gilda-notify-form-<id>" class="gilda-notify__form">` **vazio** impresso logo depois do variations_form. Não mova os campos para fora sem manter esse atributo.
- **Produto simples esgotado:** o `<form class="gilda-notify__form">` com o bloco dentro, já visível, logo depois do bloco "Adicionar ao carrinho" (onde o tema mostra o botão "Esgotado").
- Pra colocar em outro lugar: `echo gilda_stock_alert_form( $product );` (imprime só uma vez por produto).

**Marcação**
```html
<div class="gilda-notify gilda-notify--variation" id="gilda-notify-33" data-product-id="33" data-form-id="gilda-notify-form-33" hidden>
  <p class="gilda-notify__title">Esgotado. Quer saber quando voltar?</p>
  <p class="gilda-notify__text">Deixe seu e-mail e avisamos assim que <span class="gilda-notify__variation">Marrom / M</span> chegar.</p>
  <div class="gilda-notify__fields">
    <label class="gilda-notify__label" for="gilda-notify-email-33">Seu e-mail</label>
    <input class="gilda-notify__email" type="email" name="email" required …>
    <label class="gilda-notify__consent"><input class="gilda-notify__consent-input" type="checkbox" required …> <span>Quero receber um único e-mail…</span></label>
    <span class="gilda-notify__hp" aria-hidden="true" style="position:absolute;left:-10000px…">…</span> <!-- anti-robô: não mostre -->
    <button type="submit" class="gilda-notify__submit button wp-element-button">Avise-me</button>
  </div>
  <p class="gilda-notify__message" role="status" aria-live="polite" hidden></p>
</div>
```

| Classe / estado | Quando |
|---|---|
| `.gilda-notify--variation` / `.gilda-notify--simple` | tipo de produto |
| `[hidden]` | variação disponível ou nenhuma escolhida |
| `.gilda-notify__variation` | texto da combinação escolhida ("Marrom / M"); "este item" no simples |
| `.is-loading` (no `.gilda-notify`) + `aria-busy` no botão | enviando |
| `.is-done` (no `.gilda-notify`) | inscrição feita (dá pra esconder `.gilda-notify__fields`) |
| `.gilda-notify__message.is-success` / `.is-error` | resposta do servidor (texto já pronto, em português) |

Eventos JS disparados no `.gilda-notify` (borbulham): `gilda:notify-show` (`detail.variation`), `gilda:notify-hide`, `gilda:notify-success` e `gilda:notify-error` (`detail.code`, `detail.message`).

Dado nas variações (`data-product_variations` e `found_variation`): `gilda_stock_alert: true` quando aquela variação aceita aviso (está esgotada). Serve, por exemplo, pra marcar o tamanho esgotado com um sininho no seletor (5.6).

Sem JavaScript o formulário do produto simples também funciona: posta em `admin-post.php` e volta pra página com `?gilda_stock_alert=<código>#gilda-notify-<id>`, e a mensagem já vem renderizada.

**Regras (backend):** nonce, campo-isca anti-robô, consentimento obrigatório, até 10 tentativas por hora por IP, até 20 avisos pendentes por e-mail, só aceita item que esteja de fato esgotado e o mesmo e-mail na mesma variação não duplica (a resposta é "ok" do mesmo jeito, pra não revelar quem já está na lista).

**E-mail:** WooCommerce → Configurações → E-mails → **"Avise-me: voltou ao estoque"** (ligar/desligar, assunto, título, texto adicional, HTML/texto). Usa o cabeçalho/rodapé/cores dos outros e-mails da loja. Template sobrescrevível no tema: `gilda/woocommerce/emails/gilda-back-in-stock.php` (e `plain/gilda-back-in-stock.php`); as variáveis estão documentadas no topo do template original em `wp-content/mu-plugins/gilda-stock-alerts/templates/emails/`.

**Cancelamento (LGPD):** o e-mail traz um link "Apagar meu e-mail desta lista". Ele abre uma página de confirmação (`wp_die`, sem o tema) e o botão apaga o registro. Pedir confirmação evita que antivírus de e-mail, que "clicam" nos links, cancelem sozinhos. Também há exportação e exclusão pelas ferramentas de privacidade do WordPress (Ferramentas → Exportar/Apagar dados pessoais). Limpeza automática diária: avisos enviados somem depois de 30 dias e pedidos nunca atendidos depois de 180.

**Pras donas:** **WooCommerce → Avise-me** mostra quantas pessoas esperam cada produto/cor/tamanho, o estoque atual e o pedido mais antigo. Clicando no produto aparecem os e-mails, com "Apagar" e, se o item já voltou, "Enviar avisos agora".

**Hooks (PHP)**
| Hook | Tipo | Uso |
|---|---|---|
| `gilda_stock_alerts_supports( bool, $product )` | filtro | desligar o avise-me em algum produto |
| `gilda_stock_alerts_rate_limit( 10 )` | filtro | tentativas por hora por IP |
| `gilda_stock_alerts_retention_sent_days( 30 )` / `gilda_stock_alerts_retention_pending_days( 180 )` | filtro | prazos da limpeza |
| `gilda_stock_alert_subscribed( $id, $product_id, $variation_id, $email )` | ação | depois de uma inscrição |
| `gilda_stock_alert_cancelled( $alert )` | ação | depois de um cancelamento pelo link |
| `gilda_stock_alerts_batch_sent( $id, $sent )` | ação | depois de cada lote de e-mails |

**Como funciona o envio:** quando o status de estoque de um produto simples ou variação muda para "em estoque" (`woocommerce_product_set_stock_status` / `woocommerce_variation_set_stock_status`), o plugin agenda uma tarefa no Action Scheduler (`gilda_stock_alerts_send`, grupo `gilda`). Ela confere se ainda está em estoque e manda em lotes de 50. Assim, salvar o produto no admin não fica lento. Em produção o Action Scheduler roda pelo cron do servidor a cada 5 minutos ([deploy.md](deploy.md), seção 5), então o e-mail sai em até ~5 minutos depois da reposição. Localmente dá pra forçar com `docker compose run --rm wpcli wp action-scheduler run --hooks=gilda_stock_alerts_send`.

### "Restam só N"

Quando o estoque de um produto simples ou da variação escolhida está **entre 1 e o limite** (padrão **2**; WooCommerce → Configurações → Produtos → Inventário → "Últimas unidades (vitrine)"; 0 desliga), o "Em estoque" do WooCommerce vira:

```html
<p class="stock in-stock gilda-low-stock" data-gilda-low-stock="1">Resta só 1</p>
```

- **Produto variável:** vem no `availability_html` da variação, que o WooCommerce já coloca em `.woocommerce-variation-availability` quando a cor/tamanho é escolhida. Nada a fazer além do CSS de `.gilda-low-stock`.
- **Produto simples:** no lugar do aviso de estoque, dentro do formulário de compra.
- **Dado:** `gilda_low_stock` (número ou `null`) em cada variação do `data-product_variations` / `found_variation`; na Store API, `extensions.gilda.low_stock` em `/wp-json/wc/store/v1/products/<id>` (útil para cards da loja). Em PHP: `gilda_low_stock_qty( $product )` (int|null) e `gilda_low_stock_html( $product )`.
- Texto alterável pelo filtro `gilda_low_stock_text( $text, $qty, $product )`; limite também pelo filtro `gilda_low_stock_threshold`.

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

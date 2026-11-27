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

**Marcação** (o plugin já traz o estilo em `gilda-stock-alerts/assets/stock-alerts.css`, com as cores da paleta)

- **Logado** (`.gilda-notify--member`): um botão só, que manda o aviso para o e-mail da conta, e a nota "Vamos avisar em …".
- **Sem login** (`.gilda-notify--guest`): o botão abre um `<dialog class="gilda-notify-modal">` marrom que só pede o e-mail.
- O consentimento é o próprio pedido: o texto diz que o e-mail serve só para este aviso e linka a política; vai `consent=1` escondido.

```html
<div class="gilda-notify gilda-notify--variation gilda-notify--guest" id="gilda-notify-33" data-product-id="33" data-form-id="gilda-notify-form-33" hidden>
  <p class="gilda-notify__title">Esgotado. <span class="gilda-notify__text">Quer saber quando <span class="gilda-notify__variation">Marrom / M</span> voltar?</span></p>
  <span class="gilda-notify__hp" aria-hidden="true" …>…</span> <!-- anti-robô: não mostre -->
  <input type="hidden" name="action|product_id|variation_id|consent|_gilda_nonce" …>
  <!-- logado: <input type="hidden" name="email"> + <button type="submit" class="gilda-notify__submit gilda-notify__open"> + <p class="gilda-notify__note"> -->
  <button type="button" class="gilda-notify__open" aria-haspopup="dialog">Avise-me quando chegar</button>
  <dialog class="gilda-notify-modal">
    <button class="gilda-notify-modal__close" data-gilda-notify-close>×</button>
    <p class="gilda-notify-modal__title">Avise-me</p>
    <p class="gilda-notify-modal__text">Deixe seu e-mail e avisamos quando <span class="gilda-notify__variation">Marrom / M</span> voltar.</p>
    <div class="gilda-notify-modal__row"><input class="gilda-notify__email" type="email" name="email" required …><button type="submit" class="gilda-notify__submit">Avisar</button></div>
    <p class="gilda-notify__message" role="status" hidden></p> <!-- erro fica aqui, no modal -->
    <p class="gilda-notify-modal__privacy">Usamos seu e-mail só para este aviso (política de privacidade).</p>
  </dialog>
  <p class="gilda-notify__message gilda-notify__message--inline" role="status" hidden></p> <!-- "Pronto!" -->
</div>
```

| Classe / estado | Quando |
|---|---|
| `.gilda-notify--variation` / `.gilda-notify--simple` | tipo de produto |
| `.gilda-notify--member` / `.gilda-notify--guest` | logado (envia direto) / sem login (abre o modal) |
| `[hidden]` | variação disponível ou nenhuma escolhida |
| `.gilda-notify__variation` | texto da combinação escolhida ("Marrom / M"); "este item" no simples |
| `.is-loading` (no `.gilda-notify`) + `aria-busy` no botão | enviando |
| `.is-done` (no `.gilda-notify`) | inscrição feita (o CSS esconde o botão e deixa só a confirmação) |
| `.gilda-notify__message.is-success` / `.is-error` | resposta do servidor (texto já pronto, em português) |

Eventos JS disparados no `.gilda-notify` (borbulham): `gilda:notify-show` (`detail.variation`), `gilda:notify-hide`, `gilda:notify-success` e `gilda:notify-error` (`detail.code`, `detail.message`).

Dado nas variações (`data-product_variations` e `found_variation`): `gilda_stock_alert: true` quando aquela variação aceita aviso (está esgotada). Serve, por exemplo, pra marcar o tamanho esgotado com um sininho no seletor (5.6).

Sem JavaScript, logado, o produto simples também funciona: posta em `admin-post.php` e volta pra página com `?gilda_stock_alert=<código>#gilda-notify-<id>`, e a mensagem já vem renderizada. Sem login o modal precisa de JavaScript.

**Regras (backend):** nonce, campo-isca anti-robô, consentimento (enviado pelo próprio botão), até 10 tentativas por hora por IP, até 20 avisos pendentes por e-mail, só aceita item que esteja de fato esgotado e o mesmo e-mail na mesma variação não duplica (a resposta é "ok" do mesmo jeito, pra não revelar quem já está na lista).

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
- **Meta Pixel:** `gilda-meta-pixel.php` já dispara PageView, ViewContent, AddToCart, InitiateCheckout e Purchase, **só depois do consentimento de marketing** (seção 6.2). O tema não deve incluir o pixel de novo, nem nenhum outro script de rastreamento sem passar pelo `gildaConsent`.
- **Banner de cookies:** já existe (seção 6.2). O tema só estiliza e coloca o link de reabrir no rodapé.
- **Fotos por cor:** `gilda-color-gallery.php` guarda as fotos de cada cor na galeria das variações (recurso do WooCommerce 11). Ao escolher a cor, `color-gallery.js` troca a galeria inteira pela daquela cor (o WooCommerce sozinho só troca com cor + tamanho). A troca recria o `.woocommerce-product-gallery` (eventos `wc-product-gallery-before-destroy` / `wc-product-gallery-after-init`): se o tema mexer na galeria, refaça no `after-init`. As donas escolhem as fotos na caixa "Fotos por cor" da edição da peça; a carga inicial vem de `data/product-color-images.json`.
- **Cache do catálogo:** `gilda-catalog-cache.php` guarda por 3 dias o HTML da loja e dos filtros (`?tipo=`, `?capitulo=`, cor, tamanho) para quem está sem login e com o carrinho vazio, então o catálogo abre na hora depois do zoom dos cards. Ele limpa sozinho quando produto, estoque ou categoria muda, ou quando `gilda.js`/`theme.css` mudam. Para conferir, o cabeçalho é `X-Gilda-Cache: HIT/MISS`; para limpar à mão, `wp eval 'gilda_catalog_cache_flush();'`; para desligar, `GILDA_CATALOG_CACHE=0`. Não coloque nada pessoal (nome, carrinho) no HTML do catálogo: o mesmo HTML vai para todos os visitantes sem login.
- **Cabeçalhos de segurança:** já existem. Se o tema carregar fontes ou scripts de outro domínio, tudo bem: não há Content Security Policy restritiva.

## 6.2 Banner de cookies (LGPD)

`wp-content/mu-plugins/gilda-consent.php` desenha o banner e o painel de preferências. O backend cuida da lógica (cookie, pixel, consent mode); **o visual é seu**. Já vem com um CSS neutro mínimo só pra funcionar.

**Marcação** (sai logo no início do `<body>`, via `wp_body_open`):

| Classe | O que é |
|---|---|
| `.gilda-consent` | o banner (fixo no rodapé da tela, `role="dialog"`, `aria-modal="false"`). Fica com `hidden` depois que a pessoa escolhe |
| `.gilda-consent__inner`, `__body`, `__title`, `__text`, `__link` | conteúdo do banner (`__link` = política de privacidade, só aparece quando a página estiver publicada) |
| `.gilda-consent__actions` | grupo de botões (no banner e no painel) |
| `.gilda-consent__button` + `--accept` / `--reject` / `--preferences` / `--save` | botões. A ANPD recomenda **Aceitar e Recusar com o mesmo peso visual**: não destaque só o Aceitar |
| `.gilda-consent__prefs` | o painel de preferências: um `<dialog>` nativo aberto com `showModal()` (prende o foco, fecha no Esc, fundo em `::backdrop`) |
| `.gilda-consent__prefs-header`, `__close` | topo do painel e o botão fechar (×) |
| `.gilda-consent__categories`, `__category` (+ `--necessary` / `--marketing`), `__checkbox`, `__label`, `__badge`, `__category-desc` | lista de categorias. "Necessários" vem marcado e desabilitado |
| `.gilda-consent__sr` | texto só para leitor de tela (essa regra sempre é impressa) |
| `.gilda-consent-open` | botão que reabre as preferências (shortcode abaixo). Vem com cara de link |
| `.gilda-consent__reopen` | `<p>` com o link automático no fim da página (ver abaixo) |

**Customizar o visual**
- Sobrescreva as classes no CSS do tema, ou só as variáveis: `--gilda-consent-bg`, `--gilda-consent-fg`, `--gilda-consent-border`, `--gilda-consent-font` (definidas em `.gilda-consent` e `.gilda-consent__prefs`).
- Pra jogar fora o CSS padrão inteiro: `add_filter( 'gilda_consent_default_css', '__return_false' );` (as regras de esconder e de leitor de tela continuam).
- Os textos podem ser trocados pelo filtro `gilda_consent_texts` (array com `title`, `text`, `accept`, `reject`, `preferences`, `prefs_title`...).

**Reabrir as preferências (obrigatório ter no site)**
- Shortcode **`[gilda_consent_preferences]`** (ou `[gilda_consent_preferences label="Cookies"]`). Sugestão: no rodapé, ao lado do CNPJ ou do "© GILDA;", num bloco Shortcode em `parts/footer.html`.
- Qualquer elemento com o atributo `data-gilda-consent-open`, ou um link `href="#gilda-consent"`, também abre o painel. Em JS: `gildaConsent.open()`.
- Enquanto o tema não usar o shortcode, o mu-plugin coloca um link simples "Preferências de cookies" (`.gilda-consent__reopen`) no fim da página. Quando o rodapé tiver o shortcode, esse link some sozinho. Pra desligar de vez: `add_filter( 'gilda_consent_auto_reopen_link', '__return_false' );`.

**API em JS** (pra quem precisar, ex.: um embed do Instagram que só carregue com consentimento):
- `gildaConsent.has('marketing')`, `gildaConsent.get()` (escolha salva ou `null`), `gildaConsent.whenGranted('marketing', fn)`, `gildaConsent.onChange(fn)`.
- Evento `gilda:consent` no `document` a cada escolha (`event.detail.consent`).
- Cookie: `gilda_consent` (6 meses). No PHP: `gilda_consent_has( 'marketing' )`.

## 6.1 Imagens novas para o tema

- **`story_banner`** (em `data/site-images.json`): foto escolhida pelo Yago pra substituir o "túnel de livros" do Wix no banner **"DEPOIS DO PONTO E VÍRGULA, VOCÊ CONTA A HISTÓRIA"**. No site atual ele aparece no fim do Quem Cria e no carrinho vazio; no tema esses dois lugares hoje só têm texto.
  - É uma parede de livros e papéis antigos abertos, em tons creme. Foto de Jimmy Liu, licença Unsplash (uso comercial livre).
  - Entra na biblioteca de mídia junto com as outras pelo `scripts/import-site-images.php` (`SITE_IMAGES=1 ./scripts/setup.sh`). No tema: `gilda_image( 'story_banner' )`.
  - Onde e como usar (faixa terracota, texto por cima, corte) é decisão de design, ou seja, sua.

## 7. E-mails da loja

- Os e-mails do WooCommerce (pedido, conta, senha) usam o template padrão. Cores, logo e rodapé se configuram em **WooCommerce → Configurações → E-mails**. Também dá pra sobrescrever os templates no tema, em `gilda/woocommerce/emails/`.
- Localmente, todo e-mail cai no **Mailpit**: http://localhost:8026.

## 8. Dados de teste

- Usuário admin: `admin`, com a senha no `.env` (`WP_ADMIN_PASSWORD`).
- Clientes de teste: credenciais `TEST_CUSTOMER_*` no `.env`.
- Pra testar o checkout: CEP `01310-100`, CPF de teste `529.982.247-25` e o "Pagamento de teste".
- Pra recomeçar do zero: `docker compose down -v && ./scripts/setup.sh`.

## 9. Transições de scroll (já no tema)

O Yago aprovou e elas estão no tema `gilda`. O protótipo original continua em `prototypes/zoom-mask/`, como referência.

**Onde estão**
- `inc/transitions.php`: shortcodes e a lógica PHP.
- `assets/css/transitions.css` e `assets/js/transitions.js`: carregados só nas páginas `capitulos` e `quem-cria`, sem bibliotecas (CSS `sticky` mais `requestAnimationFrame`).
- `inc/book-chapters.php`: `gilda_book_chapters()`. Os textos, os links e as fotos dos capítulos saíram do `patterns/book.php` e são usados por ele e pela história contínua. **Pra mudar texto ou foto de capítulo, edite aqui.**

**Capítulos (`templates/page-capitulos.html` → `[gilda_book_story]`)**
- Pra cada capítulo, nessa ordem:
  1. colagem de 7 fotos (`zoom`);
  2. a primeira foto cresce até a tela cheia e aparece "CAPÍTULO N;" com o nome do capítulo;
  3. narrativa com a foto fixa e os parágrafos entrando conforme a rolagem, trocando a foto (`story`) e com barra de progresso;
  4. os links "peças disponíveis: …".
- No fim vem o pattern `gilda/book` ("LIVRO GILDA;").
- **Leitura automática:** a página rola sozinha (zoom a 190, texto a 75 e o resto a 130 px/s). Pausa quando a pessoa rola e volta depois de 4 s parada. Tem o botão `.gilda-autoscroll` (Pausar/Continuar). Não liga se a pessoa chegou por um link com `#âncora`, se já rolou a página ou se o sistema pede menos movimento.

**Quem Cria (`patterns/about.php` → `[gilda_mask_transition]`)**
- "GILDA;" vazado sobre a foto `story_banner`; a rolagem mergulha no ";" e termina no banner "DEPOIS DO PONTO E VÍRGULA, VOCÊ CONTA A HISTÓRIA".
- Se a foto não existir, volta pro banner fixo antigo.

**Home: cards de capítulos (`initChapterShowcase` em `gilda.js`)**
- Trocam um card por vez (1, 2, 3…, a cada 2,6 s), com crossfade de 1,2 s.
- A foto nova é carregada antes e entra por cima, então o fundo nunca aparece.
- Pausa fora da tela.

**Acessibilidade e testes**
- Com `prefers-reduced-motion`, tudo aparece parado, no estado final.
- `?anima=1` no endereço força a animação mesmo assim (só pra testar).

**Fotos**
- As fotos principais de cada capítulo foram escolhidas pela resolução. As do Capítulo 3 que vieram do Wix têm só 1023 px ou estão muito comprimidas. Por isso a principal dele é a `chapter_3_hd` (fotos de produto Camisa Grão e Calça Torra, em alta).
- O mu-plugin `gilda-images.php` gera as versões das fotos em **WebP** (qualidade 82). Um PNG de 4,8 MB vira cerca de 170 KB.

**No seu ambiente, depois do `git pull`** (uma vez só):

```bash
SITE_IMAGES=1 ./scripts/setup.sh                          # importa chapter_3_hd (e o que faltar)
docker compose run --rm wpcli wp media regenerate --yes   # refaz as versões das fotos em WebP
```

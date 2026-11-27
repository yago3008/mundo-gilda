# Painel GILDA; — mapa do conteúdo e plano

Objetivo: as donas (leigas em WordPress) conseguem **adicionar, editar e remover** tudo o que muda na loja (capítulos, peças, categorias, cores, cupons, faixa de avisos, banners, textos e contatos) e cada mudança aparece sozinha em todos os lugares onde aquele item é usado, inclusive nas animações.

Este documento é o levantamento do que existe hoje (com `arquivo:linha`), do que precisa existir e das decisões a tomar antes de construir. Levantado em 26/11/2026 sobre a `main` (`87ee187`).

Legenda de "hoje":
- **Dinâmico**: a dona já consegue mexer pelo painel e o site acompanha.
- **Parcial**: aparece sozinho em alguns lugares, em outros não.
- **Fixo**: está escrito no código (PHP, HTML do tema ou JSON do repositório). Só um programador muda.

---

## 0. Antes de tudo: o setup apaga o que as donas editarem

Hoje o `./scripts/setup.sh` (rodado "sempre que quiser", inclusive depois de `git pull`) **reescreve conteúdo**:

| Script | O que sobrescreve |
|---|---|
| `scripts/import-products.php` (setup.sh:57-62) | Nome, descrição, categorias, **peso/medidas provisórios**, atributos, preço, estoque de toda peça cujo slug está em `data/products.json`. **Apaga variações** que não estão no JSON (159-161). |
| `scripts/setup-store.php` | Endereço da loja, remetente dos e-mails, avisos de privacidade, **cupom `primeiracompra` (volta para 10%)**, dados do remetente dos Correios. Sem `.env`, cai em **São Paulo / 01001-000** (20-25), contradizendo Curitiba em 257. |
| `scripts/import-site-images.php` | Regrava a opção `gilda_site_images` inteira (144) e os textos alternativos das fotos (49, 74). |
| `scripts/import-legal-pages.php` | Regrava a página da política de privacidade (`status: publicar`) a cada execução. |

**Feito (26/11/2026):** os scripts viraram semente (ver README, "Rodando localmente"). **Regra:** o git guarda **código e a carga inicial**; o banco guarda **o conteúdo**. Os scripts de carga só preenchem o que está vazio ("semente uma vez") e nunca rodam em produção depois da virada. Sem isso, qualquer painel perde o que as donas fizerem no próximo setup. Esta é a primeira coisa a mudar.

---

## 1. Capítulos — hoje **fixo**

### Onde aparecem
Hoje existem **três listas de capítulos separadas**, que não conversam entre si:

| # | Lista | Arquivo | Usada em |
|---|---|---|---|
| A | `gilda_book_chapters()` | `T/inc/book-chapters.php:14-78` | Página Capítulos: abertura com colagem + zoom (`[gilda_book_story]`, `T/inc/transitions.php:170-225`), narrativa com fotos trocando, leitura automática (`T/assets/js/transitions.js:144-210`) e o "LIVRO GILDA;" (`T/patterns/book.php`) com galeria, botões e textos |
| B | `$gilda_featured_chapters` | `T/patterns/shop-section.php:13-17` | Home: 3 cards "CAPÍTULOS" com fotos trocando (`gilda.js:1178-1256`) que levam ao filtro da loja |
| C | `gilda_chapter_product_slugs()` | `T/inc/pages.php:105-111` | Loja: filtro "Capítulo" (`T/inc/filters.php:58-70` e 166-181) |

(`T/` = `wp-content/themes/gilda/`.)

### Se hoje alguém acrescentasse um capítulo 4 só na lista A
- **Apareceria:** abertura + narrativa na página Capítulos, bloco no Livro GILDA; com âncora `#capitulo-4`, leitura automática.
- **Não apareceria:** cards da home (lista B; e a grade é fixa em 3 colunas, `theme.css:1140`), filtro da loja (lista C; `?capitulo=capitulo-4` mostra loja vazia), cache do catálogo (não é limpo quando capítulo muda, `MU/gilda-catalog-cache.php:133-162`; a chave nem considera `pages.php`).
- **Quebraria:** o rótulo do filtro usa o último caractere do slug (`filters.php:173`) → "Capítulo 10" vira "Capítulo 0". O título "CAPÍTULO N" é escrito à mão e o número do "Capítulo N ·" da narrativa vem da posição (`transitions.php:218`): reordenar desencontra os dois. Sem `links`, o `book.php:49` dá erro.

### Ao adicionar um capítulo, ele precisa aparecer em
1. Página **Capítulos**: abertura (colagem de até 7 fotos que dá zoom na principal + "CAPÍTULO N;" + nome), narrativa (um parágrafo por passo, fotos trocando), leitura automática.
2. **Livro GILDA;** (mesma página, abaixo): título vertical, galeria, botões das peças, textos (o lookbook do capítulo 3 foi removido, decisão §16).
3. **Home**: card do capítulo com fotos trocando (os **5 mais recentes**, decisão §16).
4. **Loja**: opção no filtro "Capítulo" com as peças do capítulo.
5. **Página da peça** (novo, sugestão): "Esta peça é do Capítulo 3 — Ritual Brasileiro".
6. Faixa de avisos ("Novas peças a cada capítulo") e menus continuam genéricos, sem mudança.

### Campos de um capítulo
| Campo | Tipo | Usado em | Observação |
|---|---|---|---|
| Número / ordem | número | todos | Gera "CAPÍTULO N" e a âncora `capitulo-N`. Não digitar à mão. |
| Nome | texto curto | abertura, narrativa, card da home, filtro | Ex.: "Ritual Brasileiro" |
| Textos | lista de parágrafos (itálico permitido) | narrativa (cada parágrafo é um passo e aumenta a altura da seção), Livro | |
| Peças do capítulo | campo "Capítulo" **na peça** (uma peça → no máximo um capítulo, decisão §16) | botões, filtro da loja, página da peça | Hoje é por **slug** e com rótulo escrito à mão ("peças disponíveis: Camisa Casulo"): renomear a peça quebra. Passa a ser por ID e o rótulo vem do nome da peça. |
| Foto principal | 1 imagem | abertura (tela cheia) | **Alta resolução** (≥ 2560 px no lado maior; originais 4000×6000). Recortada para paisagem no desktop, foco em `center 30%`. |
| Fotos da abertura | **exatamente 6** (+ a principal = 7, decisão §16) | colagem do zoom | Desenho fixo de 7 posições (`transitions.css:11-16`, `transitions.php:53,58`). O formulário exige as 7. ≥ 1536 px. |
| Fotos da narrativa | lista | narrativa | Útil ter ≤ número de parágrafos; fotos a mais nunca aparecem (`transitions.js:34-59`). |
| Galeria | lista | Livro GILDA; (recorte 3:4) e card da home | ≥ 768 px, retrato funciona melhor. |
| Texto alternativo | por foto | acessibilidade | Hoje é um por grupo. |

**Animações:** o JavaScript já é genérico (qualquer quantidade de capítulos e de parágrafos). O que precisa mudar é a **origem dos dados** (as três listas viram uma só, no banco) e os limites acima (7 posições da colagem, grade de 3 cards, rótulo de 1 dígito, número digitado, lookbook fixo, cache). As fotos passam a ser guardadas por **ID**, não por posição (`chapter_3:7` muda de sentido quando uma foto some, `T/inc/images.php:21-32`).

**Proposta:** tipo de conteúdo **Capítulo** (`gilda_capitulo`, num mu-plugin), tela de edição em português com seletores de fotos e arrastar para ordenar, e as listas A/B/C lendo dele. Um script migra os 3 capítulos de hoje (a página deve ficar igual depois).

**Feito (fase 2):** `MU/gilda-painel/chapters.php` (tipo de conteúdo e leitura `gilda_chapters()`), `chapters-admin.php` (tela de edição, campo "Capítulo" na peça) e `scripts/import-chapters.php` (semente dos 3 capítulos, chamada pelo `setup.sh`). As listas A/B/C leem de `gilda_book_chapters()` (`T/inc/book-chapters.php`); a página da peça mostra o capítulo (`[gilda_product_chapter]`). Guia das donas: `docs/painel-gilda.md`.

---

## 2. Peças — hoje **parcial**

### Onde aparecem (já automático ao publicar uma peça no WooCommerce)
Loja `/loja/` (`archive-product.html:15`, 12 por página, ordem manual), categoria (`taxonomy-product_cat.html`), busca, relacionados (8, `single-product.html:39`), filtros, "Restam só N" (`MU/gilda-low-stock.php`), "Avise-me" (`MU/gilda-stock-alerts/`), Meta Pixel, SEO, compartilhar, frete, carrinho/checkout, cache (limpa sozinho, `gilda-catalog-cache.php:133-163`).

### Onde **não** aparece sozinha
- **Destaques da home** (`T/patterns/featured.php:14`): só peças marcadas com a estrela "Destaque" (3, mais recentes). O import nunca marca.
- **Capítulos**: links e filtro são listas fixas (ver §1). Há 6 citações fixas de peças por slug (`book-chapters.php:32,51,52,72,73,74`).
- **Carrossel do login**: usa fotos fixas (`account_carousel`).

### Campos para cadastrar uma peça
Nome · preço (promoção funciona, mas não há selo "Oferta" nos cards) · foto principal (3:4) · **pelo menos 1 foto na galeria** (a 1ª vira a foto do "passar o mouse" no card, `woocommerce.php:176-191`) · descrição (aparece acima do preço, inclui composição e guia de medidas) · categoria · cores e tamanhos com **estoque por combinação** · **peso e medidas da embalagem** (sem isso os Correios usam o mínimo do plugin, `setup-store.php:233-236`) · capítulo · opcionais: destaque, ordem na loja, SKU, "Descrição para o Google".

**Proposta:** tela "Nova peça" simplificada por cima do WooCommerce (os dados continuam no WooCommerce, então pedidos, estoque e relatórios seguem iguais), com a grade cor × tamanho de estoque, campo de capítulo e o peso/medidas já preenchidos pela categoria (editáveis). Mudanças no código: capítulo por relação, e o cache do frete (`T/inc/shipping.php:69,131`) passa a considerar alterações de peso/medidas.

---

## 3. Categorias — hoje **parcial**

| Lugar | Hoje |
|---|---|
| Filtro "Buscar por" e "Tipo de produto" (`filters.php:137-193`), página da categoria, relacionados, SEO, cache | **Automático** para categoria nova de primeiro nível |
| **Cards da home** (CAMISAS, CALÇAS, BERMUDAS, LENÇOS) | **Fixo**: 4 linhas em `T/patterns/categories.php:14-19` com foto (`category_*` do JSON), rótulo, link `?tipo=` e cor. Não lê as categorias do WooCommerce. Renomear o slug ou apagar uma categoria deixa o card abrindo a loja vazia. |
| Animação do card (zoom no nome) | **Genérica**: vale para qualquer card (`category-zoom.js`) |
| Menus | Não têm categorias |
| Peso/medidas padrão por categoria | Só no import (`import-products.php:24-29`) |
| Textos de SEO padrão ("camisas, calças, bermudas e lenços") | Fixo (`MU/gilda-seo.php:96,122`) |

**Campos:** nome, ordem, descrição (opcional); para aparecer na home: **mostrar na home** (sim/não), foto do card (retrato ~900×1200), rótulo do card; peso e medidas padrão. **Ao adicionar:** entra nos filtros e na página dela; se marcada "na home", ganha card com zoom. A grade da home precisa aceitar 3, 4, 5… cards (hoje é `minmax(240px)`, ok).

---

## 4. Cores (`pa_cor`) — hoje **parcial**

- Aparecem como bolinhas na página da peça (`gilda.js:236-278`) e no filtro "Cor" (`filters.php:213-226`).
- **O código hexadecimal está fixo no tema** (`T/inc/setup.php:155-168`, 7 cores). Cor nova entra com a bolinha **bege genérica** (`#D9D3C2`) até um programador incluir.
- O import já grava um hex em cada cor (`gilda_hex`, `import-products.php:105-108`), mas **o tema não lê**, e os dois discordam (Marrom `#b4522d` × `#5C3A21`; Off White `#e9e9d4` × `#F2EEE3`).

**Campos:** nome, **cor (seletor)**, ordem. **Mudança:** o tema passa a ler a cor gravada na própria cor; decidir qual dos dois hex vale hoje.

## 5. Tamanhos (`pa_tamanho`) — hoje **parcial**

- Botões na página da peça (`gilda.js:281-310`) e filtro "Tamanho".
- A ordem (PP, P, M, G, GG, 36…44) só é aplicada pelo import (`import-products.php:180-187`): tamanho novo provavelmente vai para o começo até alguém arrastar.
- Botões de **44×44 px fixos** (`theme.css:712-729`): "Único" / "Tamanho único" estoura.
- A tabela de medidas do modelo de descrição é fixa PP-GG (`T/patterns/product-details.php:13-19`).

**Campos:** nome, ordem (arrastar). **Mudança:** botão com largura mínima em vez de fixa.

---

## 6. Cupons e "promoção ativa" — hoje **fixo**

- O cupom `primeiracompra` (10%, uso único por cliente) é criado pelo `setup-store.php:299-310`. A regra "só na primeira compra" (`MU/gilda-first-purchase-coupon.php`) vale para qualquer cupom com o campo escondido `_gilda_first_purchase_only` — **não há caixinha no painel** para isso.
- O código e o "10%" estão **escritos à mão** na faixa de avisos (`T/inc/shortcodes.php:28`), na fita do banner da home (`T/patterns/hero.php:19`) e nos termos de uso (`docs/legal/termos-de-uso.md:44`).
- **Se as donas mudarem para 15%:** a faixa e o banner continuam dizendo 10%, e o próximo setup volta o cupom para 10%.
- **Se criarem outro cupom:** nada no site anuncia; se apagarem o `primeiracompra`, a faixa e o banner anunciam um código que não funciona.

**Proposta:** cupons continuam no WooCommerce (Marketing → Cupons), com uma caixinha "Só na primeira compra". E um bloco **"Promoção em destaque"**: escolher o cupom, e a faixa e a fita do banner mostram código e percentual **lidos do cupom** (ex.: "{percentual} OFF na primeira compra com o cupom {código}"), com liga/desliga.

---

## 7. Faixa de avisos (acima do cabeçalho) — hoje **fixo**

4 mensagens em `T/inc/shortcodes.php:24-48`, em todas as páginas (`T/parts/header.html:2`): o cupom, "Alfaiataria criativa — feito à mão em Curitiba/PR", "Novas peças a cada capítulo", "@gildapontoevirgula".

**Campos:** lista de mensagens (até ~60 caracteres), link opcional por mensagem, ordem, liga/desliga da faixa; atalho para a promoção em destaque (§6). **Ao mudar:** aparece em todas as páginas, sem cache para limpar.

---

## 8. Home — banner e títulos — hoje **fixo**

| Item | Onde | Campos |
|---|---|---|
| Foto do banner (`hero`) | `T/patterns/hero.php:39-56` | Imagem paisagem ≥ 2400×1350 + ponto de foco |
| Marca ";" sobre o banner (`hero_mark`) | `hero.php:14,22-28` (some no celular) | Imagem pequena |
| Fita do cupom | `hero.php:19` | Vem da promoção em destaque (§6) |
| Botão "disponível" | `hero.php:13,32` | Texto + link |
| "ALFAIATARIA CRIATIVA" | `T/patterns/categories.php:52` | Texto |
| "CAPÍTULOS" | `T/patterns/shop-section.php:21` | Texto |
| Destaques | `featured.php` | Já dinâmico (estrela na peça) |
| Descrição para o Google da home | `MU/gilda-seo.php:95-97` | Já existe na página Início |

---

## 9. Quem Cria — hoje **fixo** (nem o editor do WordPress alcança)

Tudo está em `T/patterns/about.php` incluído por shortcode:

| Item | Linha | Campos |
|---|---|---|
| Título "ALFAIATARIA CRIATIVA" | `:44` | Texto |
| Abertura com "GILDA;" vazado e zoom no ";" (`[gilda_mask_transition]`) | `:51-52`; padrões em `T/inc/transitions.php:126-129` | Foto (paisagem ≥ 2400 px), palavra ("GILDA;"), faixa ("DEPOIS DO PONTO E VÍRGULA,"), título ("VOCÊ CONTA A HISTÓRIA") |
| "quem cria" | `:67` | Texto |
| Criadoras: nome (pintado de marrom→creme no scroll), foto, ajuste de enquadramento, parágrafos | `:13-39` (render `:76-96`) | **Lista de criadoras**: nome, foto retrato ~1200×1500, enquadramento, texto |

**Ao mudar:** a abertura e a pintura do nome continuam funcionando (são genéricas para qualquer nome).

---

## 10. Rodapé, dados da empresa e contatos — hoje **fixo**

O mesmo dado está escrito em vários lugares, em formatos diferentes:

| Dado | Onde aparece |
|---|---|
| Telefone/WhatsApp (3 formatos) | rodapé `:49,109`; política `:21`; termos `:20` |
| E-mail | rodapé `:113`; política `:20,108`; termos `:19` |
| Instagram | faixa de avisos `shortcodes.php:31`; rodapé `:48` |
| Razão social + CNPJ | rodapé `:124`; `setup-store.php:252,254`; os 3 textos legais |
| Cidade / CEP / endereço | rodapé `:124`; `.env`; `setup-store.php`; textos legais; faixa ("Curitiba/PR"); SEO |
| Copyright "© 2006" | rodapé `:136` (ano parece errado) |

**Proposta:** uma tela **"Dados da loja"** (razão social, CNPJ, endereço, CEP, cidade/UF, telefone, WhatsApp, e-mail, Instagram) e todos esses lugares passam a ler dela (rodapé, textos legais via marcadores, remetente dos Correios, SEO). Copyright com ano automático.

**Feito (fase 1):** tela "Dados da loja" (`MU/gilda-painel/store-data.php`, opção `gilda_store`, função `gilda_store()`), com o logo. Leem dela: o rodapé (`[gilda_footer_social]`, `[gilda_footer_contact]`, `[gilda_footer_legal]`, `[gilda_copyright]` com ano automático), o @ da faixa de avisos e o logo do cabeçalho/rodapé. **Ainda não leem:** textos legais, remetente dos Correios / endereço de origem do WooCommerce e SEO.

---

## 11. Menus — hoje **fixo** e inconsistentes

Três listas escritas à mão que não batem: cabeçalho desktop (`header.html:9-12`), cabeçalho celular (`:16-19`, rótulos diferentes e sem Quem Cria) e rodapé (`footer.html:61-97`). O rodapé aponta para **2 páginas que não existem** ("Cuidados com as peças", "Dúvidas frequentes") e não aponta para os termos de uso.

**Campos:** menu principal e menus do rodapé (rótulo + link para página/categoria/capítulo), ordem.

---

## 12. Páginas e textos do sistema

| Item | Hoje | Proposta |
|---|---|---|
| Política de privacidade | Markdown no git, publicada pelo setup (`docs/legal/`) | Depois da virada: editada no painel, sem o setup regravar; dados da loja por marcador |
| Trocas e devoluções, Termos de uso | Rascunhos com `[PREENCHER]` | Idem, publicar quando revisados; ligar "Termos" no checkout |
| Cuidados com as peças, Dúvidas frequentes | **Não existem** (mas o rodapé linka) | Criar (texto / perguntas e respostas) ou tirar do rodapé |
| Banner de cookies | Textos fixos (`MU/gilda-consent.php:82-105`) | Campos de texto + botão "perguntar de novo para todo mundo" |
| 404, carrinho vazio ("próximos capítulos"), "Nenhum produto encontrado" (3 cópias), "você também vai gostar", pedido recebido | Fixos nos templates / PHP | Uma tela "Textos do site" |
| Remetente e visual dos e-mails | WooCommerce (cores padrão roxas) | Configurar logo/cor nos e-mails do WooCommerce |
| Meta Pixel, SMTP, gateway de pagamento | `.env` / WooCommerce | Continuam técnicos (não vão para o painel das donas) |

## 13. Imagens do site (`data/site-images.json`)

| Chave | Uso | Fica em |
|---|---|---|
| `logo` | cabeçalho e rodapé | Dados da loja |
| `hero`, `hero_mark` | banner da home | Home (§8) |
| `category_*` | cards da home | Categoria (§3) |
| `about_*`, `story_banner` | Quem Cria | Quem Cria (§9) |
| `chapter_*` | capítulos | Capítulo (§1) |
| `account_carousel` | carrossel do login | Textos/imagens do site |
| `butterfly`, `chapter_3_hd` | **não usadas** (`chapter_3_hd` só nas listas de capítulo) | Remover / migrar |

---

## 14. Como fica o painel

Dentro do próprio WordPress, com um perfil **"Dona da loja"** (baseado no gerente de loja do WooCommerce) que vê só:

```
Painel GILDA;
├─ Pedidos                      (WooCommerce)
├─ Peças → Nova peça            (tela simplificada; dados no WooCommerce)
├─ Capítulos → Novo capítulo    (tipo de conteúdo novo)
├─ Categorias, Cores, Tamanhos  (com foto do card / seletor de cor / ordem)
├─ Cupons + Promoção em destaque
├─ Página inicial               (banner, títulos, faixa de avisos)
├─ Quem Cria                    (abertura, criadoras)
├─ Dados da loja                (empresa, contatos, redes, logo)
├─ Menus e Textos do site
└─ Páginas                      (políticas, trocas, FAQ)
```

Plugins, temas, configurações técnicas e o editor de blocos ficam escondidos para esse perfil.

**Feito (fase 1):** perfil `gilda_dona` ("Dona da loja", `MU/gilda-painel/role.php`), com o menu Painel GILDA; · Pedidos (e Clientes) · Peças · Capítulos · Categorias, Cores e Tamanhos · Cupons · Dados da loja · Páginas. Os itens de Página inicial, Quem Cria, Menus e Textos do site entram com as fases 4 e 5. Por que dentro do WordPress e não um site administrativo à parte: login, permissões, envio de fotos, estoque e pedidos já existem e são seguros; um painel separado teria que refazer tudo isso.

## 15. Fases sugeridas

1. **Base:** regra "semente uma vez" nos scripts (§0), incluindo os textos legais (decisão §16.5); perfil "Dona da loja"; tela "Dados da loja" (§10) ligada ao rodapé e aos textos legais.
2. **Capítulos** (§1): tipo de conteúdo, migração dos 3 atuais, página Capítulos/Livro/cards da home/filtro lendo dele, limites removidos (colagem, grade, rótulo, cache), capítulo na página da peça.
3. **Catálogo:** "Nova peça" (§2), cores com seletor (§4), tamanhos (§5), categorias com card na home (§3).
4. **Promoção e home:** caixinha "só primeira compra" + promoção em destaque (§6), faixa de avisos (§7), banner (§8).
5. **Quem Cria, menus, textos do site e páginas** (§9, §11, §12).
6. **Guia para as donas** em português (com prints) e testes automáticos de "criar capítulo / peça / cupom".

Cada fase é um PR; a 2 e a 3 mexem bastante no tema (K1venn).

## 16. Decisões

### Tomadas (26/11/2026)

1. **Uma peça pertence a no máximo um capítulo.** No painel, o capítulo é um campo da peça (escolher um). O capítulo lista as peças que apontam para ele.
2. **Colagem do zoom fixa em 7 fotos** (1 principal + 6). O formulário do capítulo exige as 7 e avisa se faltar, para a colagem nunca ficar com buracos.
3. **Home com no máximo 5 cards** (os 5 capítulos mais recentes; a mesma regra de 5 vale para os cards de categoria marcados "na home"). A grade da home passa de 3 para até 5 colunas.

### Tomadas depois (26/11/2026)

4. **Peças de cada capítulo** (resolve as peças que hoje estão em dois capítulos e o texto do capítulo 3):

   | Capítulo | Peças | Origem |
   |---|---|---|
   | 1 — Borboleta 88 | Camisa Casulo, Calça Asas, Lenço oitenta-e-oito | Casulo e Asas: decisão. Lenço: inferido do texto do capítulo ("lenço com estampa exclusiva em aquarela… borboleta 88") |
   | 2 — Os filetes da borboleta | Bermuda Envolto, Lenço Manacá | decisão |
   | 3 — Ritual Brasileiro | Camisa Grão, Calça Torra | decisão. A "camisa Café" do texto é a **Camisa Grão na cor Marrom** (não é outra peça) |

5. **Textos legais editáveis no painel.** Política de privacidade, trocas e termos passam a ser editados pelas donas no painel. O `scripts/import-legal-pages.php` vira "semente uma vez": cria a página se ela não existir e nunca mais sobrescreve (entra na fase 1, junto com os outros scripts do §0).

6. **Cores das bolinhas = as do Wix** (Marrom `#b4522d`, Off White `#e9e9d4` etc.). O tema passa a ler a cor gravada em cada cor do atributo (`gilda_hex`), que é o que o painel vai editar; o mapa fixo do tema vira só reserva.
7. **"Cuidados com as peças" e "Dúvidas frequentes" são criadas**, simples, no padrão das outras páginas, e editáveis no painel (semente uma vez, como os textos legais).
8. **O painel é só das donas.** O perfil "Dona da loja" vê pedidos e clientes; não há perfil restrito.

9. **Sem lookbook.** A galeria extra de looks do capítulo 3 foi removida e o formulário do capítulo não terá esse campo. (As fotos `chapter_3_lookbook` continuam importadas porque a abertura e a narrativa do capítulo 2 usam três delas; na migração elas viram fotos do capítulo 2.)

Todas as decisões do levantamento estão tomadas.

## 17. Achados no caminho (fora do painel)

- `© 2006` no rodapé.
- Dois links do rodapé para páginas inexistentes; termos de uso sem link.
- Imagens/códigos sem uso: `butterfly`, `chapter_3_hd`, `[gilda_zoom_parallax]`, `[gilda_payment_methods]`, `T/patterns/products-page.php` (a página `/produtos/` redireciona para a loja).
- `setup-store.php` usa São Paulo quando o `.env` não tem endereço.
- Filtros por URL em dois formatos (`cor[]` do tema × `filter_cor` do WooCommerce): o cache só reconhece o primeiro (`gilda-catalog-cache.php:28`).
- Colagem do zoom: se a 1ª foto faltar, uma foto lateral vira a principal (`transitions.php:52-118`).

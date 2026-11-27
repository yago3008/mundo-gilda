# Integração site ↔ ERP

O ERP é um sistema de gestão interna das donas da GILDA;. Hoje ele existe só como **protótipo** (https://yago3008.github.io/projeto-gilda/#/). Este documento combina, para o futuro, **o que é responsabilidade de cada sistema**, para não haver retrabalho, peça perdida nem cadastro duplo. Escrito em 26/11/2026, a partir do protótipo e do Painel GILDA; do site (`docs/development/painel-conteudo.md`, `docs/painel-gilda.md`).

## 1. O que cada sistema faz

**ERP (protótipo): custo e produção**
- **Painel:**
  - peças cadastradas e unidades em estoque;
  - estoque a custo e a preço de venda, lucro potencial e margem média;
  - custo por grupo e unidades por categoria e por coleção;
  - estoque baixo, melhores e piores margens, últimas movimentações.
- **Peças:**
  - uma linha por peça + tamanho + cor, com SKU (ex.: `CAM-GRAO-OW-PP`);
  - coleção/capítulo, categoria, estoque e estoque mínimo, preço, custo de produção;
  - composição do tecido, modelagem, tempo de produção e campos personalizados;
  - **composição de custo:** insumo × quantidade × valor unitário;
  - exportação CSV/Excel.
- **Insumos:**
  - tecidos, botões, zíperes, etiquetas, aviamentos, mão de obra, embalagem e sacola;
  - para cada um, unidade, custo unitário e fornecedor.
- **Estoque:** histórico de entradas e saídas com motivo (produção, venda, devolução, perda, inventário, ajuste).
- **Cadastros:** coleções/capítulos, categorias, grupos de custo, listas de tamanho, cor, unidade e motivo, e campos personalizados.

**Site (WordPress/WooCommerce + Painel GILDA;): vitrine e venda**
- **Peças:**
  - fotos, descrição de venda, preço e destaque na home;
  - variações cor × tamanho, com estoque de cada uma;
  - peso e medidas para o frete.
- **Capítulos:** textos, fotos da abertura (7), da história e da galeria, peças, e a animação da página Capítulos.
- **Vendas:** pedidos, clientes, pagamento, frete PAC/SEDEX, cupons, "Avise-me" e "Restam só N".
- **Conteúdo:** páginas (políticas, trocas, cuidados, dúvidas) e Dados da loja.

## 2. Regra de ouro

**Cada informação tem um dono só.** Só o dono cria e edita; o outro sistema **lê ou recebe** automaticamente e nunca deixa digitar de novo, nem que seja um campo bloqueado. A peça é a mesma nos dois lados graças a uma chave: o **SKU**.

## 3. Quem é dono de quê

| Informação | Dono | O outro sistema |
|---|---|---|
| Insumos, fornecedores, grupos de custo | **ERP** | O site não usa |
| Composição de custo, custo de produção, margem | **ERP** | O site não usa (nunca mostrar custo na loja) |
| Ficha técnica: composição do tecido, modelagem, tempo de produção, campos personalizados | **ERP** | — |
| **SKU** de cada variação (peça + cor + tamanho) | **ERP** cria | O site grava o mesmo SKU na variação |
| Nome da peça, categoria, cores e tamanhos que ela tem | **ERP** na criação (envia ao site) | O site recebe; fotos e texto de venda continuam no site |
| Movimentos de **produção, perda, inventário e ajuste** | **ERP** registra | Envia ao site a quantidade a somar ou tirar |
| **Estoque atual** de cada variação | **Site**: as vendas baixam na hora | O ERP lê e mostra (não guarda um número próprio) |
| **Vendas e devoluções** pelo site | **Site** | Entram no ERP como "saída: venda" / "entrada: devolução" |
| Preço de venda e preço promocional | **Site**: é onde a cliente compra e onde ficam as promoções | O ERP lê para calcular a margem |
| Fotos, descrição de venda, destaque na home, peso e medidas | **Site** | — |
| Capítulos (textos, fotos, animação, peças) | **Site** | O ERP usa o nome do capítulo nos relatórios |
| Categorias, cores (com a cor da bolinha) e tamanhos (a lista) | **Site** | O ERP lê as listas (não tem lista própria) |
| Cupons, clientes, frete, páginas, Dados da loja | **Site** | — |

**Por que o estoque mora no site:** a venda online acontece a qualquer hora e baixa o estoque no mesmo instante. Se o ERP tivesse um número próprio, os dois iam divergir na primeira venda. O ERP continua sendo onde se registra **por que** o estoque mudou (produção, perda, inventário). Ele só não guarda o saldo.

**Venda fora do site** (feira, Instagram, pronta-entrega): registrar no site como pedido manual (o WooCommerce tem "Adicionar pedido") ou no ERP como "saída: venda", que manda a baixa para o site. Um dos dois, nunca os dois.

## 4. Os fluxos

**Peça nova**
1. **ERP:**
   - cria a peça: nome, categoria, cores, tamanhos e composição de custo;
   - o SKU de cada cor × tamanho é gerado sozinho;
   - registra a produção, que gera a entrada no estoque.
2. **ERP → site:** "Publicar no site" cria a peça no WooCommerce **em rascunho**. Ela já vai com as variações (um SKU por cor × tamanho), o estoque produzido, a categoria e o capítulo.
3. **Painel GILDA;:** as donas completam fotos, descrição, preço, peso e medidas, e publicam.

**Venda no site**
1. O site baixa o estoque da variação (como hoje).
2. Site → ERP (webhook do WooCommerce): o ERP registra "saída: venda", com pedido, SKU e quantidade, e atualiza custo vendido, lucro e giro.

**Produção nova, perda, inventário**
1. ERP registra o movimento (ex.: "+5 Camisa Grão Off White M, produção").
2. ERP → site: soma ou tira a quantidade daquela variação (pelo SKU).
3. Inventário = o ERP manda "o saldo certo é N"; o site acerta e o ERP grava a diferença como ajuste.

**Mudança de preço**: só no site. O ERP lê o preço novo para recalcular a margem.

**Capítulo novo**: só no Painel GILDA;. O ERP passa a mostrar o nome dele na lista de coleções.

**Cor ou tamanho novo**: no site (Categorias, Cores e Tamanhos, com a cor da bolinha). O ERP lê a lista atualizada.

## 5. Como ligar os dois (quando o ERP sair do protótipo)

**Pré-requisito: o ERP precisa de servidor.** O protótipo guarda tudo no próprio navegador (`localStorage`, chave `gilda:dados:v1`). Por isso:
- os dados só existem naquele computador e naquele navegador;
- limpar o navegador apaga tudo e não há backup;
- as duas donas não veem a mesma informação;
- o site não tem como conversar com ele.

Antes de integrar, o ERP precisa de banco de dados num servidor, login por pessoa e backup.

Dois caminhos:

**A. ERP como sistema separado, ligado pela API do WooCommerce**
- **ERP → site:** API REST do WooCommerce (`/wp-json/wc/v3/products`, `/products/<id>/variations`). Usa uma chave de API própria do ERP (WooCommerce → Avançado → REST API), com permissão só de produtos.
- **Site → ERP:** webhooks do WooCommerce ("Pedido criado/atualizado", "Produto atualizado") chamando um endereço do ERP. A assinatura dos webhooks precisa ser conferida.
- **Pontos de atenção:**
  - tentar de novo quando um lado estiver fora do ar;
  - nunca reenviar um movimento já aplicado;
  - fazer uma conferência noturna "estoque do site × movimentos do ERP".

**B. ERP dentro do próprio site (recomendado se for construir do zero)**
- Insumos, composição de custo, produção, movimentos e relatórios viram mais uma área do **Painel GILDA;**, com tabelas próprias no mesmo banco do WordPress.
- Um sistema e um login só: não existe sincronização, SKU duplicado nem estoque divergente. O backup do site já cobre tudo.
- As telas do protótipo servem direto como desenho dessa área: Painel, Peças, Insumos, Estoque e Cadastros.
- Custo e margem ficam visíveis só para o perfil "Dona da loja", nunca na loja.

## 6. Correspondência dos dados

| ERP (protótipo) | Site (WooCommerce) |
|---|---|
| Uma linha de "Peça" (nome + tamanho + cor) | Uma **variação** do produto variável (ou o produto simples, para lenço "Único") |
| Peças com o mesmo nome | O **produto** (pai das variações) |
| Código / SKU | SKU da variação (`_sku`) |
| Coleção / capítulo | Capítulo do Painel GILDA; (`_gilda_capitulo` na peça) |
| Categoria | Categoria do WooCommerce (`product_cat`) |
| Tamanho / Cor | Atributos `pa_tamanho` / `pa_cor` |
| Quantidade em estoque | Estoque da variação (estoque gerenciado) |
| Estoque mínimo (alerta) | Limite do "Restam só N" (hoje global) e aviso no Início do painel |
| Preço de venda | Preço normal da variação |
| Custo de produção, composição, insumos | — (fica só no ERP) |

## 7. O que hoje não bate entre o protótipo e o site

Os dados de exemplo do protótipo precisam ser alinhados antes de qualquer integração:
- **Capítulos:**

  | | Ritual | Borboleta 88 |
  |---|---|---|
  | **Protótipo** | Capítulo 2 | não aparece ("Capítulo 1 · Origem" no lugar) |
  | **Site** | Capítulo 3 ("Ritual Brasileiro") | Capítulo 1 |
  
  No site, o Capítulo 2 é "Os filetes da borboleta".
- **Cores:**
  - Camisa Casulo: Terracota no protótipo, Branco e Azul Claro no site;
  - Bermuda Envolto: Terracota no protótipo, Laranja no site;
  - Lenço oitenta-e-oito: Mostarda no protótipo.
- **Listas:** o protótipo tem Creme, Mostarda, Oliva, Azul, Blazers e Vestidos, que não existem na loja. Pela regra do §3, as listas passam a vir do site.
- **SKU:** as peças do site ainda não têm SKU (o import do Wix não traz). Ao adotar os SKUs do ERP, o **Meta Pixel** passa a enviar o SKU no lugar do ID da peça (`gilda-meta-pixel.php` usa SKU quando existe). Avisar quem cuida dos anúncios, porque os catálogos do Facebook/Instagram mudam de chave.

## 8. Decisões para quando o ERP for construído

1. Caminho A (separado, via API) ou B (dentro do Painel GILDA;).
2. Formato do SKU (o protótipo usa `CAT-NOME-COR-TAM`, ex.: `CAM-GRAO-OW-PP`) e o que acontece quando uma peça muda de nome (o SKU não muda).
3. Vendas fora do site: registradas como pedido manual no site ou como saída no ERP.
4. Estoque mínimo por variação (o ERP tem; o site hoje usa um limite único para o "Restam só N").
5. Quem vê custo e margem: só as donas, ou também alguém da produção.

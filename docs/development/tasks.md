# Backlog de Tasks — GILDA; E-commerce

Projeto: Recriação / Evolução do e-commerce mundogilda.com.br
Product Owner: [PO]
Data: 2026-11-26

---

## Legenda de Prioridade (MoSCoW)

| Sigla | Significado      | Descrição                               |
| ----- | ---------------- | --------------------------------------- |
| **M** | Must have        | Obrigatório para o e-commerce funcionar |
| **S** | Should have      | Importante, agrega valor significativo  |
| **C** | Could have       | Desejável, melhora a experiência        |
| **W** | Won't have (now) | Fora do escopo inicial, avaliar depois  |

---

## EPIC 1 — NAVEGACAO E ESTRUTURA GLOBAL

### TASK 1.1 — Menu Principal Desktop

**Prioridade:** M
**Descricao:** Implementar barra de navegacao fixa no topo com os links: Inicio, Produtos, Quem Cria, Capitulos.
**Criterios de Aceitacao:**

- [ ] Menu visivel em todas as paginas do site
- [ ] Cada item direciona para a pagina correta
- [ ] Item ativo possui destaque visual (sublinhado ou cor diferente)
- [ ] Menu permanece fixo durante o scroll (sticky header)
- [ ] Tipografia e cores seguem a identidade visual da marca (tons marrom/terracota)

---

### TASK 1.2 — Menu Hamburger Mobile

**Prioridade:** M
**Descricao:** Implementar menu mobile em formato drawer lateral com os itens: GILDA;, PRODUTOS;, LIVRO GILDA;, CONTATO.
**Criterios de Aceitacao:**

- [ ] Icone hamburger visivel apenas em resolucoes mobile/tablet (< 1024px)
- [ ] Ao clicar, abre drawer lateral (dialog) com animacao suave
- [ ] Exibe os 4 links de navegacao
- [ ] Botao de fechar ("X") funcional e acessivel
- [ ] Clicar em um item navega e fecha o drawer automaticamente
- [ ] Fundo escurecido (overlay) ao abrir o menu

---

### TASK 1.3 — Logo no Header com Link para Home

**Prioridade:** M
**Descricao:** Exibir logotipo GILDA; no header, funcionando como link para a pagina inicial.
**Criterios de Aceitacao:**

- [ ] Logo posicionado centralizado no header
- [ ] Clicar no logo redireciona para a home (/)
- [ ] Imagem com alt text descritivo
- [ ] Logo responsivo (tamanho adapta ao viewport)

---

### TASK 1.4 — Botao de Login no Header

**Prioridade:** S
**Descricao:** Exibir botao/icone de login no header para acesso a area do cliente.
**Criterios de Aceitacao:**

- [ ] Icone de usuario visivel no header em todas as paginas
- [ ] Ao clicar, abre modal/pagina de login
- [ ] Visivel tanto em desktop quanto em mobile
- [ ] Estado deslogado: exibe icone generico
- [ ] Estado logado: exibe indicador de usuario autenticado

---

### TASK 1.5 — Icone do Carrinho com Contador

**Prioridade:** M
**Descricao:** Exibir icone de carrinho no header com badge indicando a quantidade de itens adicionados.
**Criterios de Aceitacao:**

- [ ] Icone de carrinho visivel no header em todas as paginas
- [ ] Badge numerico atualiza em tempo real ao adicionar/remover itens
- [ ] Carrinho vazio: exibe "0" ou oculta o badge
- [ ] Ao clicar, direciona para a pagina do carrinho (/cart-page)
- [ ] Acessibilidade: aria-label "Carrinho com X itens"

---

### TASK 1.6 — Breadcrumbs na Pagina de Produto

**Prioridade:** C
**Descricao:** Implementar navegacao breadcrumb na pagina de detalhe do produto mostrando o caminho hierarquico.
**Criterios de Aceitacao:**

- [ ] Exibido apenas na pagina de detalhe do produto
- [ ] Formato: GILDA; > All Products > [Nome do Produto]
- [ ] Cada nivel e clicavel e navega para a respectiva pagina
- [ ] Ultimo item (produto atual) nao e clicavel
- [ ] Responsivo: trunca em telas pequenas se necessario

---

### TASK 1.7 — Skip to Content (Acessibilidade)

**Prioridade:** C
**Descricao:** Implementar link oculto "Ir para o conteudo principal" para navegacao por teclado.
**Criterios de Aceitacao:**

- [ ] Link visivel apenas ao receber foco via Tab
- [ ] Ao ativar, foco pula para a area de conteudo principal (<main>)
- [ ] Presente em todas as paginas

---

## EPIC 2 — HOME PAGE (INICIO)

### TASK 2.1 — Banner Hero com Promocao

**Prioridade:** M
**Descricao:** Implementar banner principal da home com imagem de fundo, texto promocional e cupom de desconto.
**Criterios de Aceitacao:**

- [ ] Imagem de fundo em alta resolucao com efeito blur/foco artistico
- [ ] Exibe texto: "Use o cupom para 10% off na sua primeira compra pelo site: primeiracompra"
- [ ] Botao CTA "disponivel" posicionado sobre o banner
- [ ] Botao CTA direciona para /category/all-products
- [ ] Banner responsivo (imagem adapta ao viewport, texto legivel em mobile)
- [ ] Texto do cupom deve ser editavel via CMS/painel admin

---

### TASK 2.2 — Secao de Produtos em Destaque

**Prioridade:** S
**Descricao:** Exibir carrossel com 3 produtos em destaque na home page.
**Criterios de Aceitacao:**

- [ ] Exibe 3 cards de produto lado a lado (desktop) ou em carrossel (mobile)
- [ ] Cada card mostra: imagem e nome do produto
- [ ] Clicar no card direciona para a pagina de detalhe do produto
- [ ] Produtos exibidos sao configuráveis pelo admin
- [ ] Imagens carregam com lazy loading

---

### TASK 2.3 — Navegacao por Categorias Visuais

**Prioridade:** S
**Descricao:** Exibir secao com 4 categorias (Camisas, Calcas, Bermudas, Lencos) representadas por imagens clicaveis.
**Criterios de Aceitacao:**

- [ ] 4 cards visuais com imagem de fundo + nome da categoria
- [ ] Cada card direciona para a pagina de detalhe de um produto representativo da categoria
- [ ] Layout em grid: 4 colunas (desktop), 2 colunas (tablet), 1 coluna (mobile)
- [ ] Texto "ALFAIATARIA CRIATIVA" exibido abaixo da secao
- [ ] Hover effect nas imagens

---

### TASK 2.4 — Secao Loja com Link

**Prioridade:** S
**Descricao:** Exibir secao "LOJA" na home com icone da borboleta e link para pagina de produtos.
**Criterios de Aceitacao:**

- [ ] Heading "LOJA" centralizado
- [ ] Icone da borboleta 88 (identidade visual) clicavel
- [ ] Clicar direciona para /contact-1 (pagina de produtos)
- [ ] Logo da marca exibido na secao

---

### TASK 2.5 — Secao Instagram

**Prioridade:** C
**Descricao:** Exibir handle do Instagram (@gildapontoevirgula) na home page.
**Criterios de Aceitacao:**

- [ ] Texto @gildapontoevirgula visivel
- [ ] Clicavel, direciona para o perfil no Instagram (nova aba)

---

## EPIC 3 — CATALOGO DE PRODUTOS (PRODUTOS;)

### TASK 3.1 — Carrossel de Produtos

**Prioridade:** M
**Descricao:** Implementar carrossel horizontal com todos os produtos disponiveis, incluindo navegacao por setas.
**Criterios de Aceitacao:**

- [ ] Exibe todos os produtos em formato carrossel horizontal
- [ ] Botoes "Produto Anterior" e "Proximo Produto" funcionais
- [ ] Navegacao ciclica (volta ao inicio apos o ultimo)
- [ ] Responsivo: exibe 3-4 cards (desktop), 2 (tablet), 1 (mobile)
- [ ] Transicao suave entre slides

---

### TASK 3.2 — Card de Produto no Catalogo

**Prioridade:** M
**Descricao:** Implementar card de produto padrao com imagem, nome, preco e acoes rapidas.
**Criterios de Aceitacao:**

- [ ] Imagem do produto (clicavel → abre pagina de detalhe)
- [ ] Nome do produto abaixo da imagem
- [ ] Preco formatado em reais (ex: R$ 429,00)
- [ ] Botao "Adicionar ao carrinho" — adiciona o produto ao carrinho sem navegar (acao AJAX)
- [ ] Botao "Visualizacao rapida" (quick view) — abre modal com preview do produto
- [ ] Hover na imagem revela os botoes de acao
- [ ] **Estado Esgotado:** botao muda para "Esgotado" (desabilitado, estilo diferenciado)

---

### TASK 3.3 — Headings Tematicos na Pagina de Produtos

**Prioridade:** C
**Descricao:** Exibir headings editoriais/decorativos na pagina de produtos: "produtos;", "capitulos", "historias", "palavras", "ALFAIATARIA".
**Criterios de Aceitacao:**

- [ ] Textos posicionados como elementos decorativos (tipografia grande, estilo editorial)
- [ ] Nao interferem na navegacao ou acessibilidade
- [ ] Responsivos

---

## EPIC 4 — PAGINA DE TODOS OS PRODUTOS (ALL PRODUCTS)

### TASK 4.1 — Navegacao por Categoria (Sidebar)

**Prioridade:** M
**Descricao:** Implementar sidebar com lista de categorias para filtrar produtos.
**Criterios de Aceitacao:**

- [ ] Titulo "Buscar por"
- [ ] Lista de categorias: Todos os produtos, Bermudas, Calcas, Camisas, Lencos
- [ ] Clicar em uma categoria filtra a galeria (ou navega para URL de categoria)
- [ ] Categoria ativa possui destaque visual
- [ ] Em mobile: sidebar colapsa em acordeao ou filtro deslizante

---

### TASK 4.2 — Filtro por Tipo de Produto

**Prioridade:** S
**Descricao:** Implementar filtro por checkbox para tipos de produto.
**Criterios de Aceitacao:**

- [ ] Titulo "Tipo de produto" colapsavel (acordeao)
- [ ] Checkboxes: Bermudas, Calcas, Camisas, Lencos
- [ ] Multipla selecao permitida
- [ ] Galeria atualiza em tempo real ao marcar/desmarcar
- [ ] Contagem de resultados visivel

---

### TASK 4.3 — Filtro por Faixa de Preco

**Prioridade:** S
**Descricao:** Implementar filtro de preco com slider de faixa (range slider).
**Criterios de Aceitacao:**

- [ ] Titulo "Preco" colapsavel
- [ ] Slider duplo com valor minimo (R$ 97) e maximo (R$ 569)
- [ ] Valores limites exibidos nas extremidades
- [ ] Arrastar os handles atualiza a galeria em tempo real
- [ ] Valores atuais exibidos durante a interacao
- [ ] Funcional em dispositivos touch

---

### TASK 4.4 — Filtro por Cor

**Prioridade:** S
**Descricao:** Implementar filtro de cor com checkboxes para selecao multipla.
**Criterios de Aceitacao:**

- [ ] Titulo "COR" colapsavel
- [ ] Checkboxes com as cores: Off White, Marrom, Azul Claro, Branco, Laranja, Verde Musgo, Preto
- [ ] Cada checkbox exibe amostra visual da cor (color swatch) + nome
- [ ] Multipla selecao permitida
- [ ] Galeria atualiza ao marcar/desmarcar

---

### TASK 4.5 — Filtro por Tamanho

**Prioridade:** S
**Descricao:** Implementar filtro de tamanho com checkboxes.
**Criterios de Aceitacao:**

- [ ] Titulo "TAMANHO" colapsavel
- [ ] Checkboxes com os tamanhos disponiveis (PP, P, M, G, GG e variacoes)
- [ ] Multipla selecao permitida
- [ ] Galeria atualiza ao marcar/desmarcar
- [ ] Tamanhos indisponiveis ficam desabilitados ou com indicador visual

---

### TASK 4.6 — Galeria de Produtos (Grid)

**Prioridade:** M
**Descricao:** Exibir galeria de produtos em formato grid, responsiva e integrada aos filtros.
**Criterios de Aceitacao:**

- [ ] Grid de cards: 4 colunas (desktop), 3 (tablet), 2 (mobile)
- [ ] Cada card exibe: imagem, nome, preco
- [ ] Produtos esgotados exibem badge "Esgotado"
- [ ] Cards clicaveis → navegam para pagina de detalhe
- [ ] Galeria reage aos filtros ativos (tipo, preco, cor, tamanho)
- [ ] Estado vazio: mensagem "Nenhum produto encontrado" quando filtros nao retornam resultados

---

## EPIC 5 — PAGINA DE DETALHE DO PRODUTO

### TASK 5.1 — Galeria de Imagens do Produto

**Prioridade:** M
**Descricao:** Implementar galeria de imagens com navegacao na pagina de detalhe do produto.
**Criterios de Aceitacao:**

- [ ] Exibe imagem principal grande
- [ ] Botao "Next Item" navega entre as imagens
- [ ] Thumbnails clicaveis (se houver multiplas imagens)
- [ ] Suporta zoom ao clicar/hover na imagem principal
- [ ] Responsivo: ocupa largura total em mobile
- [ ] Lazy loading nas imagens

---

### TASK 5.2 — Informacoes Basicas do Produto (Nome, Preco)

**Prioridade:** M
**Descricao:** Exibir nome e preco do produto de forma destacada.
**Criterios de Aceitacao:**

- [ ] Nome do produto como heading (H1)
- [ ] Preco formatado em reais (ex: R$ 429,00)
- [ ] Label "Preco" acessivel (sr-only ou aria)
- [ ] Preco atualiza se variacao de cor/tamanho afetar valor (futuro)

---

### TASK 5.3 — Descricao Editorial do Produto

**Prioridade:** M
**Descricao:** Exibir descricao completa do produto com texto editorial, composicao e conceito.
**Criterios de Aceitacao:**

- [ ] Texto de descricao com formatacao rica (paragrafos)
- [ ] Informacao de composicao destacada (ex: "100% algodao egipcio")
- [ ] Conteudo editavel via CMS/painel admin
- [ ] Texto responsivo com boa legibilidade

---

### TASK 5.4 — Guia de Tamanhos

**Prioridade:** M
**Descricao:** Exibir tabela de medidas em centimetros para cada tamanho disponivel.
**Criterios de Aceitacao:**

- [ ] Titulo "Tamanhos em centimetros:"
- [ ] Lista de tamanhos com medidas: PP, P, M, G, GG
- [ ] Cada tamanho exibe: busto (cm) e cintura (cm)
- [ ] Referencia do modelo (ex: "Modelo veste tamanho PP")
- [ ] Dados editaveis por produto via CMS

---

### TASK 5.5 — Seletor de Cor (Radio Buttons)

**Prioridade:** M
**Descricao:** Implementar seletor de cor do produto usando radio buttons com indicador visual.
**Criterios de Aceitacao:**

- [ ] Label "COR" acima do seletor
- [ ] Radio buttons com nome de cada cor (ex: Off White, Marrom)
- [ ] Indicador visual de cor (swatch/circulo colorido) ao lado do nome
- [ ] Apenas uma cor selecionavel por vez
- [ ] Selecao de cor obrigatoria antes de adicionar ao carrinho
- [ ] Cores disponiveis variam por produto (dinamico)
- [ ] Cor selecionada tem destaque visual (borda, check)

---

### TASK 5.6 — Seletor de Tamanho (Dropdown)

**Prioridade:** M
**Descricao:** Implementar seletor de tamanho do produto como dropdown/combobox.
**Criterios de Aceitacao:**

- [ ] Label "TAMANHO" acima do seletor
- [ ] Dropdown com placeholder "Selecionar"
- [ ] Opcoes: PP, P, M, G, GG (varia por produto)
- [ ] Selecao de tamanho obrigatoria antes de adicionar ao carrinho
- [ ] Tamanhos esgotados ficam desabilitados ou marcados como "Esgotado"
- [ ] Validacao: exibe mensagem de erro se tentar comprar sem selecionar tamanho

---

### TASK 5.7 — Seletor de Quantidade

**Prioridade:** M
**Descricao:** Implementar campo de quantidade com botoes de incremento e decremento.
**Criterios de Aceitacao:**

- [ ] Campo numerico com valor padrao "1"
- [ ] Botao "+" (Adicionar 1) incrementa a quantidade
- [ ] Botao "-" (Remover 1) decrementa a quantidade
- [ ] Quantidade minima: 1 (botao "-" desabilitado quando = 1)
- [ ] Quantidade maxima: limitada pelo estoque disponivel
- [ ] Aceita digitacao direta no campo
- [ ] Validacao: impede valores negativos, zero ou nao numericos

---

### TASK 5.8 — Botao Adicionar ao Carrinho

**Prioridade:** M
**Descricao:** Implementar botao que adiciona o produto ao carrinho com as variacoes selecionadas.
**Criterios de Aceitacao:**

- [ ] Texto: "Adicionar ao carrinho"
- [ ] Ao clicar, valida se cor e tamanho foram selecionados
- [ ] Se valido: adiciona ao carrinho com cor, tamanho e quantidade escolhidos
- [ ] Feedback visual: animacao no icone do carrinho (badge atualiza)
- [ ] Feedback ao usuario: toast/notificacao "Produto adicionado ao carrinho"
- [ ] Botao desabilitado enquanto variacoes obrigatorias nao forem selecionadas
- [ ] **Estado Esgotado:** botao substituido por "Esgotado" (desabilitado)

---

### TASK 5.9 — Botao Comprar (Buy Now)

**Prioridade:** M
**Descricao:** Implementar botao de compra direta que adiciona ao carrinho e redireciona para o checkout.
**Criterios de Aceitacao:**

- [ ] Texto: "Comprar"
- [ ] Ao clicar, valida cor e tamanho selecionados
- [ ] Se valido: adiciona ao carrinho E redireciona para o checkout
- [ ] Mesmo comportamento de validacao do botao "Adicionar ao carrinho"
- [ ] Estilo visual diferenciado do botao de adicionar (hierarquia de botoes)

---

### TASK 5.10 — Simulador de Frete

**Prioridade:** M
**Descricao:** Implementar calculadora de frete com campo de CEP, botao de calculo e link para busca de CEP.
**Criterios de Aceitacao:**

- [ ] Titulo: "Calcule seu frete"
- [ ] Campo de texto com placeholder "CEP"
- [ ] Mascara de CEP no campo (XXXXX-XXX)
- [ ] Botao "Calcular" ao lado do campo
- [ ] Ao calcular: exibe opcoes de frete com prazo e valor (ex: Correios PAC, SEDEX)
- [ ] Link "Nao sei meu CEP" abre site dos Correios (buscacepinter.correios.com.br) em nova aba
- [ ] Validacao: exibe erro para CEP invalido ou incompleto
- [ ] Loading state enquanto consulta a API de frete
- [ ] **Integracao:** API dos Correios ou servico de calculo de frete

---

### TASK 5.11 — Botoes de Compartilhamento Social

**Prioridade:** C
**Descricao:** Exibir botoes para compartilhar o produto em redes sociais.
**Criterios de Aceitacao:**

- [ ] Navegacao "Compartilhar nas redes sociais"
- [ ] Botoes para: WhatsApp, Facebook, Twitter/X, Pinterest (ou redes configuradas)
- [ ] Cada botao abre janela de compartilhamento com link e titulo do produto
- [ ] Icones visuais correspondentes a cada rede

---

### TASK 5.12 — Carrossel de Produtos Relacionados

**Prioridade:** S
**Descricao:** Exibir carrossel de produtos relacionados no final da pagina de detalhe.
**Criterios de Aceitacao:**

- [ ] Carrossel horizontal com produtos do catalogo (excluindo o atual)
- [ ] Botoes "Produto Anterior" e "Proximo Produto"
- [ ] Cards com imagem, nome e preco
- [ ] Produtos esgotados exibem badge "Esgotado"
- [ ] Clicar no card navega para o respectivo produto

---

## EPIC 6 — CARRINHO DE COMPRAS

### TASK 6.1 — Pagina do Carrinho

**Prioridade:** M
**Descricao:** Implementar pagina do carrinho com listagem de itens e resumo do pedido.
**Criterios de Aceitacao:**

- [ ] Titulo tematico: "proximos capitulos:"
- [ ] Lista de itens no carrinho com: imagem, nome, cor, tamanho, quantidade, preco unitario, subtotal
- [ ] Botao de remover item individual
- [ ] Seletor de quantidade editavel por item
- [ ] Atualiza subtotais em tempo real ao alterar quantidades

---

### TASK 6.2 — Resumo do Pedido

**Prioridade:** M
**Descricao:** Implementar sidebar com resumo financeiro do pedido.
**Criterios de Aceitacao:**

- [ ] Titulo: "Resumo do pedido"
- [ ] Subtotal de todos os itens
- [ ] Valor do frete (se calculado)
- [ ] Campo para codigo de cupom de desconto
- [ ] Total geral
- [ ] Botao "Finalizar compra" / "Ir para checkout"

---

### TASK 6.3 — Estado Vazio do Carrinho

**Prioridade:** M
**Descricao:** Exibir mensagem branded quando o carrinho esta vazio.
**Criterios de Aceitacao:**

- [ ] Mensagem: "DEPOIS DO PONTO E VIRGULA, VOCE CONTA A HISTORIA. QUAIS SEUS PROXIMOS CAPITULOS? QUAL SUA PROXIMA HISTORIA?"
- [ ] Estilo editorial alinhado a identidade da marca
- [ ] Link/botao sugerindo voltar a loja

---

### TASK 6.4 — Aplicacao de Cupom de Desconto

**Prioridade:** M
**Descricao:** Implementar campo de cupom no carrinho/checkout com validacao.
**Criterios de Aceitacao:**

- [ ] Campo de texto para digitar o codigo do cupom
- [ ] Botao "Aplicar"
- [ ] Cupom valido: exibe desconto aplicado e recalcula total
- [ ] Cupom invalido/expirado: exibe mensagem de erro
- [ ] Cupom "primeiracompra" aplica 10% de desconto
- [ ] Validacao de uso unico por cliente (primeira compra)

---

## EPIC 7 — AUTENTICACAO

### TASK 7.1 — Fluxo de Login

**Prioridade:** S
**Descricao:** Implementar tela de login para acesso a area do cliente.
**Criterios de Aceitacao:**

- [ ] Modal ou pagina dedicada de login
- [ ] Campos: email e senha
- [ ] Botao "Entrar"
- [ ] Link "Esqueci minha senha"
- [ ] Validacao de campos obrigatorios
- [ ] Mensagem de erro para credenciais invalidas
- [ ] Redirecionamento apos login bem-sucedido

---

### TASK 7.2 — Fluxo de Cadastro

**Prioridade:** S
**Descricao:** Implementar tela de cadastro de novo usuario.
**Criterios de Aceitacao:**

- [ ] Campos: nome, email, senha, confirmacao de senha
- [ ] Validacoes: email valido, senha forte, campos obrigatorios
- [ ] Botao "Criar conta"
- [ ] Email de confirmacao de cadastro
- [ ] Redirecionamento apos cadastro bem-sucedido
- [ ] Link para login (ja tem conta?)

---

### TASK 7.3 — Recuperacao de Senha

**Prioridade:** S
**Descricao:** Implementar fluxo de recuperacao de senha por email.
**Criterios de Aceitacao:**

- [ ] Campo de email
- [ ] Botao "Enviar link de recuperacao"
- [ ] Email enviado com link temporario para redefinir senha
- [ ] Pagina de redefinicao: nova senha + confirmacao
- [ ] Mensagem de sucesso apos redefinicao

---

## EPIC 8 — QUEM CRIA (PAGINA SOBRE)

### TASK 8.1 — Pagina Quem Cria

**Prioridade:** S
**Descricao:** Implementar pagina institucional "Quem Cria" com biografias das criadoras.
**Criterios de Aceitacao:**

- [ ] Heading "quem CRIA"
- [ ] Foto da Giulia com texto biografico/editorial
- [ ] Foto da Maria Clara com texto biografico/editorial
- [ ] Layout editorial com fotos em destaque
- [ ] Secao "ALFAIATARIA CRIATIVA"
- [ ] Secao de encerramento: "VOCE CONTA A HISTORIA" com logo
- [ ] Conteudo editavel via CMS
- [ ] Responsivo

---

## EPIC 9 — CAPITULOS (LIVRO GILDA;)

### TASK 9.1 — Pagina Livro GILDA; — Estrutura

**Prioridade:** S
**Descricao:** Implementar pagina editorial "Livro GILDA;" com capitulos da historia da marca.
**Criterios de Aceitacao:**

- [ ] Titulo "LIVRO GILDA;"
- [ ] Secoes separadas por capitulo (1, 2, 3)
- [ ] Layout editorial com tipografia grande e imagens de fundo
- [ ] Responsivo

---

### TASK 9.2 — Capitulo 1 — Borboleta 88

**Prioridade:** S
**Descricao:** Implementar secao do Capitulo 1 com narrativa e galeria.
**Criterios de Aceitacao:**

- [ ] Heading "CAPITULO 1"
- [ ] Texto narrativo sobre a borboleta 88 e transformacao
- [ ] Galeria de 8 imagens clicaveis
- [ ] Lightbox ao clicar nas imagens (abre visualizacao ampliada)
- [ ] Link "pecas disponiveis" direcionando para produto da colecao

---

### TASK 9.3 — Capitulo 2 — Ritual Brasileiro

**Prioridade:** S
**Descricao:** Implementar secao do Capitulo 2 com narrativa e galeria.
**Criterios de Aceitacao:**

- [ ] Heading "CAPITULO 2"
- [ ] Texto narrativo sobre o cafe e ritual brasileiro
- [ ] Galeria de 8 imagens clicaveis com lightbox
- [ ] Link "pecas disponiveis" direcionando para produto da colecao

---

### TASK 9.4 — Capitulo 3 — Colecao Atual

**Prioridade:** S
**Descricao:** Implementar secao do Capitulo 3 com imagens editoriais e links para produtos.
**Criterios de Aceitacao:**

- [ ] Heading "CAPITULO 3"
- [ ] Imagens editoriais de lookbook
- [ ] 3 links "pecas disponiveis" direcionando para: Camisa Grao, Bermuda Envolto, Camisa Casulo
- [ ] Galeria com 8 imagens clicaveis com lightbox
- [ ] Logo da marca no encerramento

---

## EPIC 10 — FOOTER (RODAPE GLOBAL)

### TASK 10.1 — Footer com Informacoes de Contato

**Prioridade:** M
**Descricao:** Implementar rodape global com dados de contato e informacoes empresariais.
**Criterios de Aceitacao:**

- [ ] Titulo: "CONTATO"
- [ ] Telefone: 41.991008303
- [ ] Email: mundogilda@gmail.com (clicavel — abre cliente de email)
- [ ] Instagram: @gildapontoevirgula
- [ ] Dados empresariais: GILDA ALFAIATARIA LTDA, CNPJ 45.822.638/0001-09, Curitiba/PR CEP 82.015-384
- [ ] Footer identico em todas as paginas
- [ ] Responsivo

---

### TASK 10.2 — Icones de Metodos de Pagamento

**Prioridade:** M
**Descricao:** Exibir icones dos metodos de pagamento aceitos no footer.
**Criterios de Aceitacao:**

- [ ] Texto: "Aceitamos os seguintes metodos de pagamento:"
- [ ] Icones visuais: Visa, MasterCard, Pix
- [ ] Icones com alt text descritivo
- [ ] Alinhados e responsivos

---

### TASK 10.3 — Copyright e Creditos

**Prioridade:** C
**Descricao:** Exibir linha de copyright e creditos da plataforma.
**Criterios de Aceitacao:**

- [ ] Texto: "(c) 2006 by GILDA;"
- [ ] Link "Built on Wix Studio" (se aplicavel na nova plataforma)

---

## EPIC 11 — FUNCIONALIDADES TRANSVERSAIS

### TASK 11.1 — Responsividade Global

**Prioridade:** M
**Descricao:** Garantir que todo o site seja responsivo e funcional em desktop, tablet e mobile.
**Criterios de Aceitacao:**

- [ ] Breakpoints: mobile (< 768px), tablet (768-1023px), desktop (>= 1024px)
- [ ] Nenhum scroll horizontal em nenhuma resolucao
- [ ] Textos legiveis sem zoom em mobile
- [ ] Botoes e areas clicaveis com tamanho minimo de 44x44px em touch
- [ ] Imagens adaptativas (srcset ou picture)
- [ ] Menus e filtros adaptam ao formato mobile

---

### TASK 11.2 — Gestao de Estoque (Status Esgotado)

**Prioridade:** M
**Descricao:** Implementar logica de controle de estoque com status visual de produto esgotado.
**Criterios de Aceitacao:**

- [ ] Produtos sem estoque exibem badge "Esgotado" no card do catalogo
- [ ] Botao "Adicionar ao carrinho" substituido por "Esgotado" (desabilitado)
- [ ] Na pagina de detalhe: botoes de compra desabilitados
- [ ] Tamanhos/cores esgotados ficam indisponiveis nos seletores
- [ ] Admin pode atualizar estoque via painel

---

### TASK 11.3 — SEO Basico

**Prioridade:** S
**Descricao:** Implementar fundamentos de SEO em todas as paginas.
**Criterios de Aceitacao:**

- [ ] Title tags unicos e descritivos por pagina
- [ ] Meta descriptions por pagina
- [ ] Alt text em todas as imagens
- [ ] URLs amigaveis (slugs em portugues: /product-page/camisa-grao)
- [ ] Heading hierarchy correta (H1 > H2 > H3)
- [ ] Schema markup para produtos (Product, Offer)
- [ ] Sitemap.xml gerado automaticamente

---

### TASK 11.4 — Acessibilidade (WCAG)

**Prioridade:** S
**Descricao:** Garantir conformidade basica de acessibilidade.
**Criterios de Aceitacao:**

- [ ] Skip to content funcional
- [ ] Navegacao completa por teclado
- [ ] Contraste minimo 4.5:1 para textos
- [ ] Aria labels em botoes de icone (carrinho, login, setas)
- [ ] Alt text descritivo em imagens
- [ ] Formularios com labels associados
- [ ] Focus indicators visiveis

---

### TASK 11.5 — Integracao de Pagamento

**Prioridade:** M
**Descricao:** Configurar gateway de pagamento para aceitar Visa, MasterCard e Pix.
**Criterios de Aceitacao:**

- [ ] Checkout integrado com gateway (ex: Mercado Pago, PagSeguro, Stripe)
- [ ] Aceita cartao de credito Visa e MasterCard
- [ ] Aceita pagamento via Pix (QR code ou copia-e-cola)
- [ ] Pagina de checkout segura (HTTPS)
- [ ] Confirmacao de pedido por email
- [ ] Pagina de sucesso apos pagamento

---

### TASK 11.6 — Integracao de Frete (API Correios)

**Prioridade:** M
**Descricao:** Integrar API de calculo de frete para consulta por CEP.
**Criterios de Aceitacao:**

- [ ] Consulta automatica de opcoes de frete ao informar CEP
- [ ] Exibe modalidades (PAC, SEDEX, etc.) com prazo e valor
- [ ] Calculo baseado no peso/dimensoes do produto
- [ ] Fallback para erro de API (mensagem amigavel)
- [ ] Cache de consultas para o mesmo CEP

---

## RESUMO QUANTITATIVO

| Epic      | Modulo                | Total de Tasks | Must   | Should | Could |
| --------- | --------------------- | -------------- | ------ | ------ | ----- | --- |
| 1         | Navegacao & Estrutura | 7              | 3      | 1      | 3     |
| 2         | Home Page             | 5              | 1      | 3      | 1     |
| 3         | Catalogo de Produtos  | 3              | 2      | 0      | 1     |
| 4         | Todos os Produtos     | 6              | 2      | 4      | 0     |
| 5         | Detalhe do Produto    | 12             | 9      | 1      | 2     |
| 6         | Carrinho de Compras   | 4              | 4      | 0      | 0     |
| 7         | Autenticacao          | 3              | 0      | 3      | 0     |
| 8         | Quem Cria             | 1              | 0      | 1      | 0     |
| 9         | Capitulos             | 4              | 0      | 4      | 0     |
| 10        | Footer                | 3              | 2      | 0      | 1     |
| 11        | Transversais          | 6              | 3      | 2      | 0     | 1   |
| **TOTAL** |                       | **54**         | **26** | **19** | **8** |

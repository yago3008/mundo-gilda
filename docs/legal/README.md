# Textos legais (RASCUNHOS)

Rascunhos das páginas exigidas pela LGPD e pelo Código de Defesa do Consumidor (item 7 de [requisitos-cliente.md](../requisitos-cliente.md)). **Nenhum deles está pronto para publicar**: são modelos para as donas da GILDA; e o contador/advogado revisarem.

| Arquivo | Página no site | Observação |
|---|---|---|
| [politica-de-privacidade.md](politica-de-privacidade.md) | `/politica-de-privacidade/` | dados do checkout, Correios/Infixs, consulta de CEP, pagamento, Meta Pixel, e-mail (SMTP), cookies, direitos LGPD |
| [trocas-e-devolucoes.md](trocas-e-devolucoes.md) | `/trocas-e-devolucoes/` | arrependimento de 7 dias (CDC art. 49), troca por tamanho, defeito (90 dias), reembolso |
| [termos-de-uso.md](termos-de-uso.md) | `/termos-de-uso/` | condições de venda, cupom, estoque e **entrega** (faz o papel da política de frete) |

## O que as donas precisam decidir ou informar

Tudo o que está marcado com **[PREENCHER]** nos arquivos. Os principais:

- Endereço completo (rua e número) e os contatos oficiais (e-mail e WhatsApp).
- Gateway de pagamento e formas de pagamento/parcelamento.
- Hospedagem e serviço de envio de e-mail (os fornecedores de produção).
- **Trocas:** prazo para troca por tamanho/cor, quem paga o frete da troca, vale-compras, prazos de reembolso.
- Frete da devolução no arrependimento: código de logística reversa dos Correios (precisa de contrato) ou reembolso da postagem.
- Prazo de postagem (hoje o site calcula 2 dias úteis) e frete grátis, se houver.
- Regras do cupom `primeiracompra` (acumula? vale em promoção?).
- Nota fiscal: como é emitida (com o contador).
- Se recebem medidas corporais das clientes (ajustes/sob medida) e como tratam peças personalizadas.

## Notas para a revisão

- **Mailpit não aparece na política** de propósito: é só a caixa de e-mails de teste do ambiente local e nunca recebe dados de clientes reais. Em produção, os e-mails saem por um provedor SMTP, que é o que a política cita.
- Os cookies listados batem com o que o site grava hoje. Os de marketing (`_fbp`, `_fbc` da Meta e `sbjs_*` da atribuição de pedidos do WooCommerce) só são gravados depois do aceite no banner (`wp-content/mu-plugins/gilda-consent.php`).
- O texto `[gilda_consent_preferences]` na política é um shortcode: no site ele vira o botão "Preferências de cookies".
- Prazos legais usados: 7 dias de arrependimento (CDC art. 49), 90 dias para defeito em produto durável (CDC art. 26, II), 30 dias para sanar o defeito (CDC art. 18), 6 meses de registros de acesso (Marco Civil art. 15), 5 anos para documentos fiscais/pedidos (a confirmar com o contador).

## Levar para o WordPress

```bash
docker compose run --rm wpcli wp eval-file /scripts/import-legal-pages.php --user=admin
```

Cria ou atualiza as três páginas **como rascunho** (nunca publica e nunca mexe numa página que já esteja publicada). Depois é revisar no painel, apagar o aviso de RASCUNHO, preencher e publicar. Ao publicar os termos, ligue a página em WooCommerce → Configurações → Avançado → "Termos e condições" (o script não faz isso para o checkout não linkar um rascunho).

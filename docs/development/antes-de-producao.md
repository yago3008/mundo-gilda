# Antes de mandar para produção — GILDA;

O que falta para a loja nova substituir o Wix. Marque `[x]` conforme for fazendo.
O passo a passo técnico está em [deploy.md](deploy.md); o que depende das donas, em detalhe, em [requisitos-cliente.md](../requisitos-cliente.md).

## 1. Depende das donas (bloqueia o lançamento)

- [ ] **Pagamento real:** provedor (o que usam no Wix), credenciais de produção e de teste, parcelamento, parcela mínima e desconto no Pix. Hoje só o pagamento de teste está ligado.
- [ ] **Hospedagem** escolhida (com SSH + WP-CLI) e quem paga.
- [ ] **Domínio e DNS:** acesso ao Registro.br e a quem altera o DNS.
- [ ] **E-mail da loja:** endereço remetente e um serviço de envio (Brevo ou Amazon SES) configurado no DNS, para não cair no spam.
- [ ] **Textos legais:** preencher os `[PREENCHER]` de Termos de uso e de Trocas e devoluções (donas/advogado). Depois: publicar as páginas e ligar o aceite dos termos no checkout.
- [ ] **Envio:** endereço completo de onde saem as encomendas, contrato com os Correios ou plano do Meu Frete, e aceitar os termos do plugin dos Correios no painel (elas aceitam, não nós).
- [ ] **Nota fiscal:** emitem NF-e? Por qual sistema?

## 2. Do nosso lado

- [x] Guia de deploy completo: tema `gilda` incluído (antes ele copiava só os mu-plugins), todos os passos de carga do `setup.sh` e os scripts aceitando `GILDA_DATA_DIR` / `GILDA_LEGAL_DIR` fora do Docker.
- [x] Bateria de segurança no local: **78 PASS, 0 FAIL**. O único aviso é "HTTP sem TLS", normal no local (sem HTTPS); some ao rodar contra o site real.
- [x] Testes ponta a ponta: **19 de 19** passando (cadastro, login, compra completa, frete, cupom, filtros, painel das donas).
- [ ] Integrar o provedor de pagamento assim que as credenciais chegarem e testar uma compra no modo de teste dele.
- [ ] Depois do deploy: rodar `./scripts/security-check.sh https://www.mundogilda.com.br` e chegar em **0 FAIL e 0 WARN**.
- [ ] (Opcional) Ambiente de teste online para as donas aprovarem antes da virada.

## 3. Dia da virada

Detalhes de cada passo em [deploy.md, seção 8](deploy.md#8-virada-dia-d).

- [ ] Congelar o Wix e importar o estoque do dia, os clientes e os pedidos em aberto.
- [ ] Fazer uma compra real de valor baixo e estornar.
- [ ] Conferir que os e-mails chegam fora do spam, o frete, o cupom e o Pixel.
- [ ] Baixar o TTL do DNS um dia antes, apontar o domínio e conferir os redirecionamentos das URLs antigas do Wix.
- [ ] Enviar o sitemap ao Search Console e manter o Wix ativo por alguns dias.

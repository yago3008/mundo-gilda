import { expect, type Locator, type Page } from '@playwright/test';
import { checkoutData } from './env';

/**
 * Passos de compra com seletores por papel/rótulo/texto, para sobreviver às mudanças do tema.
 * Funcionam tanto no checkout de blocos quanto no clássico (shortcode) do WooCommerce.
 */

export const PRODUCT_SLUG = 'camisa-grao';
export const PRODUCT_URL = `/product-page/${PRODUCT_SLUG}/`;

export interface Variation {
  variation_id: number;
  attributes: Record<string, string>;
  is_in_stock: boolean;
  max_qty: number | '';
  display_price: number;
}

/** Variações do produto (dados que o WooCommerce põe no formulário de compra). */
export async function readVariations(page: Page): Promise<Variation[]> {
  const raw = await page.locator('form.variations_form').first().getAttribute('data-product_variations');
  expect(raw, 'formulário de produto variável (data-product_variations)').toBeTruthy();
  return JSON.parse(raw!) as Variation[];
}

/** A variação com mais estoque, para os testes não esgotarem uma combinação. */
export async function pickInStockVariation(page: Page): Promise<Variation> {
  const inStock = (await readVariations(page)).filter((v) => v.is_in_stock);
  expect(inStock.length, 'alguma variação em estoque').toBeGreaterThan(0);
  return inStock.sort((a, b) => (Number(b.max_qty) || 0) - (Number(a.max_qty) || 0))[0];
}

/** Escolhe cor e tamanho como a cliente faria (bolinha/botão do tema ou o <select> padrão). */
export async function selectVariation(page: Page, variation: Variation): Promise<void> {
  for (const [attribute, slug] of Object.entries(variation.attributes)) {
    const select = page.locator(`form.variations_form select[name="${attribute}"]`);
    const label = ((await select.locator(`option[value="${slug}"]`).textContent()) || slug).trim();
    const radio = page.locator('form.variations_form').getByRole('radio', { name: label, exact: true });
    if (await radio.count()) {
      await radio.first().check();
    } else {
      await select.selectOption(slug);
    }
  }
  // O WooCommerce só libera os botões depois de achar a variação.
  await expect(page.locator('input[name="variation_id"]')).toHaveValue(String(variation.variation_id));
  await expect(addToCartButton(page)).not.toHaveClass(/\bdisabled\b/);
}

export function addToCartButton(page: Page): Locator {
  return page.getByRole('button', { name: /adicionar ao carrinho/i });
}

/** Botão de compra direta (contrato do mu-plugin gilda-buy-now: name="gilda_buy_now"). */
export function buyNowButton(page: Page): Locator {
  return page.locator('form.cart button[name="gilda_buy_now"]');
}

/** Itens do carrinho da sessão do navegador (Store API, mesmos cookies da página). */
export async function cartItems(page: Page): Promise<{ id: number; name: string; quantity: number }[]> {
  const res = await page.request.get('/wp-json/wc/store/v1/cart');
  expect(res.ok()).toBeTruthy();
  return (await res.json()).items;
}

/** Leva 1 unidade direto para o checkout pelo link documentado (?gilda_buy_now=1&add-to-cart=<id>). */
export async function buyNowByUrl(page: Page, variationId: number): Promise<void> {
  await page.goto(`/?gilda_buy_now=1&add-to-cart=${variationId}&quantity=1`);
  await expect(page).toHaveURL(/\/finalizar-compra\/?/);
}

export async function waitForCheckout(page: Page): Promise<void> {
  await expect(page).toHaveURL(/\/finalizar-compra\/?/);
  await expect(page.getByRole('button', { name: /finalizar pedido/i })).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('.wc-block-checkout.is-loading, .blockUI.blockOverlay')).toHaveCount(0, { timeout: 30_000 });
}

function field(page: Page, name: string | RegExp): Locator {
  return page.getByRole('textbox', { name, exact: typeof name === 'string' }).first();
}

const digits = (v: string) => v.replace(/\D/g, '');

/**
 * Preenche e confere. O checkout de blocos re-renderiza (ex.: depois do autopreenchimento do CEP)
 * e pode apagar o que já foi digitado; campos com máscara são digitados tecla a tecla.
 */
async function fillVerified(input: Locator, value: string, opts: { masked?: boolean } = {}): Promise<void> {
  // Máscara: compara só os dígitos (o celular pode ganhar o +55 na frente).
  const same = (current: string) => (opts.masked ? digits(current).endsWith(digits(value)) : current === value);
  await expect(async () => {
    if (!same(await input.inputValue())) {
      await input.fill('');
      if (opts.masked) {
        await input.pressSequentially(digits(value), { delay: 30 });
      } else {
        await input.fill(value);
      }
      await input.blur();
    }
    expect(same(await input.inputValue()), `campo com "${value}"`).toBe(true);
  }).toPass({ timeout: 20_000, intervals: [300, 700, 1500] });
}

async function waitIdle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle').catch(() => {});
  await expect(page.locator('.blockUI.blockOverlay, .wc-block-components-loading-mask, .wc-block-checkout__form--loading')).toHaveCount(0, { timeout: 30_000 });
}

export interface AddressCheck {
  street: string;
  city: string;
}

/**
 * Preenche o checkout. O CEP vem primeiro e deve preencher rua/bairro/cidade sozinho
 * (o plugin "Campos Checkout Brasileiro" oferece "Usar o endereço: …").
 */
export async function fillCheckout(page: Page, email: string): Promise<AddressCheck> {
  await field(page, 'Endereço de e-mail').fill(email);

  const cep = field(page, /^(CEP|Código postal)/);
  // No checkout de blocos, o CEP dispara um update-customer (com atraso). Se a sugestão de endereço
  // for aceita antes da resposta chegar, a resposta apaga rua/cidade. Uma pessoa leva mais de 1 s
  // para clicar; aqui esperamos a resposta (no clássico ela não existe e o timeout curto basta).
  const synced = page
    .waitForResponse((r) => r.url().includes('/wc/store/v1/cart/update-customer'), { timeout: 8_000 })
    .catch(() => null);
  await cep.fill(checkoutData.cep);
  await cep.blur();

  const suggestion = page.getByRole('checkbox', { name: /usar o endere[çc]o/i });
  await expect(suggestion, 'sugestão de endereço pelo CEP (autopreenchimento)').toBeVisible({ timeout: 20_000 });
  await synced;
  await waitIdle(page);
  await suggestion.check();

  const street = field(page, 'Endereço');
  await expect(street, 'rua preenchida pelo CEP').toHaveValue(/paulista/i);
  await expect(field(page, 'Cidade'), 'cidade preenchida pelo CEP').toHaveValue(/s[ãa]o paulo/i);
  await expect(field(page, 'Bairro'), 'bairro preenchido pelo CEP').not.toHaveValue('');

  await waitIdle(page);
  const fields: [Locator, string, boolean][] = [
    [field(page, 'Nome'), checkoutData.firstName, false],
    [field(page, 'Sobrenome'), checkoutData.lastName, false],
    [field(page, /^CPF/), checkoutData.cpf, true],
    [field(page, 'Número'), checkoutData.number, false],
    [field(page, /celular|telefone/i), checkoutData.phone, true],
  ];
  for (const [input, value, masked] of fields) {
    await fillVerified(input, value, { masked });
  }
  await waitIdle(page);
  // Confere de novo: nada pode ter sido apagado por uma re-renderização (inclusive o CEP).
  await fillVerified(cep, checkoutData.cep, { masked: true });
  for (const [input, value, masked] of fields) {
    await fillVerified(input, value, { masked });
  }
  await fillVerified(field(page, 'Endereço de e-mail'), email);
  await expect(street, 'rua continua preenchida').toHaveValue(/paulista/i);
  await waitIdle(page);

  return { street: await street.inputValue(), city: await field(page, 'Cidade').inputValue() };
}

/** Opções de frete (PAC/SEDEX) com preço. Retorna o rótulo acessível de cada uma. */
export async function shippingOptions(page: Page): Promise<{ pac: Locator; sedex: Locator }> {
  const pac = page.getByRole('radio', { name: /^PAC\b/ });
  const sedex = page.getByRole('radio', { name: /^SEDEX\b/ });
  await expect(pac, 'frete PAC').toBeVisible({ timeout: 30_000 });
  await expect(sedex, 'frete SEDEX').toBeVisible();
  return { pac, sedex };
}

/** Abre o campo de cupom (blocos: "Adicionar cupons"; clássico: "Clique aqui para digitar seu cupom") e aplica. */
export async function applyCoupon(page: Page, code: string): Promise<void> {
  const input = page.getByRole('textbox', { name: /digite o c[óo]digo|c[óo]digo do cupom|cupom/i }).first();
  if (!(await input.isVisible())) {
    await page.getByRole('button', { name: /adicionar cupons|c[óo]digo de cupom|digitar seu cupom/i }).first().click();
  }
  await expect(input).toBeVisible();
  await input.fill(code);
  await page.getByRole('button', { name: /^aplicar/i }).first().click();
  await waitIdle(page);
}

export async function choosePaymentAndPlaceOrder(page: Page): Promise<void> {
  const testGateway = page.getByRole('radio', { name: /pagamento de teste/i });
  if (await testGateway.count()) {
    await testGateway.check();
  } else {
    // Com um único meio de pagamento o checkout clássico não mostra radio, só o texto.
    await expect(page.getByText(/pagamento de teste/i).first()).toBeVisible();
  }
  await page.getByRole('button', { name: /finalizar pedido/i }).click();
}

/** Formata um valor como no site (R$ 1.234,56 → "1.234,56"). */
export function brl(value: number): string {
  return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

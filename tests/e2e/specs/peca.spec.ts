import fs from 'node:fs';
import path from 'node:path';
import { test, expect, type Browser } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { strongPassword, uniqueEmail } from '../helpers/env';
import { loginAsOwner, pickPhotos } from '../helpers/painel';

/**
 * Painel das donas com o visual da GILDA; (docs/painel-gilda.md): a dona entra pelo login da marca, vê o
 * Início com números de verdade e cadastra uma peça variável pela tela "Nova peça" (2 cores × 3 tamanhos,
 * com estoque e capítulo). A peça aparece na loja, no filtro do capítulo e na página dela com as bolinhas
 * certas; mudar o estoque de uma combinação mantém o ID da variação (pedidos antigos continuam valendo).
 */

const IMAGES = path.resolve(__dirname, '..', '..', '..', 'data', 'images');
const stamp = Date.now().toString(36);
const pieceName = `Camisa Teste ${stamp}`;
const STAMP = stamp.toUpperCase();
const baseSku = `CAM-TESTE-${STAMP}`; // categoria Camisas + nome sem "Camisa"
const newColorCode = `T${STAMP[0]}`; // "Teste <stamp>": iniciais das duas palavras
const manualSku = `CAM-MANUAL-${STAMP}`;
const newColor = { name: `Teste ${stamp}`, hex: '#2a6f97' };
const created = { userId: 0, productId: 0, media: [] as number[] };
const owner = { username: `dona-peca-${stamp}`, password: strongPassword() };

test.describe.configure({ mode: 'serial' });

test.beforeAll(async () => {
  const api = await AdminApi.login();
  try {
    const files = fs.readdirSync(IMAGES).filter((f) => /\.(webp|jpe?g|png)$/i.test(f)).slice(0, 2);
    for (const file of files) created.media.push((await api.uploadImage(path.join(IMAGES, file))).id);
    created.userId = (await api.createUser({ ...owner, email: uniqueEmail('dona'), role: 'gilda_dona', firstName: 'Giulia' })).id;
  } finally {
    await api.dispose();
  }
});

test.afterAll(async () => {
  const api = await AdminApi.login();
  try {
    if (created.productId) await api.deleteProduct(created.productId);
    for (const id of created.media) await api.deleteMedia(id);
    await api.deleteAttributeTerm('pa_cor', newColor.name);
    if (created.userId) await api.deleteUser(created.userId);
  } finally {
    await api.dispose();
  }
});

async function visitor(browser: Browser) {
  const context = await browser.newContext();
  return { page: await context.newPage(), close: () => context.close() };
}

test('login da GILDA; e Início com os números da loja', async ({ page }) => {
  await page.goto('/wp-login.php');
  await expect(page.locator('.gilda-login-intro__brand')).toHaveText('GILDA;');
  await expect(page.locator('.gilda-login-intro__title')).toHaveText('o painel da loja');
  await expect(page.locator('.gilda-login-photo')).toBeVisible();
  await expect(page.locator('#wp-submit')).toHaveValue('Entrar;');

  await loginAsOwner(page, owner);
  await expect(page.locator('h1.gilda-display')).toHaveText('olá, giulia;');
  await expect(page.locator('#adminmenuwrap')).toHaveCSS('background-color', 'rgb(119, 39, 12)');
  await expect(page.locator('#wpfooter')).toBeHidden();
  await expect(page.locator('.gilda-menu-foot')).toContainText('Ver o site');

  const api = await AdminApi.login();
  try {
    const toShip = await api.ordersCount('processing');
    const week = await api.ordersCount('processing,completed', new Date(Date.now() - 7 * 24 * 3600 * 1000));
    await expect(page.locator('[data-kpi="to-ship"] .gilda-kpi__value')).toHaveText(String(toShip));
    await expect(page.locator('[data-kpi="week"] .gilda-kpi__note')).toContainText(`${week} pedido`);
  } finally {
    await api.dispose();
  }
  await expect(page.locator('[data-kpi="low-stock"] .gilda-kpi__value')).toHaveText(/^\d+$/);
  await expect(page.locator('[data-kpi="alerts"] .gilda-kpi__value')).toHaveText(/^\d+$/);
  await expect(page.locator('.gilda-orders')).toBeVisible();
  await expect(page.locator('.gilda-shortcut')).toHaveCount(4);
});

test('a dona cadastra uma peça variável e ela aparece na loja, no capítulo e na página dela', async ({ page, browser }) => {
  await loginAsOwner(page, owner);
  await page.getByRole('link', { name: '+ Nova peça' }).click();
  await expect(page).toHaveURL(/page=gilda-peca/);
  await expect(page.locator('h1.gilda-display')).toHaveText('nova peça;');

  await page.locator('#gilda-peca-name').fill(pieceName);
  await page.locator('#gilda-peca-price').fill('199,90');
  await page.locator('#gilda-peca-category').selectOption({ label: 'Camisas' });
  await expect(page.locator('input[name="weight"]')).toHaveValue('0,35');
  await expect(page.locator('input[name="length"]')).toHaveValue('30');
  await page.locator('#gilda-peca-chapter').selectOption({ label: 'Capítulo 1 — Borboleta 88' });
  await page.locator('textarea[name="description"]').fill('Uma camisa criada pelo teste.\n\nComposição: 100% algodão.');
  await pickPhotos(page, page.locator('.gilda-photos--cover'), created.media);
  await expect(page.locator('.gilda-photos--cover .gilda-photo').first().locator('.gilda-photo__cap')).toHaveText('Capa');

  // Cores: Marrom + uma cor nova (com o seletor de cor).
  await page.locator('[data-gilda-colors] label', { hasText: /^Marrom$/ }).click();
  await page.locator('[data-gilda-newcolor-open]').click();
  await page.locator('[data-gilda-newcolor-name]').fill(newColor.name);
  await page.locator('[data-gilda-newcolor-hex]').fill(newColor.hex);
  await page.locator('[data-gilda-newcolor-save]').click();
  await expect(page.locator('[data-gilda-colors] label', { hasText: newColor.name }).locator('input')).toBeChecked();
  for (const size of ['P', 'M', 'G']) {
    await page.locator('[data-gilda-sizes] label', { hasText: new RegExp(`^${size}$`) }).click();
  }
  const grid = page.locator('[data-gilda-stock] input[type="number"]');
  await expect(grid).toHaveCount(6);
  const stock: Record<string, number> = {
    'Marrom P': 1, 'Marrom M': 2, 'Marrom G': 0,
    [`${newColor.name} P`]: 3, [`${newColor.name} M`]: 4, [`${newColor.name} G`]: 5,
  };
  for (const [label, qty] of Object.entries(stock)) {
    await page.getByLabel(label, { exact: true }).fill(String(qty));
  }
  // SKU: sugerido na hora (peça e cada combinação); um deles a dona troca à mão.
  await expect(page.locator('#gilda-peca-sku')).toHaveValue(baseSku);
  await expect(page.getByLabel('SKU Marrom P', { exact: true })).toHaveValue(`${baseSku}-MR-P`);
  await expect(page.getByLabel(`SKU ${newColor.name} G`, { exact: true })).toHaveValue(`${baseSku}-${newColorCode}-G`);
  await page.getByLabel('SKU Marrom G', { exact: true }).fill(manualSku);
  await expect(page.locator('[data-gilda-preview-name]')).toHaveText(pieceName);
  await expect(page.locator('[data-gilda-preview-price]')).toHaveText('R$ 199,90');

  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toContainText('Peça publicada');
  created.productId = Number(new URL(page.url()).searchParams.get('product'));
  expect(created.productId).toBeGreaterThan(0);

  // Dados no WooCommerce.
  const api = await AdminApi.login();
  let before: Awaited<ReturnType<AdminApi['variations']>> = [];
  let permalink = '';
  try {
    const product = await api.product(created.productId);
    expect(product.type).toBe('variable');
    expect(product.status).toBe('publish');
    expect(product.images.map((i) => i.id)).toEqual(created.media);
    expect(product.categories.map((c) => c.slug)).toEqual(['camisas']);
    expect(product.weight).toBe('0.35');
    expect(product.sku).toBe(baseSku);
    permalink = product.permalink;
    before = (await api.variations(created.productId)).filter((v) => v.status === 'publish');
    expect(before).toHaveLength(6);
    for (const v of before) {
      const label = `${v.attributes.find((a) => a.name === 'Cor')!.option} ${v.attributes.find((a) => a.name === 'Tamanho')!.option}`;
      expect(v.stock_quantity, label).toBe(stock[label]);
      expect(v.regular_price).toBe('199.9');
      const color = label.startsWith('Marrom') ? 'MR' : newColorCode;
      const size = label.split(' ').pop();
      expect(v.sku, label).toBe(label === 'Marrom G' ? manualSku : `${baseSku}-${color}-${size}`);
    }
  } finally {
    await api.dispose();
  }

  // Loja, filtro do capítulo e página da peça (visitante, passa pelo cache do catálogo).
  const v = await visitor(browser);
  await v.page.goto('/loja/');
  await expect(v.page.getByRole('main').locator(`a[href="${permalink}"]`).first()).toBeVisible();
  await v.page.goto('/loja/?capitulo=capitulo-1');
  await expect(v.page.getByRole('main').locator(`a[href="${permalink}"]`).first()).toBeVisible();
  await v.page.goto(permalink);
  await expect(v.page.locator('.gilda-pdp-chapter')).toContainText('Capítulo 1 — Borboleta 88');
  const swatches = v.page.locator('.gilda-swatch-option');
  await expect(swatches).toHaveCount(2);
  await expect(swatches.filter({ hasText: 'Marrom' }).locator('.gilda-swatch')).toHaveAttribute('style', /--swatch:\s*#b4522d/i);
  await expect(swatches.filter({ hasText: newColor.name }).locator('.gilda-swatch')).toHaveAttribute('style', new RegExp(`--swatch:\\s*${newColor.hex}`, 'i'));
  // Marrom G tem estoque 0: escolhida a cor, o G já aparece esgotado (e continua clicável para o Avise-me).
  await swatches.filter({ hasText: 'Marrom' }).locator('input').check({ force: true });
  const sizeG = v.page.locator('.gilda-sizes .gilda-size', { hasText: /^G$/ });
  await expect(sizeG).toHaveClass(/is-soldout/);
  await expect(sizeG.locator('input')).toHaveAttribute('aria-label', 'G esgotado');
  await expect(sizeG.locator('input')).toBeEnabled();
  // O X (duas diagonais) sobre o quadrado do tamanho.
  expect(await sizeG.locator('span').evaluate((el) => getComputedStyle(el).backgroundImage)).toContain('linear-gradient');
  await expect(v.page.locator('.gilda-sizes .gilda-size', { hasText: /^M$/ })).not.toHaveClass(/is-soldout/);
  await swatches.filter({ hasText: newColor.name }).locator('input').check({ force: true });
  await expect(sizeG).not.toHaveClass(/is-soldout/);
  await v.close();

  // Editar o estoque de uma combinação mantém o ID da variação.
  await page.goto(`/wp-admin/post.php?post=${created.productId}&action=edit`);
  await expect(page).toHaveURL(new RegExp(`page=gilda-peca&product=${created.productId}`));
  await expect(page.getByLabel('Marrom M', { exact: true })).toHaveValue('2');
  await page.getByLabel('Marrom M', { exact: true }).fill('7');
  // Mudar o nome não muda nenhum SKU (nem o sugerido na tela).
  await page.locator('#gilda-peca-name').fill(`Camisa Renomeada ${stamp}`);
  await expect(page.locator('#gilda-peca-sku')).toHaveValue(baseSku);
  await expect(page.getByLabel('SKU Marrom M', { exact: true })).toHaveValue(`${baseSku}-MR-M`);
  await page.locator('#gilda-peca-publish').click();
  await expect(page.locator('.gilda-notice--ok')).toBeVisible();

  const api2 = await AdminApi.login();
  try {
    const after = (await api2.variations(created.productId)).filter((x) => x.status === 'publish');
    expect(after.map((x) => x.id).sort()).toEqual(before.map((x) => x.id).sort());
    const marromM = after.find((x) => x.attributes.some((a) => a.option === 'Marrom') && x.attributes.some((a) => a.option === 'M'))!;
    expect(marromM.id).toBe(before.find((x) => x.attributes.some((a) => a.option === 'Marrom') && x.attributes.some((a) => a.option === 'M'))!.id);
    expect(marromM.stock_quantity).toBe(7);
    // SKUs iguais aos de antes, mesmo com o nome novo.
    const skuOf = (list: typeof before) => Object.fromEntries(list.map((x) => [x.id, x.sku]));
    expect(skuOf(after)).toEqual(skuOf(before));
    const renamed = await api2.product(created.productId);
    expect(renamed.name).toBe(`Camisa Renomeada ${stamp}`);
    expect(renamed.sku).toBe(baseSku);
  } finally {
    await api2.dispose();
  }
});

test('as peças da carga inicial ganharam SKU (semente do setup.sh)', async () => {
  const api = await AdminApi.login();
  try {
    const seeded = ['Camisa Casulo', 'Calça Asas', 'Lenço oitenta-e-oito', 'Bermuda Envolto', 'Lenço Manacá', 'Camisa Grão', 'Calça Torra'];
    const products = (await api.products()).filter((p) => seeded.includes(p.name));
    expect(products.map((p) => p.name).sort()).toEqual([...seeded].sort());
    for (const product of products) {
      expect(product.sku, product.name).toMatch(/^(CAM|CAL|BER|LEN)-[A-Z0-9-]+$/);
      if (product.type !== 'variable') continue;
      const variations = await api.variations(product.id);
      expect(variations.length, product.name).toBeGreaterThan(0);
      for (const v of variations) {
        expect(v.sku, `${product.name} ${v.attributes.map((a) => a.option).join(' ')}`).toMatch(new RegExp(`^${product.sku}-[A-Z0-9]+-[A-Z0-9]+$`));
      }
    }
    const grao = products.find((p) => p.name === 'Camisa Grão')!;
    expect(grao.sku).toBe('CAM-GRAO');
    const owPP = (await api.variations(grao.id)).find((v) => v.attributes.some((a) => a.option === 'Off White') && v.attributes.some((a) => a.option === 'PP'));
    expect(owPP?.sku).toBe('CAM-GRAO-OW-PP'); // o exemplo de docs/development/integracao-erp.md
  } finally {
    await api.dispose();
  }
});

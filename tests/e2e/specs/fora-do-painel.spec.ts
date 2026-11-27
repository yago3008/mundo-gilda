import { test, expect, type Page } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { strongPassword, uniqueEmail } from '../helpers/env';

/**
 * Fora do painel: busca de produtos, redirecionamentos do Wix, prévia de compartilhamento (WhatsApp /
 * Open Graph), banner de cookies + Meta Pixel só com consentimento, e o CEP (ViaCEP) nos endereços de
 * Minha conta.
 */

test('busca de produtos: acha pelo nome e avisa quando não acha', async ({ page }) => {
  await page.goto('/?s=camisa&post_type=product');
  await expect(page.locator('h1')).toContainText('camisa');
  const cards = page.getByRole('main').locator('.wc-block-product, li.product');
  expect(await cards.count()).toBeGreaterThan(0);
  await expect(cards.first()).toContainText(/camisa/i);
  await page.goto('/?s=xyzzy-nada&post_type=product');
  await expect(page.getByRole('main')).toContainText(/nenhum produto/i);
});

test('endereços do Wix: 301 direto para a página nova', async ({ request }) => {
  const map: Record<string, RegExp> = {
    '/category/all-products': /\/loja\/$/,
    '/cart-page': /\/carrinho\/$/,
    '/contact-1': /\/loja\/$/, // direto, sem passar pelo /produtos/ (que faz outro redirecionamento)
    '/contact': /\/quem-cria\/$/,
    '/contact-2': /\/capitulos\/$/,
    '/product-page/camisa-gr%C3%A3o': /\/product-page\/camisa-grao\/$/,
    '/product-page/peca-que-nao-existe': /\/loja\/$/,
  };
  for (const [from, to] of Object.entries(map)) {
    const res = await request.get(from, { maxRedirects: 0 });
    expect(res.status(), from).toBe(301);
    expect(res.headers()['location'], from).toMatch(to);
    const final = await request.get(res.headers()['location']);
    expect(final.status(), `${from} → destino`).toBe(200);
  }
});

async function og(page: Page, url: string): Promise<Record<string, string>> {
  await page.goto(url);
  return page.evaluate(() => {
    const out: Record<string, string> = {};
    document.querySelectorAll('meta[property], meta[name]').forEach((m) => {
      out[m.getAttribute('property') || m.getAttribute('name') || ''] = m.getAttribute('content') || '';
    });
    return out;
  });
}

// A foto vem do capítulo mais novo; numa loja sem fotos importadas (CI), do logo ou da foto do tema.
test('prévia no WhatsApp: peça com nome e preço; Livro e home com foto do capítulo mais novo', async ({ page }) => {
  const product = await og(page, '/product-page/camisa-grao/');
  expect(product['og:title']).toBe('Camisa Grão — GILDA;');
  expect(product['og:type']).toBe('product');
  expect(Number(product['product:price:amount'])).toBeGreaterThan(0);
  expect(product['product:price:currency']).toBe('BRL');
  expect(product['og:description'].length).toBeGreaterThan(20);

  const book = await og(page, '/capitulos/');
  expect(book['og:image'], 'foto na prévia do Livro').toMatch(/^https?:\/\/.+\.(webp|jpe?g|png)$/);
  expect(book['og:description']).toMatch(/^Capítulo \d+ — /);
  expect(book['twitter:card']).toBe('summary_large_image');

  const home = await og(page, '/');
  expect(home['og:image'], 'foto na prévia da home').toMatch(/^https?:\/\/.+\.(webp|jpe?g|png)$/);
});

test('cookies: sem aceitar, o Meta Pixel não roda; aceitando, roda', async ({ page }) => {
  const meta: string[] = [];
  page.on('request', (r) => {
    if (/facebook\.(com|net)/.test(r.url())) meta.push(r.url());
  });
  await page.goto('/product-page/camisa-grao/');
  const banner = page.locator('#gilda-consent');
  await expect(banner).toBeVisible();
  const pixel = () => page.evaluate(() => ((window as unknown as { __gildaPixel?: unknown[][] }).__gildaPixel || []).map((a) => String(a[1] ?? a[0])));
  expect(await pixel(), 'nada antes de escolher').toEqual([]);

  await banner.locator('[data-gilda-consent="reject"]').click();
  await expect(banner).toBeHidden();
  await page.reload();
  await expect(banner).toBeHidden();
  expect(await pixel(), 'nada depois de recusar').toEqual([]);
  expect(await page.context().cookies()).not.toContainEqual(expect.objectContaining({ name: '_fbp' }));

  // Outra pessoa aceita: os eventos da fila saem (aqui em modo de teste, sem rede).
  const other = await page.context().browser()!.newContext();
  const p2 = await other.newPage();
  await p2.goto('/product-page/camisa-grao/');
  await p2.locator('#gilda-consent [data-gilda-consent="accept"]').click();
  await expect
    .poll(() => p2.evaluate(() => ((window as unknown as { __gildaPixel?: unknown[][] }).__gildaPixel || []).map((a) => String(a[1] ?? ''))))
    .toEqual(expect.arrayContaining(['PageView', 'ViewContent']));
  await other.close();
  expect(meta, 'nenhuma chamada à Meta fora de produção').toEqual([]);
});

test('Minha conta: o CEP preenche o endereço e, trocado, substitui rua, bairro e cidade', async ({ page }) => {
  const user = { username: `cep-${Date.now().toString(36)}`, password: strongPassword(), email: uniqueEmail('cep') };
  const api = await AdminApi.login();
  const { id } = await api.createUser({ ...user, role: 'customer', firstName: 'Cliente' });
  await api.dispose();
  try {
    await page.goto('/wp-login.php', { waitUntil: 'load' });
    await expect(async () => {
      await page.locator('#user_login').fill(user.username);
      await page.locator('#user_pass').fill(user.password);
      await expect(page.locator('#user_pass')).toHaveValue(user.password, { timeout: 500 });
    }).toPass({ timeout: 10_000 });
    await page.locator('#wp-submit').click();
    await page.waitForLoadState('load');

    await page.goto('/minha-conta/enderecos/entrega/');
    const banner = page.locator('#gilda-consent');
    if (await banner.isVisible().catch(() => false)) await banner.locator('[data-gilda-consent="reject"]').click();
    const cep = page.locator('#shipping_postcode');
    await cep.fill('');
    await cep.pressSequentially('01310100', { delay: 30 });
    await cep.blur();
    await expect(page.locator('#shipping_address_1')).toHaveValue(/paulista/i, { timeout: 20_000 });
    await expect(page.locator('#shipping_city')).toHaveValue(/s[ãa]o paulo/i);
    await expect(page.locator('#shipping_neighborhood')).not.toHaveValue('');
    await page.locator('#shipping_number').fill('1000');
    const first = page.locator('#shipping_first_name');
    if (await first.isVisible()) await first.fill('Cliente');
    const last = page.locator('#shipping_last_name');
    if (await last.isVisible()) await last.fill('Teste');
    await page.locator('button[name="save_address"]').click();
    await expect(page.getByText('Endereço alterado com sucesso')).toBeVisible();

    // Muda de endereço: o CEP novo troca rua, bairro e cidade (antes ficavam os do endereço antigo).
    await page.goto('/minha-conta/enderecos/entrega/');
    await expect(page.locator('#shipping_address_1')).toHaveValue(/paulista/i);
    await cep.fill('');
    await cep.pressSequentially('80010000', { delay: 30 });
    await cep.blur();
    await expect(page.locator('#shipping_city')).toHaveValue(/curitiba/i, { timeout: 20_000 });
    await expect(page.locator('#shipping_address_1')).not.toHaveValue(/paulista/i);
    await expect(page.locator('#shipping_state')).toHaveValue('PR');
  } finally {
    const api2 = await AdminApi.login();
    await api2.deleteUser(id);
    await api2.dispose();
  }
});

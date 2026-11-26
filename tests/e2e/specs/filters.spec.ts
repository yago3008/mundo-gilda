import { test, expect, type Page } from '@playwright/test';

// Filtros da loja por URL (Epic 4). O esperado vem dos atributos dos produtos na Store API.

interface StoreProduct {
  name: string;
  permalink: string;
  attributes: { taxonomy: string; terms: { slug: string }[] }[];
  categories: { slug: string }[];
}

const slugOf = (url: string) => new URL(url).pathname.replace(/\/$/, '').split('/').pop()!;

async function allProducts(page: Page): Promise<StoreProduct[]> {
  const res = await page.request.get('/wp-json/wc/store/v1/products?per_page=100');
  expect(res.ok()).toBeTruthy();
  return res.json();
}

function withTerm(products: StoreProduct[], taxonomy: string, slugs: string[]): string[] {
  return products
    .filter((p) => p.attributes.some((a) => a.taxonomy === taxonomy && a.terms.some((t) => slugs.includes(t.slug))))
    .map((p) => slugOf(p.permalink))
    .sort();
}

/** Produtos listados na página (links para /product-page/ fora da barra de filtros). */
async function listedProducts(page: Page): Promise<string[]> {
  const hrefs = await page
    .getByRole('main')
    .locator('a[href*="/product-page/"]')
    .evaluateAll((links) =>
      links.filter((a) => !a.closest('aside, [role="complementary"], nav')).map((a) => (a as HTMLAnchorElement).href),
    );
  return [...new Set(hrefs.map(slugOf))].sort();
}

const cases: { title: string; url: string; expected: (p: StoreProduct[]) => string[] }[] = [
  { title: 'cor marrom', url: '/loja/?filter_cor=marrom', expected: (p) => withTerm(p, 'pa_cor', ['marrom']) },
  {
    title: 'preto OU verde musgo',
    url: '/loja/?filter_cor=preto,verde-musgo&query_type_cor=or',
    expected: (p) => withTerm(p, 'pa_cor', ['preto', 'verde-musgo']),
  },
  { title: 'tamanho GG', url: '/loja/?filter_tamanho=gg', expected: (p) => withTerm(p, 'pa_tamanho', ['gg']) },
  {
    title: 'categoria camisas',
    url: '/categoria/camisas/',
    expected: (p) => p.filter((x) => x.categories.some((c) => c.slug === 'camisas')).map((x) => slugOf(x.permalink)).sort(),
  },
];

for (const c of cases) {
  test(`filtro por URL: ${c.title}`, async ({ page }) => {
    const expected = c.expected(await allProducts(page));
    expect(expected.length, 'o filtro precisa ter algum produto para o teste valer').toBeGreaterThan(0);

    await page.goto(c.url);
    await expect(page.getByRole('main').locator('a[href*="/product-page/"]').first()).toBeVisible();
    expect(await listedProducts(page)).toEqual(expected);
  });
}

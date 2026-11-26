import { test, expect } from '@playwright/test';
import { checkoutData } from '../helpers/env';
import { PRODUCT_URL } from '../helpers/shop';

// Simulador de frete na página do produto (5.10): PAC e SEDEX com preço.
test('simulador de frete do produto mostra PAC e SEDEX', async ({ page }) => {
  await page.goto(PRODUCT_URL);
  const main = page.getByRole('main');
  await main.getByRole('textbox', { name: /CEP/ }).first().fill(checkoutData.cep);
  await main.getByRole('button', { name: /calcular/i }).first().click();

  await expect(main.getByText(/PAC[\s\S]*?R\$\s?\d+,\d{2}/).first()).toBeVisible({ timeout: 30_000 });
  await expect(main.getByText(/SEDEX[\s\S]*?R\$\s?\d+,\d{2}/).first()).toBeVisible();
});

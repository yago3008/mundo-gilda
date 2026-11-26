import { test, expect, type Page } from '@playwright/test';
import { AdminApi } from '../helpers/admin';
import { admin, strongPassword, uniqueEmail } from '../helpers/env';
import { deleteMessages, deleteMessagesTo, findLink, searchMessages, waitForMessage } from '../helpers/mailpit';

// Conta de cliente (requisitos 7.1–7.3): cadastro, login e recuperação de senha.
test.describe.configure({ mode: 'serial' });

const email = uniqueEmail('conta');
const name = 'Cliente Teste E2E';
let password = strongPassword();

/** O cadastro fica na aba "Criar conta" (o login abre por padrão); sem as abas, o formulário já está visível. */
async function openRegisterForm(page: Page) {
  const tab = page.getByRole('tab', { name: /criar conta|cadastr/i });
  if (await tab.count()) {
    await tab.click();
  }
  return page.locator('form').filter({ has: page.getByRole('button', { name: /cadastr/i }) });
}
function loginForm(page: Page) {
  return page.locator('form').filter({ has: page.getByRole('button', { name: /^(acessar|entrar|login)$/i }) });
}
async function expectLoggedIn(page: Page) {
  await expect(page.getByRole('link', { name: /^sair$/i }).first()).toBeVisible();
  await expect(page.locator('body')).toHaveClass(/\blogged-in\b/);
}

test.afterAll(async () => {
  const api = await AdminApi.login();
  await api.cleanup([email]);
  await api.dispose();
  await deleteMessagesTo([email]);
  // O aviso de "senha alterada" para a loja cita o usuário (gerado a partir do e-mail).
  const login = email.split('@')[0];
  await deleteMessages((await searchMessages(`to:"${admin.email}" "${login}"`)).map((m) => m.ID));
});

test('cadastro recusa senhas diferentes', async ({ page }) => {
  await page.goto('/minha-conta/');
  const form = await openRegisterForm(page);
  await form.getByRole('textbox', { name: /^nome\b/i }).fill(name);
  await form.getByRole('textbox', { name: /e-?mail/i }).fill(email);
  await form.getByRole('textbox', { name: /^senha/i }).fill(password);
  await form.getByRole('textbox', { name: /confirmar senha/i }).fill(`${password}x`);
  await form.getByRole('button', { name: /cadastr/i }).click();

  await expect(page.getByText('As senhas não coincidem.')).toBeVisible();
  await expect(page.locator('body')).not.toHaveClass(/\blogged-in\b/);
});

test('cadastro cria a conta, entra e manda o e-mail de boas-vindas', async ({ page }) => {
  await page.goto('/minha-conta/');
  const form = await openRegisterForm(page);
  await form.getByRole('textbox', { name: /^nome\b/i }).fill(name);
  await form.getByRole('textbox', { name: /e-?mail/i }).fill(email);
  await form.getByRole('textbox', { name: /^senha/i }).fill(password);
  await form.getByRole('textbox', { name: /confirmar senha/i }).fill(password);
  await form.getByRole('button', { name: /cadastr/i }).click();

  await expectLoggedIn(page);
  await waitForMessage(email, /conta/i);

  const api = await AdminApi.login();
  const [customer] = await api.customersByEmail(email);
  await api.dispose();
  expect(customer, 'cliente criado no WooCommerce').toBeTruthy();
  expect(customer.first_name, 'nome salvo no perfil').toBe('Cliente');
});

test('login com e-mail e senha', async ({ page }) => {
  await page.goto('/minha-conta/');
  const form = loginForm(page);
  await form.getByRole('textbox', { name: /usu[áa]rio|e-?mail/i }).fill(email);
  await form.getByRole('textbox', { name: /^senha/i }).fill(password);
  await form.getByRole('button', { name: /^(acessar|entrar|login)$/i }).click();
  await expectLoggedIn(page);
});

test('recuperação de senha por e-mail', async ({ page }) => {
  await page.goto('/minha-conta/recuperar-senha/');
  await page.getByRole('textbox', { name: /usu[áa]rio|e-?mail/i }).fill(email);
  await page.getByRole('button', { name: /redefinir senha/i }).click();
  await expect(page.getByText(/e-mail.*(enviado|redefini)/i).first()).toBeVisible();

  const message = await waitForMessage(email, /senha/i);
  await page.goto(findLink(message, /recuperar-senha\/?\?.*key=/));

  password = strongPassword();
  await page.getByRole('textbox', { name: /^nova senha/i }).fill(password);
  await page.getByRole('textbox', { name: /novamente|confirm/i }).fill(password);
  await page.getByRole('button', { name: /salvar/i }).click();
  await expect(page.getByText(/senha foi (alterada|redefinida)/i).first()).toBeVisible();

  // A senha nova funciona.
  await page.goto('/minha-conta/');
  const form = loginForm(page);
  await form.getByRole('textbox', { name: /usu[áa]rio|e-?mail/i }).fill(email);
  await form.getByRole('textbox', { name: /^senha/i }).fill(password);
  await form.getByRole('button', { name: /^(acessar|entrar|login)$/i }).click();
  await expectLoggedIn(page);
});

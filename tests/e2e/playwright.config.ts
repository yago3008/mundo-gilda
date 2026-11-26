import { defineConfig, devices } from '@playwright/test';
import { BASE_URL } from './helpers/env';

/**
 * Testes ponta a ponta da loja contra o ambiente Docker local (./scripts/setup.sh).
 * URLs e credenciais vêm do .env da raiz do projeto (ver helpers/env.ts).
 */
export default defineConfig({
  testDir: './specs',
  globalSetup: './global-setup.ts',
  // WordPress local é lento e os fluxos mexem em estoque/cupom: um worker evita disputa.
  workers: 1,
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  forbidOnly: !!process.env.CI,
  reporter: process.env.CI
    ? [['list'], ['html', { open: 'never' }], ['github']]
    : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: BASE_URL,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 45_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});

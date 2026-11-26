import fs from 'node:fs';
import path from 'node:path';
import dotenv from 'dotenv';

// .env da raiz do projeto (o mesmo que o setup.sh usa). Variáveis já exportadas têm prioridade.
const ROOT = path.resolve(__dirname, '..', '..', '..');
const envFile = process.env.E2E_ENV_FILE || path.join(ROOT, '.env');
if (fs.existsSync(envFile)) {
  dotenv.config({ path: envFile, quiet: true } as dotenv.DotenvConfigOptions);
}

function required(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Variável ${name} não definida (esperada no ${envFile}).`);
  }
  return value;
}

export const BASE_URL = (process.env.E2E_BASE_URL || `http://localhost:${process.env.WP_PORT || 8080}`).replace(/\/$/, '');
export const MAILPIT_URL = (process.env.E2E_MAILPIT_URL || `http://localhost:${process.env.MAIL_PORT || 8026}`).replace(/\/$/, '');

export const admin = {
  get user() { return required('WP_ADMIN_USER'); },
  get password() { return required('WP_ADMIN_PASSWORD'); },
  get email() { return required('WP_ADMIN_EMAIL'); },
};

/** Dados de teste do checkout (docs/development/backend-para-frontend.md, seção 8). */
export const checkoutData = {
  cep: '01310-100',
  cpf: '529.982.247-25',
  number: '1000',
  phone: '(11) 98765-4321',
  firstName: 'Teste',
  lastName: 'E2E',
};

/** E-mail único por execução (os testes nunca reaproveitam contas). */
export function uniqueEmail(tag: string): string {
  const stamp = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  return `e2e-${tag}-${stamp}@mundogilda.test`;
}

/** Senha forte o bastante para o medidor do WooCommerce. */
export function strongPassword(): string {
  return `Gilda;E2e#${Math.random().toString(36).slice(2, 8)}-Xq9!`;
}

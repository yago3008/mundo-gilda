import { request, type APIRequestContext } from '@playwright/test';
import { BASE_URL, admin } from './env';

/**
 * Acesso de administrador à API REST do WooCommerce (wc/v3), usado só para preparar e conferir
 * dados (estoque, pedidos, clientes) e para limpar o que os testes criaram.
 * Autentica pelo cookie de login do wp-admin + nonce (não depende de senha de aplicativo nem do WP-CLI).
 */
export class AdminApi {
  private constructor(private readonly ctx: APIRequestContext, private readonly nonce: string) {}

  static async login(): Promise<AdminApi> {
    const ctx = await request.newContext({ baseURL: BASE_URL });
    await ctx.get('/wp-login.php'); // grava o wordpress_test_cookie
    const res = await ctx.post('/wp-login.php', {
      form: { log: admin.user, pwd: admin.password, rememberme: 'forever', 'wp-submit': 'Acessar', redirect_to: `${BASE_URL}/wp-admin/`, testcookie: '1' },
      maxRedirects: 0,
    });
    if (res.status() !== 302) {
      throw new Error(`Login do admin falhou (HTTP ${res.status()}). Confira WP_ADMIN_USER/WP_ADMIN_PASSWORD no .env.`);
    }
    const nonceRes = await ctx.get('/wp-admin/admin-ajax.php?action=rest-nonce');
    const nonce = (await nonceRes.text()).trim();
    if (!nonceRes.ok() || !/^[a-f0-9]{10}$/.test(nonce)) {
      throw new Error(`Não consegui o nonce da API REST (HTTP ${nonceRes.status()}).`);
    }
    return new AdminApi(ctx, nonce);
  }

  private async call<T>(method: 'GET' | 'PUT' | 'POST' | 'DELETE', path: string, data?: unknown): Promise<T> {
    const res = await this.ctx.fetch(`/wp-json${path}`, { method, data, headers: { 'X-WP-Nonce': this.nonce } });
    if (!res.ok()) {
      throw new Error(`REST ${method} ${path}: HTTP ${res.status()} ${(await res.text()).slice(0, 300)}`);
    }
    return (await res.json()) as T;
  }

  async productBySlug(slug: string): Promise<{ id: number; name: string }> {
    const [product] = await this.call<{ id: number; name: string }[]>('GET', `/wc/v3/products?slug=${encodeURIComponent(slug)}`);
    if (!product) throw new Error(`Produto ${slug} não encontrado`);
    return product;
  }

  async variation(productId: number, variationId: number) {
    return this.call<{ id: number; stock_quantity: number | null; manage_stock: boolean; stock_status: string }>(
      'GET',
      `/wc/v3/products/${productId}/variations/${variationId}`,
    );
  }

  async setVariationStock(productId: number, variationId: number, quantity: number) {
    return this.call('PUT', `/wc/v3/products/${productId}/variations/${variationId}`, { manage_stock: true, stock_quantity: quantity });
  }

  async ordersByEmail(email: string) {
    const orders = await this.call<{ id: number; number: string; status: string; billing: { email: string }; coupon_lines: { code: string }[]; total: string }[]>(
      'GET',
      `/wc/v3/orders?search=${encodeURIComponent(email)}&status=any&per_page=50`,
    );
    return orders.filter((o) => o.billing.email.toLowerCase() === email.toLowerCase());
  }

  async deleteOrder(id: number) {
    await this.call('DELETE', `/wc/v3/orders/${id}?force=true`);
  }

  async customersByEmail(email: string) {
    return this.call<{ id: number; email: string; first_name: string }[]>('GET', `/wc/v3/customers?email=${encodeURIComponent(email)}&role=all`);
  }

  async deleteCustomer(id: number) {
    await this.call('DELETE', `/wc/v3/customers/${id}?force=true&reassign=0`);
  }

  /** Conta do painel com um perfil (ex.: gilda_dona, "Dona da loja"). */
  async createUser(data: { username: string; email: string; password: string; role: string; firstName?: string }) {
    return this.call<{ id: number }>('POST', '/wp/v2/users', {
      username: data.username,
      email: data.email,
      password: data.password,
      roles: [data.role],
      first_name: data.firstName ?? '',
    });
  }

  async deleteUser(id: number) {
    await this.call('DELETE', `/wp/v2/users/${id}?force=true&reassign=1`);
  }

  /** Envia uma imagem para a biblioteca de mídia. */
  async uploadImage(filePath: string, alt = ''): Promise<{ id: number }> {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const name = `e2e-${Date.now().toString(36)}-${path.basename(filePath)}`;
    const ext = path.extname(filePath).slice(1).toLowerCase();
    const mimeType = ext === 'jpg' ? 'image/jpeg' : `image/${ext}`;
    const res = await this.ctx.fetch('/wp-json/wp/v2/media', {
      method: 'POST',
      headers: { 'X-WP-Nonce': this.nonce },
      multipart: { file: { name, mimeType, buffer: fs.readFileSync(filePath) }, alt_text: alt },
    });
    if (!res.ok()) throw new Error(`Upload ${name}: HTTP ${res.status()} ${(await res.text()).slice(0, 300)}`);
    return (await res.json()) as { id: number };
  }

  async deleteMedia(id: number) {
    await this.call('DELETE', `/wp/v2/media/${id}?force=true`);
  }

  async createProduct(data: { name: string; price: string }) {
    return this.call<{ id: number; permalink: string; name: string }>('POST', '/wc/v3/products', {
      name: data.name,
      type: 'simple',
      status: 'publish',
      regular_price: data.price,
    });
  }

  async deleteProduct(id: number) {
    await this.call('DELETE', `/wc/v3/products/${id}?force=true`);
  }

  /** Quantos pedidos com estes status (cabeçalho X-WP-Total). */
  async ordersCount(status: string, after?: Date): Promise<number> {
    const query = `status=${encodeURIComponent(status)}&per_page=1${after ? `&after=${encodeURIComponent(after.toISOString())}` : ''}`;
    const res = await this.ctx.fetch(`/wp-json/wc/v3/orders?${query}`, { headers: { 'X-WP-Nonce': this.nonce } });
    if (!res.ok()) throw new Error(`REST orders: HTTP ${res.status()}`);
    return Number(res.headers()['x-wp-total'] ?? 0);
  }

  async product(id: number) {
    return this.call<{ id: number; name: string; sku: string; status: string; type: string; permalink: string; featured: boolean; weight: string; images: { id: number }[]; categories: { slug: string }[] }>(
      'GET',
      `/wc/v3/products/${id}`,
    );
  }

  async variations(productId: number) {
    return this.call<{ id: number; sku: string; status: string; stock_quantity: number | null; regular_price: string; attributes: { name: string; option: string }[] }[]>(
      'GET',
      `/wc/v3/products/${productId}/variations?per_page=100&status=any`,
    );
  }

  async products() {
    return this.call<{ id: number; name: string; sku: string; type: string }[]>('GET', '/wc/v3/products?per_page=100&status=any');
  }

  /** Apaga um termo de atributo global (ex.: uma cor criada pelo teste). */
  async deleteAttributeTerm(taxonomy: string, name: string) {
    const attributes = await this.call<{ id: number; slug: string }[]>('GET', '/wc/v3/products/attributes');
    const attribute = attributes.find((a) => a.slug === taxonomy);
    if (!attribute) return;
    const terms = await this.call<{ id: number; name: string }[]>('GET', `/wc/v3/products/attributes/${attribute.id}/terms?per_page=100&search=${encodeURIComponent(name)}`);
    for (const term of terms.filter((t) => t.name === name)) {
      await this.call('DELETE', `/wc/v3/products/attributes/${attribute.id}/terms/${term.id}?force=true`);
    }
  }

  /** Apaga pedidos e contas criados com estes e-mails. */
  async cleanup(emails: string[]) {
    for (const email of emails) {
      for (const order of await this.ordersByEmail(email)) await this.deleteOrder(order.id);
      for (const customer of await this.customersByEmail(email)) await this.deleteCustomer(customer.id);
    }
  }

  async dispose() {
    await this.ctx.dispose();
  }
}

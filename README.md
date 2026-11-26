# mundo-gilda

Site WordPress do cliente Mundo Gilda.

## Ambiente local (Docker)

Requisitos: Docker Desktop rodando.

```bash
cp .env.example .env        # só na primeira vez
docker compose up -d
```

| Serviço     | URL                     |
|-------------|-------------------------|
| WordPress   | http://localhost:8080   |
| phpMyAdmin  | http://localhost:8081   |

As portas podem ser trocadas no `.env` (`WP_PORT`, `PMA_PORT`).

Login do admin (`/wp-admin`): usuário e senha ficam no seu `.env` (`WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`).

### Primeira instalação

Depois do primeiro `docker compose up -d`, instale o WordPress (pt_BR, permalinks e fuso já configurados):

```bash
set -a; . ./.env; set +a
docker compose run --rm wpcli wp core install --url=http://localhost:8080 --title="GILDA;" \
  --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email
docker compose run --rm wpcli wp language core install pt_BR --activate
docker compose run --rm wpcli wp rewrite structure '/%postname%/'
docker compose run --rm wpcli wp option update timezone_string America/Sao_Paulo
```

### WP-CLI

```bash
docker compose run --rm wpcli wp <comando>
# ex.: docker compose run --rm wpcli wp plugin list
```

### Parar / resetar

```bash
docker compose down          # para os containers (mantém o banco)
docker compose down -v       # apaga banco e core do WordPress (reset total)
```

## Estrutura

```
wp-content/
  themes/    # temas versionados no git (tema do projeto fica aqui)
  plugins/   # plugins versionados no git
  uploads/   # mídia local, fora do git
```

O core do WordPress fica num volume Docker e não é versionado. Só `themes/` e `plugins/` vão pro repositório.

## Equipe

- Requisitos: K1venn
- WordPress: yago3008

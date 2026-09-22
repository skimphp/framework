.PHONY: up down build rebuild shell test pest migrate migrate-fresh migrate-down \
        migrate-status cache-clear queue-work logs ps redis-cli mysql-cli pgsql-cli mcp-install

# ── Docker lifecycle ─────────────────────────────────────────────────────────

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose build --no-cache

logs:
	docker compose logs -f app

ps:
	docker compose ps

# ── App shell ────────────────────────────────────────────────────────────────

shell:
	docker compose exec app bash

# ── Tests ────────────────────────────────────────────────────────────────────

test:
	docker compose exec app ./vendor/bin/pest

pest:
	docker compose exec app ./vendor/bin/pest --coverage

test-filter:
	docker compose exec app ./vendor/bin/pest --filter=$(filter)

# ── Migrations ───────────────────────────────────────────────────────────────

migrate:
	docker compose exec app php skim migrate

migrate-fresh:
	docker compose exec app php skim migrate:fresh

migrate-down:
	docker compose exec app php skim migrate:down

migrate-status:
	docker compose exec app php skim migrate:status

# ── Cache ────────────────────────────────────────────────────────────────────

cache-clear:
	docker compose exec app php skim cache:clear

# ── Queue ────────────────────────────────────────────────────────────────────

queue-work:
	docker compose exec app php skim queue:work

# ── DB clients ───────────────────────────────────────────────────────────────

mysql-cli:
	docker compose exec mysql mysql -u skim -psecret skim_dev

pgsql-cli:
	docker compose exec pgsql psql -U skim -d skim_analytics

redis-cli:
	docker compose exec redis redis-cli

# ── MCP Antigravity install ─────────────────────────────────────────────────

mcp-install:
	bash scripts/mcp/antigravity/install.sh

DC  = docker-compose
APP = $(DC) exec app
PHP = $(APP) php
ART = $(PHP) artisan

# ─────────────────────────────────────────────
#  Help
# ─────────────────────────────────────────────
.DEFAULT_GOAL := help
.PHONY: help
help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}' \
		| sort

# ─────────────────────────────────────────────
#  Installation
# ─────────────────────────────────────────────
.PHONY: install
install: ## Full first-time setup (copy .env, build, migrate, seed)
	@[ -f .env ] || cp .env.example .env
	$(DC) build --no-cache
	$(DC) up -d
	$(ART) key:generate
	$(ART) migrate --seed
	@echo "\n✔  App running at http://localhost:8000"

.PHONY: build
build: ## Rebuild Docker images from scratch
	$(DC) build --no-cache

.PHONY: composer-install
composer-install: ## Install PHP dependencies inside the container
	$(APP) composer install

.PHONY: npm-install
npm-install: ## Install Node dependencies inside the container
	$(APP) npm install

# ─────────────────────────────────────────────
#  Docker lifecycle
# ─────────────────────────────────────────────
.PHONY: up
up: ## Start all containers in the background
	$(DC) up -d

.PHONY: down
down: ## Stop and remove containers
	$(DC) down

.PHONY: restart
restart: down up ## Restart all containers

.PHONY: ps
ps: ## Show running containers
	$(DC) ps

.PHONY: logs
logs: ## Tail logs for all containers (Ctrl+C to stop)
	$(DC) logs -f

.PHONY: logs-app
logs-app: ## Tail app container logs only
	$(DC) logs -f app

.PHONY: logs-db
logs-db: ## Tail MySQL container logs only
	$(DC) logs -f mysql

# ─────────────────────────────────────────────
#  Shell access
# ─────────────────────────────────────────────
.PHONY: shell
shell: ## Open a bash shell in the app container
	$(APP) bash

.PHONY: tinker
tinker: ## Open Laravel Tinker REPL
	$(ART) tinker

.PHONY: mysql
mysql: ## Open a MySQL shell
	$(DC) exec mysql mysql -u$${DB_USERNAME:-sail} -p$${DB_PASSWORD:-password} $${DB_DATABASE:-mona_lisa_insurance}

# ─────────────────────────────────────────────
#  Database
# ─────────────────────────────────────────────
.PHONY: migrate
migrate: ## Run pending migrations
	$(ART) migrate

.PHONY: migrate-fresh
migrate-fresh: ## Drop all tables and re-run migrations
	$(ART) migrate:fresh

.PHONY: migrate-fresh-seed
migrate-fresh-seed: ## Drop all tables, re-run migrations, and seed
	$(ART) migrate:fresh --seed

.PHONY: seed
seed: ## Run database seeders
	$(ART) db:seed

.PHONY: rollback
rollback: ## Rollback the last migration batch
	$(ART) migrate:rollback

# ─────────────────────────────────────────────
#  Frontend
# ─────────────────────────────────────────────
.PHONY: dev
dev: ## Start Vite dev server (HMR) inside the container
	$(APP) npm run dev

.PHONY: build-assets
build-assets: ## Build frontend assets for production (alias: npm-build)
	$(APP) npm run build

.PHONY: npm-build
npm-build: ## Run npm run build inside the container
	$(APP) npm run build

# ─────────────────────────────────────────────
#  Code quality
# ─────────────────────────────────────────────
.PHONY: test
test: ## Run PHPUnit test suite
	$(APP) php artisan test

.PHONY: test-filter
test-filter: ## Run tests matching a filter  (usage: make test-filter FILTER=LoginTest)
	$(APP) php artisan test --filter=$(FILTER)

.PHONY: lint
lint: ## Run Laravel Pint code style fixer
	$(APP) ./vendor/bin/pint

.PHONY: lint-dry
lint-dry: ## Check code style without making changes
	$(APP) ./vendor/bin/pint --test

# ─────────────────────────────────────────────
#  Laravel helpers
# ─────────────────────────────────────────────
.PHONY: cache-clear
cache-clear: ## Clear all application caches
	$(ART) optimize:clear

.PHONY: cache
cache: ## Cache config, routes, and views for production
	$(ART) optimize

.PHONY: optimize
optimize: ## Alias for cache — run php artisan optimize
	$(ART) optimize

.PHONY: routes
routes: ## List all registered routes
	$(ART) route:list

.PHONY: make-controller
make-controller: ## Generate a controller  (usage: make make-controller NAME=UserController)
	$(ART) make:controller $(NAME)

.PHONY: make-model
make-model: ## Generate a model + migration  (usage: make make-model NAME=Policy)
	$(ART) make:model $(NAME) -m

.PHONY: make-migration
make-migration: ## Generate a migration  (usage: make make-migration NAME=create_policies_table)
	$(ART) make:migration $(NAME)

.PHONY: make-seeder
make-seeder: ## Generate a seeder  (usage: make make-seeder NAME=PolicySeeder)
	$(ART) make:seeder $(NAME)

# ─────────────────────────────────────────────
#  Cleanup
# ─────────────────────────────────────────────
.PHONY: destroy
destroy: ## Stop containers and delete volumes (⚠ destroys DB data)
	$(DC) down -v

SHELL := /bin/bash

# Local overrides (git-ignored), e.g. DOCKER_ROOTLESS=1 for rootless Docker.
-include make.local

DOCKER_ROOTLESS ?= 0
PHP ?= 8.4
export PHP_VERSION = $(PHP)

# Docker socket for Traefik, taken from the active context (works for rootless and rootful Docker).
export DOCKER_SOCKET ?= $(shell docker context inspect --format '{{.Endpoints.docker.Host}}' 2>/dev/null | sed -n 's|^unix://||p')

COMPOSE_FILE ?= docker/docker-compose.yml
COMPOSE_FILES = $(subst :, ,$(COMPOSE_FILE))
COMPOSE = docker compose $(foreach file,$(COMPOSE_FILES),-f $(file))

# Rootless Docker maps container root to the host user. Rootful local Docker needs the host UID
# for bind mounts. The CI overlay uses named volumes seeded by its non-root image user instead.
ifneq (,$(filter docker/docker-compose.ci.yml,$(COMPOSE_FILES)))
DOCKER_USER =
else ifeq ($(DOCKER_ROOTLESS),1)
DOCKER_USER =
else
DOCKER_USER = --user $(shell id -u):$(shell id -g)
endif

RUN_PHP      = $(COMPOSE) run --rm -T $(DOCKER_USER) --no-deps php
RUN_PHP_TEST = $(COMPOSE) run --rm -T $(DOCKER_USER) php-test
RUN_NODE     = $(COMPOSE) run --rm -T $(DOCKER_USER) node

APP_SERVICES  = traefik php scheduler nginx mariadb mailpit adminer
TEST_SERVICES = mariadb-test mailpit php-test
ACCEPTANCE_SERVICES = traefik mariadb-test mailpit selenium nginx php-test

TEST ?=

define wait_for_selenium
	@for i in $$(seq 1 30); do \
		if $(COMPOSE) exec -T selenium wget -q -O - http://localhost:4444/wd/hub/status 2>/dev/null | grep -q '"ready":[[:space:]]*true'; then exit 0; fi; \
		echo "Waiting for Selenium... ($$i/30)"; sleep 2; \
	done; echo "Selenium did not become ready in time."; exit 1
endef

.PHONY: help build up down restart ps logs enter clean-cache composer-install composer-update init \
        migrate migration fixtures db-reset console assets assets-watch npm-install \
        test-unit test-integration test-acceptance ci-acceptance test-mapping test-database \
        prepare-writable-dirs check-cs check-cs-check check-phpstan check-latte check-types audit fix ci

help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*## ' Makefile | awk -F ':[^#]*## ' '{printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'
	@echo "  PHP=8.4|8.5 selects the PHP version (default 8.4), TEST=... narrows a test run"

build: ## Build the images
	$(COMPOSE) build php php-test

up: ## Start the development stack
	$(COMPOSE) up -d $(APP_SERVICES)
	$(MAKE) prepare-writable-dirs

down: ## Stop everything
	$(COMPOSE) down --remove-orphans

restart: down up ## Restart the development stack

ps: ## List running services
	$(COMPOSE) ps

logs: ## Follow logs
	$(COMPOSE) logs -f --tail=200

enter: ## Shell in the PHP container
	$(COMPOSE) exec php bash

clean-cache: ## Remove every cache in temp/ (DI container, Latte, skautIS code lists, PHPStan, tests) and the sessions
	$(RUN_PHP) sh -c "find temp -mindepth 1 -maxdepth 1 ! -name .gitkeep ! -name sessions -exec rm -rf {} + && rm -rf temp/sessions/*"

prepare-writable-dirs: ## Create every directory that application, assets and tests may write to
	$(RUN_PHP) sh -c 'mkdir -p log temp uploads www/assets tests/_output tests/_support/_generated'

composer-install: ## composer install
	$(RUN_PHP) composer install --no-interaction --prefer-dist

composer-update: ## composer update
	$(RUN_PHP) composer update --no-interaction --prefer-dist

npm-install: ## npm ci
	$(RUN_NODE) npm ci

assets: prepare-writable-dirs ## Build frontend assets
	$(RUN_NODE) npm run build

assets-watch: ## Rebuild assets on change
	$(COMPOSE) run --rm $(DOCKER_USER) node npm run watch

init: ## Setup from scratch: build, caches cleared, dependencies, assets, database reset (all data lost)
	$(MAKE) build
	$(MAKE) clean-cache
	$(MAKE) up
	$(MAKE) composer-install
	$(MAKE) npm-install
	$(MAKE) assets
	$(MAKE) db-reset
	@echo "Application: http://$$(grep '^DEV_HOST=' docker/.env | cut -d= -f2)  Mail: http://mail.localhost  Adminer: http://adminer.localhost"

console: ## Run a console command: make console c="migrations:status"
	$(RUN_PHP) bin/console $(c)

migrate: ## Run database migrations
	$(RUN_PHP) bin/console migrations:migrate --no-interaction --allow-no-migration

migration: ## Generate a migration from entity changes
	$(RUN_PHP) bin/console migrations:diff --no-interaction

fixtures: ## Load development fixtures (replaces existing data)
	$(RUN_PHP) bin/console doctrine:fixtures:load --no-interaction --purge=delete

db-reset: ## Drop every table of the dev database, migrate from scratch and load fixtures (all data lost)
	$(RUN_PHP) bin/console app:database:drop --force
	$(MAKE) migrate
	$(MAKE) fixtures

test-unit: prepare-writable-dirs ## Unit tests (TEST=tests/unit/Foo/BarTest.php)
	$(RUN_PHP_TEST) vendor/bin/codecept run unit $(TEST)

test-integration: prepare-writable-dirs ## Integration tests against the test database
	$(COMPOSE) up -d $(TEST_SERVICES)
	$(MAKE) test-database
	$(RUN_PHP_TEST) vendor/bin/codecept run integration $(TEST)

test-acceptance: prepare-writable-dirs ## Acceptance tests with a visible browser at http://selenium.localhost
	$(COMPOSE) up -d $(ACCEPTANCE_SERVICES)
	$(call wait_for_selenium)
	$(MAKE) test-database
	$(RUN_PHP_TEST) vendor/bin/codecept run acceptance -vv $(TEST); status=$$?; $(COMPOSE) stop selenium; exit $$status

ci-acceptance: prepare-writable-dirs ## Acceptance tests headless (CI)
	$(COMPOSE) up -d $(ACCEPTANCE_SERVICES)
	$(call wait_for_selenium)
	$(MAKE) test-database
	$(RUN_PHP_TEST) vendor/bin/codecept run acceptance --env ci -vv $(TEST); status=$$?; $(COMPOSE) stop selenium; exit $$status

test-mapping: prepare-writable-dirs ## Entity mapping must match the migrations (migrations:diff must find no changes)
	$(COMPOSE) up -d $(TEST_SERVICES)
	$(MAKE) test-database
	$(RUN_PHP_TEST) sh -c 'out=$$(bin/console migrations:diff --no-interaction 2>&1); echo "$$out" | grep -q "No changes detected" || { echo "$$out"; echo "Doctrine mapping differs from migrations."; exit 1; }; echo "Mapping is in sync with migrations."'

test-database: ## Recreate the test database from migrations
	$(RUN_PHP_TEST) sh -c "bin/console app:database:drop --force -q && bin/console migrations:migrate --no-interaction --allow-no-migration -q"

check-cs: ## Coding standard (fixes files)
	$(RUN_PHP) vendor/bin/php-cs-fixer fix

check-cs-check: ## Coding standard (dry run)
	$(RUN_PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

check-phpstan: prepare-writable-dirs ## PHPStan (generates Codeception actors first)
	$(RUN_PHP) sh -c "vendor/bin/codecept build -q && vendor/bin/phpstan analyse --no-progress --memory-limit=1G"

check-latte: prepare-writable-dirs ## Latte lint
	$(RUN_PHP) bin/latte-lint

check-types: ## TypeScript type check (frontend and service worker)
	$(RUN_NODE) npm run check-types

audit: ## Known vulnerabilities in Composer and npm dependencies
	$(RUN_PHP) composer audit --no-interaction
	$(RUN_NODE) npm audit --omit=dev --audit-level=high

fix: check-cs check-latte check-phpstan check-types ## Fixable checks

ci: ## Everything CI runs
	$(MAKE) audit
	$(MAKE) check-cs-check
	$(MAKE) check-phpstan
	$(MAKE) check-latte
	$(MAKE) check-types
	$(MAKE) test-unit
	$(MAKE) test-integration
	$(MAKE) test-mapping
	$(MAKE) ci-acceptance
	@printf "\n\033[1;32mALL PASSED\033[0m\n"

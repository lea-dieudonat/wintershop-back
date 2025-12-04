.PHONY: help build up down restart logs shell php-shell composer test cache-clear db-create db-migrate

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(GREEN)Available commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(BLUE)%-20s$(NC) %s\n", $$1, $$2}'

build: ## Build Docker containers
	@echo "$(YELLOW)Building Docker containers...$(NC)"
	docker compose build

up: ## Start all containers
	@echo "$(YELLOW)Starting containers...$(NC)"
	docker compose up -d
	@echo "$(GREEN)Containers started! App available at http://localhost:8000$(NC)"

down: ## Stop all containers
	@echo "$(YELLOW)Stopping containers...$(NC)"
	docker compose down

restart: ## Restart all containers
	@echo "$(YELLOW)Restarting containers...$(NC)"
	docker compose restart

stop: ## Stop containers without removing them
	@echo "$(YELLOW)Stopping containers...$(NC)"
	docker compose stop

logs: ## Show logs from all containers
	docker compose logs -f

logs-php: ## Show PHP container logs
	docker compose logs -f php

logs-nginx: ## Show Nginx container logs
	docker compose logs -f nginx

logs-db: ## Show database container logs
	docker compose logs -f database

shell: ## Open bash shell in PHP container
	docker compose exec php sh

php-shell: ## Alias for shell
	docker compose exec php sh

nginx-shell: ## Open shell in Nginx container
	docker compose exec nginx sh

composer: ## Run composer command (usage: make composer cmd="install")
	docker compose exec php composer $(cmd)

composer-install: ## Run composer install
	@echo "$(YELLOW)Installing dependencies...$(NC)"
	docker compose exec php composer install

composer-update: ## Run composer update
	@echo "$(YELLOW)Updating dependencies...$(NC)"
	docker compose exec php composer update

composer-require: ## Install a package (usage: make composer-require pkg="vendor/package")
	docker compose exec php composer require $(pkg)

console: ## Run Symfony console command (usage: make console cmd="cache:clear")
	docker compose exec php php bin/console $(cmd)

cache-clear: ## Clear Symfony cache
	@echo "$(YELLOW)Clearing cache...$(NC)"
	docker compose exec php php bin/console cache:clear

cache-warmup: ## Warmup Symfony cache
	@echo "$(YELLOW)Warming up cache...$(NC)"
	docker compose exec php php bin/console cache:warmup

db-create: ## Create database
	@echo "$(YELLOW)Creating database...$(NC)"
	docker compose exec php php bin/console doctrine:database:create --if-not-exists

db-drop: ## Drop database
	@echo "$(YELLOW)Dropping database...$(NC)"
	docker compose exec php php bin/console doctrine:database:drop --force

db-migrate: ## Run database migrations
	@echo "$(YELLOW)Running migrations...$(NC)"
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

db-migration-create: ## Create a new migration (usage: make db-migration-create name="AddUserTable")
	docker compose exec php php bin/console doctrine:migrations:generate

db-reset: ## Reset database (drop, create, migrate)
	@echo "$(YELLOW)Resetting database...$(NC)"
	@make db-drop
	@make db-create
	@make db-migrate

factory: ## Generate test factories
	@echo "$(YELLOW)Generating test factories...$(NC)"
	docker compose exec php php bin/console make:factory --test

fixtures: ## Load fixtures
	@echo "$(YELLOW)Loading fixtures...$(NC)"
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

test: ## Run tests
	docker compose exec php php bin/phpunit

clean: ## Clean up containers, volumes, and networks
	@echo "$(YELLOW)Cleaning up...$(NC)"
	docker compose down -v --remove-orphans

ps: ## Show running containers
	docker compose ps

rebuild: ## Rebuild and restart containers
	@echo "$(YELLOW)Rebuilding containers...$(NC)"
	docker compose down
	docker compose build --no-cache
	docker compose up -d
	@echo "$(GREEN)Rebuild complete!$(NC)"

fresh: ## Fresh install (rebuild, composer install, db setup)
	@echo "$(YELLOW)Fresh install...$(NC)"
	@make rebuild
	@make composer-install
	@make db-create
	@echo "$(GREEN)Fresh install complete!$(NC)"

### Testing commands ###

test-db-create: ## Create test database
	@echo "$(YELLOW)Creating test database...$(NC)"
	docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists

test-db-schema: ## Create test database schema
	@echo "$(YELLOW)Creating test database schema...$(NC)"
	docker compose exec php php bin/console doctrine:schema:create --env=test

test-db-migrate: ## Run migrations on test database
	@echo "$(YELLOW)Running test migrations...$(NC)"
	docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction

test-db-fixtures: ## Load fixtures in test database
	@echo "$(YELLOW)Loading test fixtures...$(NC)"
	docker compose exec php php bin/console doctrine:fixtures:load --env=test --no-interaction

test-db-reset: ## Reset test database (drop, create, migrate, fixtures)
	@echo "$(YELLOW)Resetting test database...$(NC)"
	docker compose exec php php bin/console doctrine:database:drop --env=test --force --if-exists
	@make test-db-create
	@make test-db-migrate
	@make test-db-fixtures
	@echo "$(GREEN)Test database reset complete!$(NC)"

test-unit: ## Run unit tests
	docker compose exec php php bin/phpunit --testsuite=Unit

test-functional: ## Run functional tests
	docker compose exec php php bin/console doctrine:database:drop --env=test --force --if-exists
	docker compose exec php php bin/console doctrine:database:create --env=test
	docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction
	docker compose exec php php bin/phpunit --testsuite=Functional

.PHONY: help install build up down logs test pint deploy docker-build docker-up docker-down seed

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Install PHP and Node dependencies
	composer install
	npm ci

build: ## Build frontend assets
	npm run build

test: ## Run test suite
	php artisan test

pint: ## Run Laravel Pint
	vendor/bin/pint

seed: ## Seed database
	php artisan db:seed

docker-build: ## Build Docker images
	docker compose build

docker-up: ## Start Docker stack
	@test -f .env.docker || cp .env.docker.example .env.docker
	docker compose up -d

docker-down: ## Stop Docker stack
	docker compose down

docker-logs: ## Tail Docker logs
	docker compose logs -f

deploy: ## Zero-downtime production deploy
	chmod +x scripts/deploy.sh
	./scripts/deploy.sh production

setup-docker: ## First-time Docker setup
	cp -n .env.docker.example .env.docker || true
	@echo "Edit .env.docker and set APP_KEY (run: php artisan key:generate --show)"
	docker compose build
	docker compose up -d
	@echo "Application available at http://localhost:8080"

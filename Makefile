.PHONY: setup composer-install artisan-key-generate artisan-storage-link up ps down fix lint test bash

DOCKER      := docker compose

setup: up composer-install artisan-key-generate artisan-storage-link

composer-install:
	$(DOCKER) exec app composer install

artisan-key-generate:
	$(DOCKER) exec app php artisan key:generate

up:
	$(DOCKER) up -d

ps:
	$(DOCKER) ps

down:
	$(DOCKER) down

fresh:
	$(DOCKER) exec app bash -c "php artisan migrate:fresh --seed "

release:
	$(DOCKER) exec app bash -c "php artisan optimize:clear"

fix:
	$(DOCKER) exec app bash -c "./vendor/bin/pint app config database routes tests"

lint:
	$(DOCKER) exec app bash -c "./vendor/bin/phpstan analyse --no-progress"

test:
	$(DOCKER) exec app php artisan test

bash:
	$(DOCKER) exec app bash

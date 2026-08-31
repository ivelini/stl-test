.PHONY: setup composer-install artisan-key-generate artisan-storage-link up ps down fix lint

setup: up composer-install artisan-key-generate artisan-storage-link

composer-install:
	docker compose exec app composer install

artisan-key-generate:
	docker compose exec app php artisan key:generate

up:
	docker compose up -d

ps:
	docker compose ps

down:
	docker compose down

fresh:
	docker compose exec app bash -c "php artisan migrate:fresh --seed "

release:
	docker compose exec app bash -c "php artisan optimize:clear"

fix:
	docker compose exec app bash -c "./vendor/bin/pint app config database routes tests"

lint:
	docker compose exec app bash -c "./vendor/bin/phpstan analyse --no-progress"

bash:
	docker compose exec app bash

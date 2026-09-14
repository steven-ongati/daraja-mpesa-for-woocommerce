.PHONY: setup check lint lint-fix analyse test lint-js shell

setup:
	docker compose build php
	docker compose run --rm php composer install
	docker compose run --rm node npm ci

check:
	docker compose run --rm php composer check
	docker compose run --rm node npm run lint:js

lint:
	docker compose run --rm php composer lint

lint-fix:
	docker compose run --rm php composer lint:fix

analyse:
	docker compose run --rm php composer analyse

test:
	docker compose run --rm php composer test

lint-js:
	docker compose run --rm node npm run lint:js

shell:
	docker compose run --rm php bash

.PHONY: up down logs ojs-cli install-plugin test lint analyse

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

ojs-cli:
	docker compose exec ojs bash

install-plugin:
	docker compose exec ojs php lib/pkp/tools/installPluginVersion.php plugins/generic/zenodoDeposit/version.xml

test:
	composer test

lint:
	composer lint

analyse:
	composer analyse

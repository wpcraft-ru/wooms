# Makefile для управления окружением разработки и инструментами

## Запуск окружения
up:
	wp-env start
	echo "login: admin:password"

## Запуск с обновлением плагинов (Playground)
update:
	npx wp-env stop
	npx wp-env start --update

## Остановка окружения
stop:
	npx wp-env stop


## additional commands

status: ## Показать статус wp-env окружения
	npx wp-env status

restart: ## Перезапуск окружения с обновлением
	npx wp-env start --update


# Инструменты

cli: ## Открыть shell в контейнере CLI (wp-content/plugins/wooms)
	npx wp-env run cli --env-cwd=wp-content/plugins/wooms sh

tdd: ## Запуск отладочного TDD-теста
	npx wp-env run cli wp test:wooms tests/tdd/debug.php

test: ## Запуск только тестов (wp test:wooms)
	npx wp-env run cli wp test:wooms

test-with-seeding: ## Запуск тестов в окружении wp-env
	npx wp-env run cli wp test:wooms:data-seeding
	npx wp-env run cli wp test:wooms

test-data-seeding: ## Подготовка данных (wp test:wooms:data-seeding)
	npx wp-env run cli wp test:wooms:data-seeding

test-fixtures-prepare: ## Подготовка фикстур (wp test:wooms:fixtures-prepare)
	npx wp-env run cli wp test:wooms:fixtures-prepare

lint: ## Запуск PHPCS в окружении wp-env
	npx wp-env run cli --env-cwd=wp-content/plugins/wooms phpcs


# Action Scheduler

## Старт в фоне (повтор каждую минуту, без логов)
as-daemon:
	@echo "🚀 Запускаем Action Scheduler как daemon (каждые 60 сек, без логов)..."
	@nohup bash -c 'while true; do \
		wp-env run cli -- wp action-scheduler run \
			--batch-size=400 \
			--batches=15 \
			--force \
			--quiet > /dev/null 2>&1 || true; \
		sleep 60; \
	done' > /dev/null 2>&1 &
	@echo "✅ Action Scheduler запущен в фоне."
	@echo "   Интервал: 60 секунд"
	@echo "   Чтобы остановить: make as-stop"

## Остановить Action Scheduler daemon
as-stop:
	@pkill -f "action-scheduler run" || echo "ℹ️  Процессы Action Scheduler не найдены"
	@echo "✅ Action Scheduler остановлен"



# danger commands

## Полное удаление окружения
destroy:
	npx wp-env destroy


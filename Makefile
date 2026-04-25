# Makefile для управления окружением разработки и инструментами

## Запуск окружения
start:
	npx wp-env start
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

AS_DAEMON_PID_FILE := .as-daemon.pid
AS_DAEMON_INTERVAL ?= 60

## Старт в фоне (повтор каждую минуту, без логов)
as-daemon:
	@if [ -f $(AS_DAEMON_PID_FILE) ] && kill -0 $$(cat $(AS_DAEMON_PID_FILE)) 2>/dev/null; then \
		echo "ℹ️  Action Scheduler уже запущен (PID: $$(cat $(AS_DAEMON_PID_FILE)))"; \
		exit 0; \
	fi
	@if ! npx wp-env status >/dev/null 2>&1; then \
		echo "ℹ️  wp-env не запущен, запускаем окружение..."; \
		npx wp-env start >/dev/null; \
	fi
	@echo "🚀 Запускаем Action Scheduler как daemon (каждые 60 сек, без логов)..."
	@nohup npx wp-env run cli -- sh -lc 'while true; do \
		wp action-scheduler run \
			--batch-size=400 \
			--batches=15 \
			--force \
			--quiet > /dev/null 2>&1 || true; \
		sleep $(AS_DAEMON_INTERVAL); \
	done' > /dev/null 2>&1 & echo $$! > $(AS_DAEMON_PID_FILE)
	@echo "✅ Action Scheduler запущен в фоне."
	@echo "   PID: $$(cat $(AS_DAEMON_PID_FILE))"
	@echo "   Интервал: $(AS_DAEMON_INTERVAL) секунд"
	@echo "   Чтобы остановить: make as-stop"

## Остановить Action Scheduler daemon
as-stop:
	@if [ -f $(AS_DAEMON_PID_FILE) ]; then \
		pid=$$(cat $(AS_DAEMON_PID_FILE)); \
		if kill -0 $$pid 2>/dev/null; then \
			kill $$pid; \
			echo "🛑 Остановлен daemon PID: $$pid"; \
		else \
			echo "ℹ️  PID из файла не найден: $$pid"; \
		fi; \
		rm -f $(AS_DAEMON_PID_FILE); \
	else \
		pkill -f "wp-env run cli -- sh -lc.*action-scheduler run" >/dev/null 2>&1 || true; \
		pkill -f "wp-env run cli -- wp action-scheduler run" >/dev/null 2>&1 || true; \
		echo "ℹ️  PID-файл не найден, выполнена очистка по сигнатуре процесса"; \
	fi
	@echo "✅ Action Scheduler остановлен"



# danger commands

## Полное удаление окружения
destroy:
	npx wp-env destroy


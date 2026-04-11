# Makefile для управления окружением разработки и инструментами

start: ## Запуск
	wp-env start
	echo "login: admin:password"

stop: ## Остановка окружения
	npx wp-env stop


## additional commands

start-update: ## Запуск с обновлением плагинов (Playground)
	npx wp-env stop
	npx wp-env start --update

status:
	npx wp-env status

restart: ## Перезапуск с обновлением (Docker)
	npx wp-env start --update



# Action Scheduler в фоне (каждую минуту, без логов)
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

# Остановить Action Scheduler daemon
as-stop:
	@pkill -f "action-scheduler run" || echo "ℹ️  Процессы Action Scheduler не найдены"
	@echo "✅ Action Scheduler остановлен"

## danger commands

destroy: ## Полное удаление окружения
	npx wp-env destroy


# Инструменты

cli: ## Запуск PHPUnit в окружении wp-env
	npx wp-env run cli sh

wp: ## WP-CLI: make cli wp <command>
	npx wp-env run cli wp $(filter-out $@,$(MAKECMDGOALS))

lint: ## Запуск PHPCS в окружении wp-env
	npx wp-env run cli --env-cwd=wp-content/plugins/wooms phpcs

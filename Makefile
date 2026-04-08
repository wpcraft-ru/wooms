# Makefile для управления окружением разработки и инструментами

start: ## Запуск
	npx wp-env start
	echo "login: admin:password"

start-update: ## Запуск с обновлением плагинов (Playground)
	npx wp-env stop
	npx wp-env start --update

start-docker: ## Запуск через Docker (полная функциональность)
	npx wp-env start

stop: ## Остановка окружения
	npx wp-env stop

status:
	npx wp-env status

restart: ## Перезапуск с обновлением (Docker)
	npx wp-env start --update

destroy: ## Полное удаление окружения
	npx wp-env destroy
# -----------------------------------------------------------------------------
# Инструменты
# -----------------------------------------------------------------------------

cli: ## WP-CLI: make cli wp <command>
	npx wp-env run cli wp $(filter-out $@,$(MAKECMDGOALS))

test: ## Запуск PHPUnit в окружении wp-env
	npx wp-env run cli --env-cwd=wp-content/plugins/wooms phpunit

lint: ## Запуск PHPCS в окружении wp-env
	npx wp-env run cli --env-cwd=wp-content/plugins/wooms phpcs

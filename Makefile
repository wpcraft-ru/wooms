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

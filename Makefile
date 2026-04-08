# Makefile для управления окружением разработки и инструментами

start: ## Запуск (Playground — быстрый режим)
	composer dev-start
	echo "login: admin:password"

start-update: ## Запуск с обновлением плагинов (Playground)
	composer dev-start-update

start-docker: ## Запуск через Docker (полная функциональность)
	composer dev-start-docker

stop: ## Остановка окружения
	composer dev-stop

restart: ## Перезапуск с обновлением (Docker)
	composer dev-restart

# -----------------------------------------------------------------------------
# Инструменты
# -----------------------------------------------------------------------------

cli: ## WP-CLI: make cli wp <command>
	composer cli wp $(filter-out $@,$(MAKECMDGOALS))

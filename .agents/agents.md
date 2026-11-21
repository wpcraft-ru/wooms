# WooMS — вход в сессию

Плагин синхронизации МойСклад ↔ WooCommerce. Правила репозитория — `AGENTS.md`, документация — `README.md` и wiki проекта.

## Start here

1. `AGENTS.md` → быстрый старт, API-референсы, ссылки на скиллы.
2. `README.md` → возможности и ограничения.
3. `.agents/skills/` → `moysklad-rest-api`, `woocommerce-backend-dev`.
4. `.agents/memories/` → решения и грабли.

## Запуск и проверки

```bash
make start                 # WordPress на http://localhost:8888
composer dev-start-docker  # полное окружение, тесты, WP-CLI
composer cli wp <command>  # WP-CLI в контейнере
```

## Границы

- Не менять формат обмена с МойСклад без понимания обратной совместимости.
- Тесты и проверки — в Docker-окружении, не в Playground, если нужен WP-CLI.
- Обновления WordPress/PHP — отдельным решением с бэкапом.

## Выученное

Решения → `memories/`.

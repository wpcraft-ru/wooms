# AGENTS.md — WooMS Development

## 🚀 Быстрый старт

```bash
make start
```

WordPress доступен на http://localhost:8888

## API and Code References

### MoySklad REST API

> **Skill:** При работе с MoySklad API используй скилл `moysklad-rest-api` (`.agents/skills/moysklad-rest-api/SKILL.md`).

### WooCommerce

> **Skill:** При работе с WooCommerce backend используй скилл `woocommerce-backend-dev` (`.agents/skills/woocommerce-backend-dev/SKILL.md`).

### Changelog

> **Skill:** При подготовке release notes и обновлении `readme.txt` используй скилл `changelog` (`.agents/skills/changelog/SKILL.md`).


## 📦 Окружение

Проект использует **@wordpress/env** — официальное Docker-окружение для WordPress-разработки.

### Режимы работы

| Режим | Команда | Когда использовать |
|-------|---------|-------------------|
| **Playground** | `composer dev-start` | Быстрая разработка, прототипирование |
| **Docker** | `composer dev-start-docker` | Тесты, WP-CLI, полная функциональность |

### Основные команды

```bash
composer dev-start          # Запуск (Playground)
composer dev-start-docker   # Запуск (Docker)
composer dev-stop           # Остановка
composer dev-restart        # Перезапуск с обновлениями
composer cli wp <command>   # WP-CLI команды
```

---

## 📚 Документация

### .wp-env.json

Конфигурация окружения находится в корне проекта: `.wp-env.json`

**Текущая конфигурация:**
- WordPress: последняя стабильная версия
- PHP: 8.3
- Плагины: текущий проект + WooCommerce (зависимость)
- Отладка: включена (WP_DEBUG, SCRIPT_DEBUG)

**Полезные ссылки:**

- [Официальная документация @wordpress/env](https://developer.wordpress.org/block-editor/packages/packages-env/)
- [GitHub @wordpress/env](https://github.com/WordPress/gutenberg/tree/trunk/packages/env)

### Структура .wp-env.json

```json
{
  "core": null,              // null = последняя стабильная версия
  "phpVersion": "8.3",       // Версия PHP
  "plugins": [
    ".",                     // Текущий плагин (wooms)
    "https://downloads.wordpress.org/plugin/woocommerce.zip"
  ],
  "port": 8888,              // Порт development-сайта
  "config": {                // WP-константы
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "WP_DEBUG_DISPLAY": false,
    "SCRIPT_DEBUG": true
  }
}
```

---

## ✅ Авто-тесты

Используется **PestPHP**.

- `npx wp-env run cli wp test:wooms` запускает тесты внутри `wp-env`
- команда `wp test:wooms` зарегистрирована в `tests/add-wp-cli.php` и вызывает `vendor/bin/pest`
- конфигурация берется из `phpunit.xml`
- `tests/bootstrap.php` подключает WordPress через `wp-load.php`

Ссылки:
- [PestPHP: Introduction](https://pestphp.com/docs/introduction)
- [PestPHP: Expectations](https://pestphp.com/docs/expectations)
- [PestPHP: Filtering Tests](https://pestphp.com/docs/filtering-tests)

Запуск:

```bash
npx wp-env run cli wp test:wooms
npx wp-env run cli wp test:wooms --filter="adds numbers correctly"
npx wp-env run cli wp test:wooms tests/tdd/test.php
```

Зависимости:

```bash
npx wp-env run cli --env-cwd=wp-content/plugins/wooms composer install
```

---

## 🐛 Отладка

### Логи

- WordPress: `wp-content/debug.log`
- Просмотр логов: `composer cli cat wp-content/debug.log`

### Xdebug (Docker режим)

```bash
wp-env start --xdebug
```

VS Code `launch.json`:
```json
{
  "name": "Listen for XDebug",
  "type": "php",
  "request": "launch",
  "port": 9003,
  "pathMappings": {
    "/var/www/html/wp-content/plugins/wooms": "${workspaceFolder}"
  }
}
```

---

## 🔗 Дополнительные ресурсы

- [Инструкции WooMS Wiki](https://github.com/wpcraft-ru/wooms/wiki)
- [Документация WordPress](https://developer.wordpress.org/)
- [Документация WooCommerce](https://developer.woocommerce.com/)
- [МойСклад API](https://dev.moysklad.ru/)

# Интеграция МойСклад и магазинов на базе WooCommerce (WordPress)

WooMS - синхронизация, выгрузка, загрузка

![ezgif com-crop](https://user-images.githubusercontent.com/1852897/83941610-70d42980-a7f5-11ea-9172-65e032e47026.gif)


## Особенности

* Синхронизация товаров по протоколу JSON REST API
* Загрузка категорий
* Импорт изображений и фото по продуктам


## Инструкции и документация

- [Список инструкций](https://github.com/wpcraft-ru/wooms/wiki)

- [Первые шаги](https://github.com/wpcraft-ru/wooms/wiki/GettingStarted)

- [Диагностика проблем - общие рекомендации по сборку информации для первичной диагностики](https://github.com/wpcraft-ru/wooms/wiki/Diagnostics)


## Вопросы и ответы

https://github.com/wpcraft-ru/wooms/issues?q=label%3Aqa

## Где найти разработчиков?

Тут общие инструкции и контакты проверенных и грамотных ребят https://github.com/wpcraft-ru/wooms/wiki/Hire-Developer


## Ссылки

- Исходники https://github.com/wpcraft-ru/wooms
- Каталог WordPress https://wordpress.org/plugins/wooms/
- По вопросам и доработкам https://github.com/wpcraft-ru/wooms/issues


## Изменения и улучшения - changelog

https://github.com/wpcraft-ru/wooms/releases


## 🛠 Локальная разработка

### Требования

- **Node.js** LTS (v20+)
- **Composer**
- **Docker Desktop** (для Docker-режима) или **PHP 8.1+** (для Playground-режима)

### Быстрый старт

```bash
# Установка зависимостей
composer install

# Запуск локального окружения (Playground - без Docker)
composer dev-start

# ИЛИ запуск в Docker-режиме (полная функциональность)
composer dev-start-docker
```

### Окружение

После запуска WordPress доступен:
- **Development:** http://localhost:8888
- **Админка:** http://localhost:8888/wp-admin (логин: `admin`, пароль: `password`)

WooCommerce и плагин WooMS устанавливаются и активируются автоматически.

### Основные команды

```bash
# Запуск окружения
composer dev-start          # Playground режим (быстро, без Docker)
composer dev-start-docker   # Docker режим (полная функциональность)

# Остановка
composer dev-stop

# Перезапуск с обновлениями
composer dev-restart

# WP-CLI команды
composer cli wp plugin list
composer cli wp user list
composer cli wp cache flush

# Тесты
composer test

# Линтинг кода
composer lint
```

### Конфигурация

Настройки окружения в файле `.wp-env.json`:
- WordPress последней версии
- PHP 8.3
- WooCommerce как зависимость
- Отладка включена (WP_DEBUG, SCRIPT_DEBUG)

Для локальных переопределений можно использовать файл `.wp-env.override.json`.
Он имеет приоритет над `.wp-env.json` и подходит для персональных настроек,
которые не должны попадать в репозиторий.

Что важно:
- `.wp-env.override.json` уже добавлен в `.gitignore`
- значения из override-файла переопределяют базовую конфигурацию
- `config` и `mappings` объединяются с базовым файлом
- `plugins` и `themes` не объединяются: если указать их в override-файле, они полностью заменят список из `.wp-env.json`

Пример локального override-файла:

```json
{
	"port": 9000,
	"phpVersion": "8.2",
	"config": {
		"SCRIPT_DEBUG": true
	}
}
```

Если используется кастомный конфиг через `--config`, override-файл ищется по тому же имени.
Например, для `.wp-env.test.json` будет использован `.wp-env.test.override.json`.

### Переключение giữa режимами

**Playground** (рекомендуется для быстрой разработки):
- Не требует Docker
- Быстрый запуск
- Использует SQLite
- ⚠️ Не поддерживает `wp-env run` команды

**Docker** (для тестов и полной функциональности):
- Полноценная MySQL база
- Поддержка WP-CLI команд
- Запуск PHPUnit тестов
- Требует Docker Desktop

```bash
# Переключиться на Docker
composer dev-stop
composer dev-start-docker
```

### Отладка

Логи WordPress:
- `wp-content/debug.log` (внутри контейнера/окружения)
- Доступны через `composer cli wp config get WP_DEBUG_LOG`

Для Xdebug отладки в Docker режиме:
```bash
wp-env start --xdebug
```

### Сброс окружения

```bash
composer dev-stop
composer dev-restart
```

Или полное удаление:
```bash
wp-env destroy
composer dev-start
```

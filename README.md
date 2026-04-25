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

Последние версии и список изменений тут https://github.com/wpcraft-ru/wooms/releases


## 🛠 Локальная разработка

### Требования

- **Node.js** LTS (v20+)
- **Composer**
- **Docker Desktop** (для Docker-режима) или **PHP 8.1+** (для Playground-режима)

### Быстрый старт

```bash

# Запуск локального окружения
make start

# вход в CLI
make cli

# Установка зависимостей для тестов
cd /var/www/html/wp-content/plugins/wooms
composer install

```
### Тестирование

Используется PestPHP.

С хоста: `make test`.

Из CLI Docker:
```
# вход в режим CLI Docker
make cli

# все тесты
wp test:wooms

# только тест с определенным описанием
wp test:wooms --filter="test description"
```


### Окружение

После запуска WordPress доступен:
- **Development:** http://localhost:8888
- **Админка:** http://localhost:8888/wp-admin (логин: `admin`, пароль: `password`)

WooCommerce и плагин WooMS устанавливаются и активируются автоматически.

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


### Отладка

Логи WordPress:
- `wp-content/debug.log` (внутри контейнера/окружения)
- Доступны через `composer cli wp config get WP_DEBUG_LOG`

Для Xdebug отладки в Docker режиме:
```bash
wp-env start --xdebug
```

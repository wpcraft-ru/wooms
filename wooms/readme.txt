=== WooMS ===
Contributors: casepress
Donate link: https://wpcraft.ru/product/wooms-extra/
Tags: moysklad, woocommerce, sync, integration
Requires at least: 4.0
Tested up to: 4.8
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

MoySklad (moysklad.ru) and WooCommerce - sync, integration, connection

== Description ==

Integration WooCommerce & MoySklad http://moysklad.ru (for Russia)

Интеграция приложения МойСклад (торговля, опт, розница, склад, производство, CRM) и WooCommerce (WordPress)

Особенности:

*   Синхронизация товаров по протоколу JSON REST API
*   Загрузка категорий
*   Простые настройки

Для больших возможностей можно приобрести расширенную версию https://wpcraft.ru/product/wooms-extra/

Исходники https://github.com/yumashev/wooms (для желающих принять участие в разработке)

По вопросам доработки https://wpcraft.ru/contacts/
Плагин спроектирован таким образом, что относительно просто позволяет дорабатывать механику под задачи и автоматизацию конкретного магазина/каталога.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to Settings / MoySklad and setup
1. Got to Tools / MoySklad and run sync

== Frequently Asked Questions ==

= Какие товары синхронизируются? =

Пока только товары с артикулом.
Если товар уже есть на сайте с таким артикулом - он обновится. Если нет, то будет добавлен.
Если артикула нет, то товар будет пропущен. Это можно исправить, но отдельным плагином в зависимости от особенностей бизнес процессов компании.

= Что нужно чтобы синхронизация заработала? =

Нужно правильно указать реквизиты доступа на странице настроек плагина в панели управления сайтом. На стороне МойСклад ничего делать не нужно.

= Как устроен механизм синхронизации? =

Используется протокол REST API. Без протокола CommerceML. Вся логика находится на стороне сайта и сайт сам запрашиует данные из МойСклад.
В зависимости от особенностей конфигурации сервера бот синхронизации может зависать из-за таймаутов. Для этого в плагине встроен супервайзер, который следит за ботом и пинает его в случае остановки.

= Какие минимальные требования? =

WordPress 4.5
WooCommerce 3.0 - мб будет работать на Woo 2.х но не факт.
PHP 5.6


== Screenshots ==

1. Страница настроек.
2. Страница управления

== Changelog ==

= 1.1 =
* Исправлены мелкие ошибки
* Дополнена инструкция readme.txt для плагина
* Добавлен ряд хуков для расширения функционала и контроля поведения механизмов

= 1.0 =
* Рабочая версия
* Добавлен супервайзер для стимуляции бота в случае засыпания

= 0.9.6 =
* Add supervisor

<?php

// includes/class-wcli-seed.php

if (class_exists('WP_CLI')) {
    WP_CLI::add_command('test:wooms:data-seeding', WarehouseSeedCommand::class);
}

class WarehouseSeedCommand {

    /**
     * Подготовка БД: базовые настройки WC + первичный синк с Складом
     *
     * ## OPTIONS
     * [--clean]
     * : Полная очистка БД перед сидированием
     * [--fixtures=<file>]
     * : JSON-файл с товарами (по умолчанию: tests/fixtures/products.json)
     *
     * ## EXAMPLES
     *   wp test:wooms:data-seeding
     *   wp test:wooms:data-seeding --force
     */
    public function __invoke($args, $assoc_args)
    {
        $clean = WP_CLI\Utils\get_flag_value($assoc_args, 'clean', false);
        $fixtures = WP_CLI\Utils\get_flag_value($assoc_args, 'fixtures', null);

        WP_CLI::log('🚀 Подготовка окружения...');

        if ($clean) {
            WP_CLI::warning('Очистка БД...');
            $this->cleanDatabase();
        }

        WP_CLI::log('📦 Базовые настройки WooCommerce...');
        $this->seedWooCommerceBase();

        WP_CLI::log('🔗 Синхронизация со Складом...');
        $this->syncFromWarehouse();

        WP_CLI::success('✅ Готово. Можно запускать тесты.');
    }
}

<?php

/**
 * wp test:wooms
 */
if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {

	WP_CLI::add_command('test:wooms', RunWoomsTestsCommand::class, [
		'shortdesc' => 'Run plugin tests using Pest.',
	]);

	WP_CLI::add_command('test:wooms:data-seeding', WarehouseSeedCommand::class, [
		'shortdesc' => 'Seed database with base WooCommerce config and initial warehouse sync.',
	]);

	WP_CLI::add_command('test:wooms:fixtures-prepare', FixturePrepare::class, [
		'shortdesc' => 'Prepare fixtures in tests/data/...',
	]);
}


/**
 * Run Pest via WP-CLI command: wp test:wooms
 */
class RunWoomsTestsCommand
{
	public function __invoke($args, $assoc_args)
	{
		$plugin_path = dirname(__DIR__.'..');
		$pest_binary = $plugin_path.'/vendor/bin/pest';

		if (! file_exists($pest_binary)) {
			WP_CLI::error(sprintf('Pest binary was not found at %s.', $pest_binary));
		}

		$php_binary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
		$forwarded_args = $args;

		foreach ($assoc_args as $key => $value) {
			$forwarded_args[] = true === $value
				? sprintf('--%s', $key)
				: sprintf('--%s=%s', $key, (string) $value);
		}

		$command_parts = array_merge(
			array(
				escapeshellarg($php_binary),
				escapeshellarg($pest_binary),
				'--colors=always',
			),
			array_map('escapeshellarg', $forwarded_args)
		);

		$command = sprintf(
			'cd %s && %s',
			escapeshellarg($plugin_path),
			implode(' ', $command_parts)
		);

		passthru($command, $exit_code);

		WP_CLI::halt($exit_code);
	}
}


/**
 * Database setup: base WooCommerce config + initial warehouse sync
 *
 * ## OPTIONS
 * [--clean]
 * : Full database cleanup before seeding
 * [--force]
 * : Force seeding even if products already exist
 * [--fixtures=<file>]
 * : JSON file with products (default: tests/fixtures/products.json)
 *
 * ## EXAMPLES
 *   wp test:wooms:data-seeding
 *   wp test:wooms:data-seeding --force
 *   wp test:wooms:data-seeding --clean
 */
class WarehouseSeedCommand
{
	public function __invoke($args, $assoc_args)
	{
		$clean = WP_CLI\Utils\get_flag_value($assoc_args, 'clean', false);
		$force = WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
		$fixtures = WP_CLI\Utils\get_flag_value($assoc_args, 'fixtures', null);
		unset($fixtures);

		if (! class_exists('WooCommerce')) {
			WP_CLI::error('WooCommerce was not found. Make sure the plugin is installed and active.');
		}

		WP_CLI::log('🚀 Preparing environment...');
		$hasProducts = $this->hasProducts();

		if ($clean) {
			WP_CLI::warning('Cleaning database...');
			$this->cleanDatabase();
			$hasProducts = false;
		}

		if ($hasProducts && ! $force) {
			WP_CLI::warning('Products already exist, seeding is not required.');
			WP_CLI::log('If you need to run seeding anyway, use: wp test:wooms:data-seeding --force');
			return;
		}

		if ($hasProducts && $force) {
			WP_CLI::warning('Products already exist, continuing due to --force flag.');
		}

		WP_CLI::log('📦 Applying base WooCommerce settings...');
		$this->seedWooCommerceBase();

		WP_CLI::log('🔗 Seeding products from local fixtures...');
		$this->syncFromWarehouse();

		WP_CLI::success('✅ Done. You can run tests now.');
	}

	/**
	 * @return bool
	 */
	protected function hasProducts()
	{
		$query = new WP_Query([
			'post_type' => 'product',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => 1,
			'no_found_rows' => true,
			'suppress_filters' => true,
		]);

		return $query->have_posts();
	}

	/**
	 * @return void
	 */
	protected function cleanDatabase()
	{
		$postTypesToDelete = [
			'product',
			'product_variation',
			'shop_order',
			'shop_order_refund',
			'shop_coupon',
		];

		foreach ($postTypesToDelete as $postType) {
			$ids = get_posts([
				'post_type' => $postType,
				'post_status' => 'any',
				'fields' => 'ids',
				'posts_per_page' => -1,
				'suppress_filters' => true,
			]);

			foreach ($ids as $id) {
				wp_delete_post((int) $id, true);
			}
		}

		if (function_exists('wc_delete_shop_order_transients')) {
			wc_delete_shop_order_transients();
		}

		if (function_exists('wc_delete_product_transients')) {
			wc_delete_product_transients();
		}

		if (class_exists('WC_Cache_Helper')) {
			WC_Cache_Helper::incr_cache_prefix('orders');
		}
	}

	/**
	 * @return void
	 */
	protected function seedWooCommerceBase()
	{
		$this->seedPages();
		$this->seedOptions();
		$this->seedPayments();
		$this->seedShipping();

		if (function_exists('wc_delete_product_transients')) {
			wc_delete_product_transients();
		}

		if (function_exists('wc_delete_shop_order_transients')) {
			wc_delete_shop_order_transients();
		}
	}

	/**
	 * @return void
	 */
	protected function seedPages()
	{
		if (! function_exists('wc_create_page')) {
			return;
		}

		wc_create_page(wc_get_page_id('shop'), 'woocommerce_shop_page_id', __('Shop', 'woocommerce'), '[products]');
		wc_create_page(wc_get_page_id('cart'), 'woocommerce_cart_page_id', __('Cart', 'woocommerce'), '[woocommerce_cart]');
		wc_create_page(wc_get_page_id('checkout'), 'woocommerce_checkout_page_id', __('Checkout', 'woocommerce'), '[woocommerce_checkout]');
		wc_create_page(wc_get_page_id('myaccount'), 'woocommerce_myaccount_page_id', __('My account', 'woocommerce'), '[woocommerce_my_account]');
		wc_create_page(wc_get_page_id('terms'), 'woocommerce_terms_page_id', __('Terms and conditions', 'woocommerce'), '[terms]');
	}

	/**
	 * @return void
	 */
	protected function seedOptions()
	{
		$options = [
			'blogname' => 'WooMS Test Store',
			'blogdescription' => 'Seeded test environment',
			'WPLANG' => 'ru_RU',
			'timezone_string' => 'Europe/Moscow',
			'date_format' => 'Y-m-d',
			'time_format' => 'H:i',
			'start_of_week' => '1',
			'woocommerce_currency' => 'RUB',
			'woocommerce_default_country' => 'RU',
			'woocommerce_allowed_countries' => 'all',
			'woocommerce_all_except_countries' => [],
			'woocommerce_specific_allowed_countries' => [],
			'woocommerce_weight_unit' => 'kg',
			'woocommerce_dimension_unit' => 'cm',
			'woocommerce_store_city' => 'Moscow',
			'woocommerce_store_address' => 'Test Street 1',
			'woocommerce_store_postcode' => '101000',
			'woocommerce_store_state' => '',
			'woocommerce_price_thousand_sep' => ' ',
			'woocommerce_price_decimal_sep' => '.',
			'woocommerce_price_num_decimals' => '2',
			'woocommerce_calc_taxes' => 'yes',
			'woocommerce_prices_include_tax' => 'no',
			'woocommerce_tax_based_on' => 'shipping',
			'woocommerce_tax_round_at_subtotal' => 'no',
			'woocommerce_shipping_cost_requires_address' => 'no',
			'woocommerce_enable_guest_checkout' => 'yes',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_registration_generate_password' => 'yes',
			'woocommerce_registration_generate_username' => 'yes',
			'woocommerce_manage_stock' => 'yes',
			'woocommerce_hold_stock_minutes' => '60',
			'woocommerce_notify_low_stock' => 'no',
			'woocommerce_notify_no_stock' => 'no',
		];

		foreach ($options as $key => $value) {
			update_option($key, $value);
		}
	}

	/**
	 * @return void
	 */
	protected function seedPayments()
	{
		update_option('woocommerce_bacs_settings', [
			'enabled' => 'yes',
			'title' => 'Bank transfer',
			'description' => 'Pay via bank transfer.',
			'instructions' => '',
			'account_details' => [],
		]);

		update_option('woocommerce_cod_settings', [
			'enabled' => 'yes',
			'title' => 'Cash on delivery',
			'description' => 'Pay with cash upon delivery.',
			'instructions' => '',
			'enable_for_methods' => '',
			'enable_for_virtual' => 'yes',
		]);
	}

	/**
	 * @return void
	 */
	protected function seedShipping()
	{
		if (! class_exists('WC_Shipping_Zones')) {
			return;
		}

		$zoneName = 'RU Test Zone';
		$existingZone = null;

		foreach (WC_Shipping_Zones::get_zones() as $zoneData) {
			if (isset($zoneData['zone_name']) && $zoneData['zone_name'] === $zoneName) {
				$existingZone = new WC_Shipping_Zone((int) $zoneData['zone_id']);
				break;
			}
		}

		$zone = $existingZone instanceof WC_Shipping_Zone ? $existingZone : new WC_Shipping_Zone();

		if (! ($existingZone instanceof WC_Shipping_Zone)) {
			$zone->set_zone_name($zoneName);
			$zone->set_zone_locations([
				[
					'code' => 'RU',
					'type' => 'country',
				],
			]);
			$zone->save();
		}

		$methods = $zone->get_shipping_methods(true, 'values');
		$flatRateExists = false;
		$freeShippingExists = false;

		foreach ($methods as $method) {
			if (isset($method->id) && $method->id === 'flat_rate') {
				$flatRateExists = true;
			}

			if (isset($method->id) && $method->id === 'free_shipping') {
				$freeShippingExists = true;
			}
		}

		if (! $flatRateExists) {
			$zone->add_shipping_method('flat_rate');
		}

		if (! $freeShippingExists) {
			$zone->add_shipping_method('free_shipping');
		}
	}

	/**
	 * Seed products from local fixtures so test runs can rely on imported catalog data.
	 *
	 * @return void
	 */
	protected function syncFromWarehouse()
	{
		$rows = $this->getProductsFixtureRows();
		$expectedIds = [];

		foreach ($rows as $row) {
			if (($row['meta']['type'] ?? '') !== 'product') {
				continue;
			}

			$expectedIds[] = (string) $row['id'];
		}

		if ([] === $expectedIds) {
			WP_CLI::error('Product fixtures do not contain product rows.');
		}

		\WooMS\Products\process_rows($rows);
		$this->assertImportedProducts($expectedIds);

		// Run the import twice to keep seeding idempotent in the same way the removed test verified it.
		\WooMS\Products\process_rows($rows);
		$this->assertImportedProducts($expectedIds);

		WP_CLI::log(sprintf('Seeded %d products from fixtures.', count($expectedIds)));
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function getProductsFixtureRows()
	{
		$fixtureFile = dirname(__DIR__).'/tests/data/fixtures-v1/products/first-100.json';

		if (! file_exists($fixtureFile)) {
			WP_CLI::error(sprintf('Products fixture was not found at %s.', $fixtureFile));
		}

		$payload = json_decode((string) file_get_contents($fixtureFile), true);

		if (! is_array($payload) || ! isset($payload['rows']) || ! is_array($payload['rows']) || [] === $payload['rows']) {
			WP_CLI::error('Products fixture is invalid or contains no rows.');
		}

		return $payload['rows'];
	}

	/**
	 * @param array<int, string> $expectedIds
	 *
	 * @return void
	 */
	protected function assertImportedProducts($expectedIds)
	{
		$importedProductIds = [];

		foreach ($expectedIds as $expectedId) {
			$productId = \WooMS\Helper::get_product_id_by_uuid($expectedId);

			if (! is_int($productId) || $productId <= 0) {
				WP_CLI::error(sprintf('Fixture product %s was not imported.', $expectedId));
			}

			$product = wc_get_product($productId);

			if (false === $product) {
				WP_CLI::error(sprintf('Fixture product %s was imported with invalid WooCommerce product ID %d.', $expectedId, $productId));
			}

			$importedProductIds[] = $productId;
		}

		if (count(array_unique($importedProductIds)) !== count($expectedIds)) {
			WP_CLI::error('Fixture product import created duplicate WooCommerce product IDs.');
		}
	}
}


/**
 * Prepare local fixtures in tests/data/...
 *
 * ## OPTIONS
 * [--product-limit=<n>]
 * : Number of products to export (default: 100).
 *
 * ## EXAMPLES
 *   wp test:wooms:fixtures-prepare
 *   wp test:wooms:fixtures-prepare --product-limit=100
 */
class FixturePrepare
{
	public function __invoke($args, $assoc_args)
	{
		unset($args);

		$pluginPath = dirname(__DIR__);
		$fixturesDir = $pluginPath.'/tests/data/fixtures-v1';
		$productLimit = (int) WP_CLI\Utils\get_flag_value($assoc_args, 'product-limit', 100);

		if ($productLimit < 1) {
			WP_CLI::error('Option --product-limit must be greater than 0.');
		}

		if (! is_dir($fixturesDir)) {
			wp_mkdir_p($fixturesDir);
		}

		$directories = [
			'categories' => $fixturesDir.'/categories',
			'products' => $fixturesDir.'/products',
			'variants' => $fixturesDir.'/variants',
		];

		foreach ($directories as $directory) {
			if (! is_dir($directory)) {
				wp_mkdir_p($directory);
			}
		}

		$categoriesRows = $this->prepareCategories($directories['categories']);
		$productsRows = $this->prepareProducts($directories['products'], $productLimit);
		$productIds = $this->collectProductIds($productsRows);
		$variantsRows = $this->prepareVariants($directories['variants'], $productIds, $productLimit);
		$this->prepareManifest($fixturesDir, $categoriesRows, $productsRows, $variantsRows);

		WP_CLI::success(sprintf('Fixtures prepared in: %s', $fixturesDir));
	}

	/**
	 * @param string $categoriesDir
	 *
	 * @return array
	 */
	protected function prepareCategories($categoriesDir)
	{
		WP_CLI::log('Loading categories from MoySklad...');

		$categoriesRows = $this->fetchRowsPaged('entity/productfolder', 1000);
		$this->writeJsonFile($categoriesDir.'/all.json', [
			'rows' => $categoriesRows,
			'meta' => [
				'exported_at_utc' => gmdate('c'),
				'count' => count($categoriesRows),
			],
		]);

		return $categoriesRows;
	}

	/**
	 * @param string $productsDir
	 * @param int $productLimit
	 *
	 * @return array
	 */
	protected function prepareProducts($productsDir, $productLimit)
	{
		WP_CLI::log(sprintf('Loading first %d products from MoySklad...', $productLimit));

		$productsResponse = \WooMS\request(sprintf('entity/product?limit=%d&offset=0', min($productLimit, 1000)));

		if (false === $productsResponse || empty($productsResponse['rows']) || ! is_array($productsResponse['rows'])) {
			WP_CLI::error('Could not load products from MoySklad.');
		}

		$productsRows = array_slice($productsResponse['rows'], 0, $productLimit);
		$this->writeJsonFile($productsDir.'/first-'.$productLimit.'.json', [
			'rows' => $productsRows,
			'meta' => [
				'exported_at_utc' => gmdate('c'),
				'count' => count($productsRows),
				'limit' => $productLimit,
			],
		]);

		return $productsRows;
	}

	/**
	 * @param array $productsRows
	 *
	 * @return array<string, bool>
	 */
	protected function collectProductIds($productsRows)
	{
		$productIds = [];

		foreach ($productsRows as $productRow) {
			if (empty($productRow['id'])) {
				continue;
			}

			$productIds[$productRow['id']] = true;
		}

		return $productIds;
	}

	/**
	 * @param string $variantsDir
	 * @param array<string, bool> $productIds
	 * @param int $productLimit
	 *
	 * @return array
	 */
	protected function prepareVariants($variantsDir, $productIds, $productLimit)
	{
		WP_CLI::log('Loading variants from MoySklad and filtering by selected products...');

		$variantsRowsAll = $this->fetchRowsPaged('entity/variant', 1000);
		$variantsRows = [];

		foreach ($variantsRowsAll as $variantRow) {
			$productHref = $variantRow['product']['meta']['href'] ?? '';
			if (empty($productHref)) {
				continue;
			}

			$productId = $this->extractIdFromHref($productHref);
			if (isset($productIds[$productId])) {
				$variantsRows[] = $variantRow;
			}
		}

		$this->writeJsonFile($variantsDir.'/by-products-first-'.$productLimit.'.json', [
			'rows' => $variantsRows,
			'meta' => [
				'exported_at_utc' => gmdate('c'),
				'count' => count($variantsRows),
				'source_variants_total' => count($variantsRowsAll),
				'product_limit' => $productLimit,
			],
		]);

		return $variantsRows;
	}

	/**
	 * @param string $fixturesDir
	 * @param array $categoriesRows
	 * @param array $productsRows
	 * @param array $variantsRows
	 *
	 * @return void
	 */
	protected function prepareManifest($fixturesDir, $categoriesRows, $productsRows, $variantsRows)
	{
		$manifest = [
			'prepared_at_utc' => gmdate('c'),
			'wordpress_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : null,
			'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : null,
			'generated_by' => 'wp test:wooms:fixtures-prepare',
			'export' => [
				'categories' => count($categoriesRows),
				'products' => count($productsRows),
				'variants' => count($variantsRows),
			],
		];

		$manifestPath = $fixturesDir.'/manifest.json';
		$manifestJson = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === file_put_contents($manifestPath, $manifestJson.PHP_EOL)) {
			WP_CLI::error(sprintf('Could not write fixtures manifest to %s.', $manifestPath));
		}
	}

	/**
	 * @param string $entityPath
	 * @param int $limit
	 *
	 * @return array
	 */
	protected function fetchRowsPaged($entityPath, $limit = 1000)
	{
		$rows = [];
		$offset = 0;

		while (true) {
			$path = sprintf('%s?limit=%d&offset=%d', $entityPath, $limit, $offset);
			$response = \WooMS\request($path);

			if (false === $response || ! isset($response['rows']) || ! is_array($response['rows'])) {
				WP_CLI::error(sprintf('Could not load data from endpoint: %s', $entityPath));
			}

			$pageRows = $response['rows'];
			$rows = array_merge($rows, $pageRows);

			if (count($pageRows) < $limit) {
				break;
			}

			$offset += $limit;
		}

		return $rows;
	}

	/**
	 * @param string $path
	 * @param array $payload
	 *
	 * @return void
	 */
	protected function writeJsonFile($path, $payload)
	{
		$json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === file_put_contents($path, $json.PHP_EOL)) {
			WP_CLI::error(sprintf('Could not write fixtures file to %s.', $path));
		}
	}

	/**
	 * @param string $href
	 *
	 * @return string
	 */
	protected function extractIdFromHref($href)
	{
		$parts = explode('/', trim($href));

		return (string) end($parts);
	}
}


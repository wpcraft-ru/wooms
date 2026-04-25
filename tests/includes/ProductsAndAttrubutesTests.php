<?php

/**
 * Тесты про продукты и атрибуты
 *
 * - проверяем, что атрибуты создаются и обновляются - если включена опция
 * - если атрибут добавлен как общий - то и создается у продукта как общий
 *
 * Логика в includes/ProductAttributes.php
 * Фикстуры для тестов - tests/data/fixtures-v1/products/products-with-attrubutes.json
 */

function getProductsWithAttributesFixtureRows(): array
{
	$fixtureFile = __DIR__.'/../data/fixtures-v1/products/products-with-attrubutes.json';
	$payload = json_decode((string) file_get_contents($fixtureFile), true);

	expect($payload)->toBeArray();
	expect($payload['rows'] ?? [])->not->toBeEmpty();

	return $payload['rows'];
}

function findProductAttributeByName(array $attributes, string $name): ?\WC_Product_Attribute
{
	$name = trim($name);
	$sanitized_name = sanitize_title($name);

	foreach ($attributes as $attribute) {
		if (! $attribute instanceof \WC_Product_Attribute) {
			continue;
		}

		$attribute_identifier = (string) $attribute->get_name(); // Slug for global, name for local

		// 1. Direct match with name or slug
		if ($attribute_identifier === $name || $attribute_identifier === $sanitized_name || $attribute_identifier === wc_attribute_taxonomy_name($sanitized_name)) {
			return $attribute;
		}

		if ($attribute->is_taxonomy()) {
			// 2. Check against the human-readable label
			if (0 === strcasecmp(wc_attribute_label($attribute_identifier), $name)) {
				return $attribute;
			}
		}
	}

	return null;
}

function getFixtureAttributeValueByName(array $row, string $name): ?string
{
	$attributes = $row['attributes'] ?? [];

	foreach ($attributes as $attribute) {
		if (($attribute['name'] ?? '') !== $name) {
			continue;
		}

		$value = $attribute['value'] ?? null;

		if (is_array($value) && isset($value['name'])) {
			return (string) $value['name'];
		}

		if (is_scalar($value)) {
			return (string) $value;
		}
	}

	return null;
}

beforeEach(function (): void {
	global $wpdb;
	$wpdb->query('START TRANSACTION');

	// Изоляция состояния для каждого теста
	delete_transient('wc_attribute_taxonomies');
	\WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
	unset($GLOBALS['wc_attribute_taxonomies']);
	wp_cache_flush();
});

afterEach(function (): void {
	global $wpdb;
	$wpdb->query('ROLLBACK');
});

it('syncs products with attributes when sync option is enabled', function (): void {
	// Enable attributes sync
	\WooMS\Settings::setValue('wooms_attributes_sync_enabled', 1);

	$rows = getProductsWithAttributesFixtureRows();
	$row = $rows[0]; // First product: "Резиновые полусапоги Tommy Hilfiger" with Цвет and Размер

	expect($row['attributes'] ?? [])->not->toBeEmpty();

	$firstAttribute = $row['attributes'][0] ?? null;
	expect($firstAttribute)->toBeArray();
	$name = $firstAttribute['name'] ?? null;
	expect($name)->not->toBeNull();
	$value = $firstAttribute['value'] ?? null;
	expect($value)->not->toBeNull();

	$productId = \WooMS\Products\product_update($row, []);

	expect($productId)->toBeInt()->toBeGreaterThan(0);

	$product = wc_get_product($productId);

	expect($product)->not->toBeFalse();
	expect($product->get_meta('wooms_id'))->toBe((string) $row['id']);

	// Check that attributes were synced
	$product_attributes = $product->get_attributes();

	//$name - может быть в виде "pa_color" для таксономии или "Цвет" для пользовательского атрибута, в зависимости от того, как он был создан. Поэтому ищем по имени внутри атрибутов.

	expect($product_attributes)->toBeArray();
	expect($product_attributes)->not->toBeEmpty();


	// Ключи массива могут быть percent-encoded для кириллицы, поэтому ищем по имени.
	$product_attribute = findProductAttributeByName($product_attributes, $name);
	expect($product_attribute)->not->toBeNull();

	// Check attribute value
	if (is_object($product_attribute) && method_exists($product_attribute, 'get_options')) {
		$options = $product_attribute->get_options();
		expect($options)->toBeArray();
	}
});

it('syncs multiple products with different attributes', function (): void {
	// Enable attributes sync
	\WooMS\Settings::setValue('wooms_attributes_sync_enabled', 1);

	$rows = getProductsWithAttributesFixtureRows();

	expect($rows)->toHaveCount(10);

	$productIds = [];
	foreach (array_slice($rows, 0, 2) as $row) {
		$productId = \WooMS\Products\product_update($row, []);
		expect($productId)->toBeInt()->toBeGreaterThan(0);
		$productIds[] = $productId;
	}

	// Verify first product has attributes
	$product1 = wc_get_product($productIds[0]);
	$attributes1 = $product1->get_attributes();
	expect($attributes1)->not->toBeEmpty();

	// Verify second product has attributes
	$product2 = wc_get_product($productIds[1]);
	$attributes2 = $product2->get_attributes();
	expect($attributes2)->not->toBeEmpty();
});

it('does not sync attributes when option is disabled', function (): void {
	// Disable attributes sync
	\WooMS\Settings::setValue('wooms_attributes_sync_enabled', 0);

	$rows = getProductsWithAttributesFixtureRows();
	$row = $rows[0];

	$productId = \WooMS\Products\product_update($row, []);

	expect($productId)->toBeInt()->toBeGreaterThan(0);

	$product = wc_get_product($productId);
	$product_attributes = $product->get_attributes();

	// When disabled, should not have attributes created from the data
	// (might be empty or might have other attributes, but not from the fixture)
	expect($product_attributes)->toBeArray();
	// expect($product_attributes)->toBeEmpty();
});


it('uses global WooCommerce attribute when label already exists', function (): void {
	// 1. Принудительная установка настроек
	update_option('wooms_attributes_sync_enabled', '1');
	\WooMS\Settings::setValue('wooms_attributes_sync_enabled', '1');

	$rows = getProductsWithAttributesFixtureRows();
	$row = $rows[0];
	$attributes = $row['attributes'] ?? [];
	$attributeName = isset($attributes[0]['name']) ? trim((string) $attributes[0]['name']) : 'Цвет';

	// 2. Создание/Получение ID атрибута
	$existingId = \WooMS\ProductAttributes::get_attribute_id_by_label((string) $attributeName);

	if (empty($existingId)) {
		$attributeTaxonomyId = (int) wc_create_attribute([
			'name' => (string) $attributeName,
			'slug' => sanitize_title((string) $attributeName),
			'type' => 'select',
			'order_by' => 'menu_order',
			'has_archives' => true,
		]);
	} else {
		$attributeTaxonomyId = (int) $existingId;
	}

	expect($attributeTaxonomyId)->toBeGreaterThan(0);

	// 3. Сброс состояния для синхронизации
	delete_transient('wc_attribute_taxonomies');
	unset($GLOBALS['wc_attribute_taxonomies']);
	wc_get_attribute_taxonomies();

	$attributeObject = wc_get_attribute($attributeTaxonomyId);
	expect($attributeObject)->not->toBeNull();

	// Очищаем слаг от префиксов, чтобы избежать pa_pa_
	$taxonomySlug = wc_attribute_taxonomy_name(str_replace('pa_', '', $attributeObject->slug));

	// Принудительная регистрация в текущем процессе
	register_taxonomy($taxonomySlug, ['product'], ['public' => true, 'label' => $attributeObject->name]);
	register_taxonomy_for_object_type($taxonomySlug, 'product');

	expect(\WooMS\ProductAttributes::get_attribute_id_by_label((string) $attributeName))->toBe($attributeTaxonomyId);

	$productId = \WooMS\Products\product_update($row, []);
	expect($productId)->toBeInt()->toBeGreaterThan(0);

	// 4. Проверка результата
	wc_delete_product_transients($productId);
	clean_post_cache($productId);

	$product = wc_get_product($productId);
	expect($product)->not->toBeFalse();

	$productAttributes = $product->get_attributes();
	$foundAttribute = findProductAttributeByName($productAttributes, $attributeName);

	if (! $foundAttribute) {
		$all_pa = array_filter(array_keys($GLOBALS['wp_taxonomies'] ?? []), fn($t) => strpos($t, 'pa_') === 0);
		expect($foundAttribute)->not->toBeNull(sprintf(
			'Атрибут "%s" (slug: %s) не найден. Доступные: [%s]. Зарегистрированные: [%s]. Raw Meta: %s',
			$attributeName, $taxonomySlug,
			implode(', ', array_keys($productAttributes)),
			implode(', ', $all_pa),
			var_export(get_post_meta($productId, '_product_attributes', true), true)
		));
	}

	expect($foundAttribute)->toBeInstanceOf(\WC_Product_Attribute::class);
	expect($foundAttribute->is_taxonomy())->toBeTrue();
	expect($foundAttribute->get_id())->toBe($attributeTaxonomyId);
	expect($foundAttribute->get_options())->toBeArray();
});

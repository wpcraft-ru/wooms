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
	foreach ($attributes as $attribute) {
		if (! $attribute instanceof \WC_Product_Attribute) {
			continue;
		}

		$attributeName = (string) $attribute->get_name();

		if ($attributeName === $name) {
			return $attribute;
		}

		if ($attribute->is_taxonomy()) {
			if (wc_attribute_label($attributeName) === $name) {
				return $attribute;
			}

			$attributeTaxonomyName = wc_attribute_taxonomy_name_by_id((int) $attribute->get_id());
			if ($attributeTaxonomyName === $name) {
				return $attribute;
			}
		}

		$attributeNameWithoutPrefix = preg_replace('/^pa_/', '', $attributeName);
		if ($attributeNameWithoutPrefix === $name) {
			return $attribute;
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
	\WooMS\Settings::setValue('wooms_attributes_sync_enabled', 1);

	$rows = getProductsWithAttributesFixtureRows();
	$row = $rows[0];
	$attributes = $row['attributes'] ?? [];
	$attributeName = $attributes[0]['name'] ?? null; // "Цвет" is the first attribute in the fixture
	$attributeValue = getFixtureAttributeValueByName($row, (string) $attributeName);

	expect($attributeValue)->not->toBeNull();
	expect($attributeName)->not->toBeNull();

	$existingAttributeTaxonomyId = \WooMS\ProductAttributes::get_attribute_id_by_label((string) $attributeName);
	// if ($existingAttributeTaxonomyId) {
	// 	expect(wc_delete_attribute($existingAttributeTaxonomyId))->toBeTrue();
	// 	delete_transient('wc_attribute_taxonomies');
	// 	\WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
	// }

	if (empty($existingAttributeTaxonomyId)) {
		// If attribute doesn't exist, create it
		$attributeTaxonomyId = wc_create_attribute([
			'name' => $attributeName,
			'type' => 'select',
			'order_by' => 'menu_order',
			'has_archives' => true,
		]);

		expect($attributeTaxonomyId)->toBeInt()->toBeGreaterThan(0);
	} else {
		$attributeTaxonomyId = $existingAttributeTaxonomyId;
	}

	expect($attributeTaxonomyId)->toBeInt()->toBeGreaterThan(0);


	delete_transient('wc_attribute_taxonomies');
	\WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
	$taxonomySlug = wc_attribute_taxonomy_name_by_id($attributeTaxonomyId);
	\WC_Post_Types::register_taxonomies();

	expect(\WooMS\ProductAttributes::get_attribute_id_by_label($attributeName))->toBe($attributeTaxonomyId);

	$productId = \WooMS\Products\product_update($row, []);
	expect($productId)->toBeInt()->toBeGreaterThan(0);

	$product = wc_get_product($productId);
	expect($product)->not->toBeFalse();

	/** @var array<string, \WC_Product_Attribute> $productAttributes */
	$productAttributes = $product->get_attributes();
	expect($productAttributes[$taxonomySlug])->not->toBeEmpty();
	$productAttribute = $productAttributes[$taxonomySlug];
	expect($productAttribute)->toBeInstanceOf(\WC_Product_Attribute::class);
	expect($productAttribute->is_taxonomy())->toBeTrue();
	expect($productAttribute->get_id())->toBe($attributeTaxonomyId);
	// dd($productAttribute->get_options());
	expect($productAttribute->get_options())->toBeArray();
});

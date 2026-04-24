<?php

function getProductsFixtureRows(): array {
	$fixtureFile = __DIR__.'/../data/fixtures-v1/products/first-100.json';
	$payload = json_decode((string) file_get_contents($fixtureFile), true);

	expect($payload)->toBeArray();
	expect($payload['rows'] ?? [])->not->toBeEmpty();

	return $payload['rows'];
}

it('updates first product from fixtures v1', function (): void {
	$rows = getProductsFixtureRows();
	$row = $rows[0];

	$productId = \WooMS\Products\product_update($row, []);

	expect($productId)->toBeInt()->toBeGreaterThan(0);

	$product = wc_get_product($productId);

	expect($product)->not->toBeFalse();
	expect($product->get_meta('wooms_id'))->toBe((string) $row['id']);
	expect($product->get_regular_price())->toBe('7990');

	$updatedProductId = \WooMS\Products\product_update($row, []);

	expect($updatedProductId)->toBe($productId);
});

it('processes all exported product rows from fixtures v1', function (): void {
	$rows = getProductsFixtureRows();
	$expectedIds = [];

	foreach ($rows as $row) {
		if (($row['meta']['type'] ?? '') !== 'product') {
			continue;
		}

		$expectedIds[] = (string) $row['id'];
	}

	expect($expectedIds)->not->toBeEmpty();

	\WooMS\Products\process_rows($rows);

	$importedProductIds = [];

	foreach ($expectedIds as $expectedId) {
		$productId = \WooMS\Helper::get_product_id_by_uuid($expectedId);

		expect($productId)->toBeInt()->toBeGreaterThan(0);
		expect(wc_get_product($productId))->not->toBeFalse();

		$importedProductIds[] = $productId;
	}

	expect(array_unique($importedProductIds))->toHaveCount(count($expectedIds));

	\WooMS\Products\process_rows($rows);

	foreach ($expectedIds as $expectedId) {
		$productId = \WooMS\Helper::get_product_id_by_uuid($expectedId);

		expect($productId)->toBeInt()->toBeGreaterThan(0);
	}
});


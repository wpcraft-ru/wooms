<?php

function getProductsFixtureRows(): array {
	$fixtureFile = __DIR__.'/../data/fixtures-v1/products/first-100.json';
	$payload = json_decode((string) file_get_contents($fixtureFile), true);

	expect($payload)->toBeArray();
	expect($payload['rows'] ?? [])->not->toBeEmpty();

	return $payload['rows'];
}

it('has at least one product in catalog', function (): void {
	$products = get_posts([
		'post_type' => 'product',
		'post_status' => 'any',
		'fields' => 'ids',
		'posts_per_page' => 1,
		'no_found_rows' => true,
		'suppress_filters' => true,
	]);

	expect($products)->toBeArray()->not->toBeEmpty();
});

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

<?php

it('loads wordpress core functions', function (): void {
	expect(function_exists('get_post'))->toBeTrue();

	$post = get_posts();

	expect($post)->toBeArray();
});

it('loads WooMS plugin and WooCommerce', function (): void {
	expect(function_exists('WooMS\\request'))->toBeTrue();
	expect(function_exists('wc_get_product'))->toBeTrue();
	expect(class_exists('WooCommerce'))->toBeTrue();
});

it('has available and valid fixtures v1', function (): void {
	$productsFixture = __DIR__.'/../data/fixtures-v1/products/first-100.json';

	expect(file_exists($productsFixture))->toBeTrue();

	$payload = json_decode((string) file_get_contents($productsFixture), true);

	expect($payload)->toBeArray();
	expect($payload['rows'] ?? [])->not->toBeEmpty();

	$firstRow = $payload['rows'][0] ?? [];

	expect($firstRow['id'] ?? '')->not->toBe('');
	expect($firstRow['meta']['type'] ?? '')->toBe('product');
	expect($firstRow['salePrices'] ?? [])->not->toBeEmpty();
});

/**
 * if faile - use wp cli:
 * ```
 * wp test:wooms:data-seeding
 * ```
 */
it('uses ruble currency in seeded WooCommerce settings', function (): void {
	expect((string) get_option('woocommerce_currency'))->toBe('RUB');
	expect((string) get_option('woocommerce_default_country'))->toBe('RU');
	expect((string) get_option('woocommerce_price_num_decimals'))->toBe('2');
});

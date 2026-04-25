<?php
/**
 * Tests for images
 * ProductGallery.php
 * ProductsImage.php
 * ProductVariableImage.php
 */

use function WooMS\Tests\getProductsRows;
use function WooMS\Tests\get_variant;

beforeEach(function (): void {
	global $wpdb;
	$wpdb->query('START TRANSACTION');
});

afterEach(function (): void {
	global $wpdb;
	$wpdb->query('ROLLBACK');

	// cleanup any HTTP stubs created in tests
	remove_all_filters('pre_http_request');
});

function getProductsFixtureRows(): array
{
	$fixtureFile = __DIR__ . '/../data/fixtures-v1/products/first-100.json';
	$payload = json_decode((string) file_get_contents($fixtureFile), true);

	expect($payload)->toBeArray();
	expect($payload['rows'] ?? [])->not->toBeEmpty();

	return $payload['rows'];
}

function getVariantFixtureRows(): array
{
	$fixtureFile = __DIR__ . '/../data/fixtures-v1/variants.json';
	$payload = json_decode((string) file_get_contents($fixtureFile), true);

	expect($payload)->toBeArray();
	expect($payload['rows'] ?? [])->not->toBeEmpty();

	return $payload['rows'];
}

function attachThumbnailWithWoomsUrl(int $variationId, string $woomsUrl): int
{
	$attachmentId = wp_insert_attachment([
		'post_mime_type' => 'image/jpeg',
		'post_title' => 'fixture-image',
		'post_content' => '',
		'post_status' => 'inherit',
	], '', $variationId);

	update_post_meta($attachmentId, 'wooms_url', $woomsUrl);
	update_post_meta($variationId, '_thumbnail_id', $attachmentId);

	return (int) $attachmentId;
}

it('loads fixture products and variants with expected structure', function (): void {
	$products = getProductsFixtureRows();
	$variants = getVariantFixtureRows();

	expect($products)->toBeArray()->not->toBeEmpty();
	expect($variants)->toBeArray()->not->toBeEmpty();
});

it('does not requeue variation image when thumbnail already matches MoySklad downloadHref (fixture)', function (): void {
	expect(class_exists(\WooMS\ProductVariableImage::class))->toBeTrue();

	// Create parent product from fixture
	$productFixture = getProductsFixtureRows()[0];
	$parentId = \WooMS\Products\product_update($productFixture, []);
	expect($parentId)->toBeInt()->toBeGreaterThan(0);

	// Create variation manually
	$variation = new \WC_Product_Variation();
	$variation->set_parent_id($parentId);
	$variation->set_regular_price('100');
	$variationId = $variation->save();

	// Get fixture variant data
	$variantFixture = getVariantFixtureRows()[0];
	$downloadHref = 'https://cdn.moysklad.ru/fixture-image-v1.jpg';

	// First sync: attach thumbnail with wooms_url pointing to same downloadHref
	attachThumbnailWithWoomsUrl($variationId, $downloadHref);

	// Prepare images meta href
	$imagesMetaHref = $variantFixture['images']['meta']['href'] ?? 'https://api.moysklad.ru/api/remap/1.2/entity/variant/metadata/images';

	// Mock HTTP request to return image with same downloadHref
	add_filter('pre_http_request', function ($preempt, $args, $url) use ($imagesMetaHref, $downloadHref) {
		if ($url !== $imagesMetaHref) {
			return $preempt;
		}

		return [
			'headers' => [],
			'body' => wp_json_encode([
				'rows' => [
					[
						'filename' => 'fixture-image-v1.jpg',
						'meta' => [
							'downloadHref' => $downloadHref,
						],
					],
				],
			]),
			'response' => ['code' => 200, 'message' => 'OK'],
			'cookies' => [],
			'filename' => null,
		];
	}, 10, 3);

	$variation = wc_get_product($variationId);
	expect($variation)->not->toBeFalse();

	// Prepare variant data with images meta href for second sync
	$variantWithImages = array_merge($variantFixture, [
		'images' => [
			'meta' => [
				'href' => $imagesMetaHref,
			],
		],
	]);

	$result = \WooMS\ProductVariableImage::add_image_task(
		$variation,
		$variantWithImages,
		$parentId
	);

	expect($result)->toBeInstanceOf(\WC_Product_Variation::class);

	// RED test: current implementation sets wooms_miniature unconditionally.
	// After fix this must remain empty because thumbnail.wooms_url already matches downloadHref.
	expect($result->get_meta('wooms_miniature', true))->toBe('');
});

it('clears malformed variation image task when downloadHref is missing (fixture)', function (): void {
	expect(class_exists(\WooMS\ProductVariableImage::class))->toBeTrue();

	// Create parent product from fixture
	$productFixture = getProductsFixtureRows()[0];
	$parentId = \WooMS\Products\product_update($productFixture, []);
	expect($parentId)->toBeInt()->toBeGreaterThan(0);

	// Create variation manually
	$variation = new \WC_Product_Variation();
	$variation->set_parent_id($parentId);
	$variation->set_regular_price('100');
	$variationId = $variation->save();

	// Inject malformed wooms_miniature metadata (no downloadHref key)
	$variantFixture = getVariantFixtureRows()[0];
	update_post_meta($variationId, 'wooms_miniature', wp_json_encode(array_merge(
		$variantFixture,
		['meta' => []] // Remove downloadHref by clearing meta
	)));

	// Call the worker to process this malformed task
	\WooMS\ProductVariableImage::download_img_for_product($variationId);

	// RED test: current implementation returns early on missing downloadHref
	// and leaves wooms_miniature meta untouched, causing infinite re-processing.
	// After fix, malformed task must be removed and logged as error.
	expect(get_post_meta($variationId, 'wooms_miniature', true))->toBe('');
});

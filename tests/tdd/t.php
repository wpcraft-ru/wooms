<?php

namespace WooMS\Tests\Products;

use WooMS\Products, Error;
use function WooMS\Products\{get_product_id_by_uuid, process_rows, walker};
use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows};
use function WooMS\{request, set_config};

require_once __DIR__ . '/../functions.php';
/**
 * wp test tdd/t.php
 */





/**
 * todo - add test for currencies
 */
test('currency - https://github.com/wpcraft-ru/wooms/issues/516', function(){
	transaction_query('start');

	$rows = \WooMS\Tests\getProductsRows();

	foreach($rows as $row){
		if($row['id'] === 'e94c3184-7644-11ee-0a80-143f001044a3'){
			break;
		}
	}
	$price_usd = 11;

	$currency = \WooMS\CurrencyConverter::get_currency();
	foreach($currency['rows'] as $row_usd){
		if($row_usd['isoCode'] == 'USD'){
			$meta_usd = $row_usd['meta'];
			// ddcli($meta_usd, $row['salePrices'][0]['currency']['meta']);
			$row['salePrices'][0]['currency']['meta'] = $meta_usd;
			$row['salePrices'][0]['value'] = $price_usd * 100;
			// $price_meta = $row['salePrices'][0];
			break;
		}
	}

	update_option(\WooMS\CurrencyConverter::OPTION_KEY, 1);

	$product_id = \WooMS\Products\product_update( $row, $data = [] );

	$product = wc_get_product($product_id);

	$price = $product->get_price();

	transaction_query('rollback');

	if($price > $price_usd){
		return true;
	}

	return false;

}, 1);


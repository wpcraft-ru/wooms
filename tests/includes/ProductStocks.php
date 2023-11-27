<?php
namespace WooMS\Tests\Products;

use Error;
use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows};
use function WooMS\Products\{get_product_id_by_uuid, process_rows};


test('остатки - должно указываться количество - не факт что работает', function(){
	transaction_query('start');

	$data = \WooMS\Tests\get_assortment();

	foreach($data['rows'] as $row){
		if($row['id'] === 'e94c3184-7644-11ee-0a80-143f001044a3'){
			break;
		}
	}

	$product_id = wooms_get_product_id_by_uuid('e94c3184-7644-11ee-0a80-143f001044a3');

	$product = wc_get_product($product_id);

	$product = \WooMS\ProductStocks::update_stock($product, $row);

	transaction_query('rollback');

	if(empty($row['quantity'])){
		throw new Error('$row[quantity] is empty');
	}
	if($row['quantity'] === $product->get_stock_quantity()){
		return true;
	}

	return false;

 });

test('assortment sync - base test - one product', function(){
	transaction_query('start');

	$data = \WooMS\Tests\get_assortment();

	foreach($data['rows'] as $row){
		if($row['id'] === 'e94c3184-7644-11ee-0a80-143f001044a3'){
			break;
		}
	}

	$product_id = wooms_get_product_id_by_uuid('e94c3184-7644-11ee-0a80-143f001044a3');

	$product = wc_get_product($product_id);

	$product = \WooMS\ProductStocks::update_stock($product, $row);

	transaction_query('rollback');

	if(empty($row['quantity'])){
		throw new Error('$row[quantity] is empty');
	}
	if($row['quantity'] === $product->get_stock_quantity()){
		return true;
	}

	return false;

 });


 test('assortment sync - base test', function(){
	transaction_query('start');

	$data = \WooMS\Tests\get_assortment();

	$ids = \WooMS\ProductStocks::process_rows($data['rows']);

	transaction_query('rollback');

	if($ids){
		return true;
	}

	return false;

 });

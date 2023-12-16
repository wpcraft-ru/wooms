<?php
namespace WooMS\Tests\Products;

use Error;
use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows};
use function WooMS\Products\{get_product_id_by_uuid, process_rows};


test('остатки - должно указываться количество - если выбран 1 склад', function(){
	transaction_query('start');

	update_option('wooms_warehouse_count', true);

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

	if( ! $product->get_manage_stock()){
		throw new Error('get_manage_stock - not working');
	}

	if(empty($row['quantity'])){
		throw new Error('$row[quantity] is empty');
	}

	if($row['quantity'] !== $product->get_stock_quantity()){
		throw new Error('quantity not good');

	}

	return true;

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

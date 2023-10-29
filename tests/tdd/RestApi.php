<?php

namespace WooMS\Tests\RestApi;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\{request, set_config};



test('assotment sync - base test - rest api', function(){
	transaction_query('start');

	$args = array(
		'post_type' => [ 'product', 'product_variation' ],
		'numberposts' => 20,
	);

	$products = get_posts( $args );

	foreach ( $products as $product ) {
		update_post_meta( $product->ID, \WooMS\ProductStocks::$walker_hook_name, true );
	}

	$result = \WooMS\ProductStocks::batch_handler();
	transaction_query('rollback');

	if($result){
		return true;
	}


	return false;

 });



/**
 * wp test tdd/RestApi.php
 */
test('check woooms request new api', function(){

	$data = request('https://api.moysklad.ru/api/remap/1.2/entity/product');

	if(isset($data['context'])){
		return true;
	}

	return false;

});

test('check woooms request new api', function(){

	$data = request('entity/product');

	if(isset($data['context'])){
		return true;
	}

	return false;

});

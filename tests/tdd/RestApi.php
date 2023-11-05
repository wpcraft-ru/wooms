<?php

namespace WooMS\Tests\RestApi;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\{request, set_config};



/**
 * for this test we have to use REST API MS
 */
test( 'Test walker', function () {

	transaction_query( 'start' );

	$now = $now = date( "YmdHis" );

	$args = [
		'session_id' => $now,
		'query_arg' => [
			'offset' => 10,
			'limit' => 10,
		],
		'rows_in_bunch' => 20,
		'timestamp' => $now,
		'end_timestamp' => 0,
	];

	$r = \WooMS\Products\walker( $args );

	transaction_query( 'rollback' );

	ddcli($r);

	if ( 'restart' != $r['result'] ) {
		throw new Error('$r[result] should be restart');
	}
	if ( 20 != $r['args_next_iteration']['query_arg']['offset'] ) {
		return false;
	}

	if ( empty( $r['args_next_iteration']['session_id'] ) ) {
		return false;
	}

	return true;

} );


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

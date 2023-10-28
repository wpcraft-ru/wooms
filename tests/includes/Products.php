<?php
namespace WooMS\Tests\Products;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows};
use function WooMS\Products\{get_product_id_by_uuid, process_rows};




/**
 * @todo to fix
 */
test( 'check schedule - false', function () {
	transaction_query( 'start' );

	\WooMS\set_config( 'walker_cron_enabled', 1 );
	\WooMS\set_config( 'walker_cron_timer', 2 );
	\WooMS\Products\set_state( 'end_timestamp', strtotime( '-3 hours' ) );

	$r = as_unschedule_action(\WooMS\Products\HOOK_NAME);

	$result = \WooMS\ProductsScheduler\check_schedule();
	transaction_query( 'rollback' );

	if ( $result ) {
		return true;
	}

	return false;
} , 0);

test( 'check schedule - true', function () {
	transaction_query( 'start' );

	\WooMS\set_config( 'walker_cron_enabled', 1 );
	\WooMS\set_config( 'walker_cron_timer', 2 );
	\WooMS\Products\set_state( 'end_timestamp', strtotime( '-1 hours' ) );

	$result = \WooMS\ProductsScheduler\check_schedule();
	transaction_query( 'rollback' );

	if ( empty( $result ) ) {
		return true;
	}

	return false;
} );



test( 'new product update function', function () {
	transaction_query( 'start' );

	$row = getJsonForSimpleProduct_code00045();


	$product = \WooMS\Products\product_update( $row, $data = [] );

	transaction_query( 'rollback' );

	if ( $row['name'] == $product->get_name() ) {
		return true;
	}
	return false;


} );



/**
 * Яндекс Станция - простой продукт
 */
function getJsonForSimpleProduct_code00045() {
	$rows = getProductsRows();
	foreach ( $rows as $row ) {
		if ( $row['code'] == "00045" ) {
			return $row;
		}
	}
	return false;
}

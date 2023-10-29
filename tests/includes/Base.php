<?php

namespace WooMS\Tests\Base;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Products\{get_product_id_by_uuid, process_rows, walker};

transaction_query( 'start' );

test( 'wooms active?', function () {
	$can_start = wooms_can_start();
	return $can_start;

} );



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

	$r = walker( $args );

	transaction_query( 'rollback' );

	if ( 'restart' != $r['result'] ) {
		return false;
	}
	if ( 20 != $r['args_next_iteration']['query_arg']['offset'] ) {
		return false;
	}

	if ( empty( $r['args_next_iteration']['session_id'] ) ) {
		return false;
	}

	return true;

} );

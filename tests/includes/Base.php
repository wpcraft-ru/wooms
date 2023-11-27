<?php

namespace WooMS\Tests\Base;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Products\{get_product_id_by_uuid, process_rows};


test( 'wooms active?', function () {

	transaction_query( 'start' );

	$can_start = wooms_can_start();

	transaction_query('rollback');

	return $can_start;

} );


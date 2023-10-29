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





test('new', function(){
	transaction_query('start');




	transaction_query('rollback');



	return false;

}, 1);


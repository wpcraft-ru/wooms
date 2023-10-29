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

	ddcli(\WooMS\CurrencyConverter::OPTION_KEY);

	transaction_query('rollback');


}, 0);




// transaction_query('rollback');


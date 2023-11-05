<?php

namespace WooMS\Tests\ProductAttributes;

use WooMS\ProductAttributes, Error;
use function Testeroid\{test, transaction_query, ddcli};

use function WooMS\{request, set_config};

use function WooMS\Tests\{getProductsRows, get_variant};

require_once __DIR__ . '/../functions.php';


test('ProductAttributes - base test', function(){
	transaction_query('start');

	update_option('wooms_attr_enabled', 1);
	$rows = getProductsRows();
	foreach($rows as $row){
		if($row['weight'] > 0){
			break;
		}

	}

	$product_id = \WooMS\Products\product_update( $row, $data = [] );

	$product = wc_get_product($product_id);

	transaction_query('rollback');

	if(empty(intval($product->get_weight()))){
		throw new Error('$product->get_weight() - empty');
	}

	if(intval($product->get_weight()) != intval($row['weight'])){
		throw new Error('weight different');
	}

	return true;

});


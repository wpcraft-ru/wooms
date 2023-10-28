<?php

namespace WooMS\Tests\Products;

use WooMS\Products, Error;
use function WooMS\Products\{get_product_id_by_uuid, process_rows, walker};
use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows};
use function WooMS\{request, set_config};

require_once __DIR__ . '/../functions.php';


transaction_query('start');













test('check woooms request new api', function(){

	$data = request('https://api.moysklad.ru/api/remap/1.2/entity/product');

	if(isset($data['context'])){
		return true;
	}

	return false;

}, 0);

test('check woooms request new api', function(){

	$data = request('entity/product');

	if(isset($data['context'])){
		return true;
	}

	return false;

}, 0);


test('check schedule', function(){

  // \WooMS\Products\set_state('end_timestamp', strtotime('-2 hours'));
  // $result = \WooMS\Scheduler\check_schedule();
  // ddcli($result);

}, 0);

test('check load simple product', function(){

  $row = getJsonForSimpleProduct_code00045();
  $product_id = \WooMS\Products\load_product($row);

  $product = wc_get_product($product_id);
  $name = $product->get_name();

  if($row['name'] == $product->get_name()){
    return true;
  }
  return false;
}, 0);

test('Check request to MS API', function(){
  $path = add_query_arg(['limit' => 1], 'entity/assortment');
  $data = \WooMS\request($path);
  if(isset($data['context'])){
    return true;
  }
  return false;
}, 0);


test('base walker', function(){
  $rows = getProductsRows();
  process_rows($rows);
}, 0);


function getJsonForSimpleProduct_code00045(){
  $rows = getProductsRows();
  foreach($rows as $row){
    if($row['code'] == "00045"){
      return $row;
    }
  }
  return false;
}


transaction_query('rollback');


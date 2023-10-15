<?php

namespace WooMS\Tests\Products;

use WooMS\Products;
use function WooMS\Products\{get_product_id_by_uuid, process_rows};
use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows, getVariantsRows};
use function WooMS\request;

require_once __DIR__ . '/../functions.php';

transaction_query('start');

test('new product update function', function(){

  $row = getJsonForSimpleProduct_code00045();


  $product = \WooMS\Products\product_update($row, $data = []);

  ddcli($product);

});

test('check load simple product', function(){

  $row = getJsonForSimpleProduct_code00045();
  $product_id = \WooMS\Products\load_product($row);

  $product = wc_get_product($product_id);
  $name = $product->get_name();

  if($row['name'] == $product->get_name()){
    return true;
  }
  return false;
});

test('Check request to MS API', function(){
  $path = add_query_arg(['limit' => 1], 'entity/assortment');
  $data = \WooMS\request($path);
  if(isset($data['context'])){
    return true;
  }
  return false;
});


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


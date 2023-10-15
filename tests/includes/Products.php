<?php
namespace WooMS\Tests\Products;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows, getVariantsRows};
use function WooMS\Products\{get_product_id_by_uuid, process_rows};


transaction_query('start');



test('check schedule - false', function(){

  \WooMS\set_config('walker_cron_enabled', 1);
  \WooMS\set_config('walker_cron_timer', 2);
  \WooMS\Products\set_state('end_timestamp', strtotime('-3 hours'));

  $result = \WooMS\Scheduler\check_schedule();

  if($result){
    return true;
  }

  return false;
});

test('check schedule - true', function(){

  \WooMS\set_config('walker_cron_enabled', 1);
  \WooMS\set_config('walker_cron_timer', 2);
  \WooMS\Products\set_state('end_timestamp', strtotime('-1 hours'));

  $result = \WooMS\Scheduler\check_schedule();

  if(empty($result)){
    return true;
  }

  return false;
});



test('new product update function', function(){

  $row = getJsonForSimpleProduct_code00045();


  $product = \WooMS\Products\product_update($row, $data = []);

  if($row['name'] == $product->get_name()){
    return true;
  }
  return false;

  // ddcli($product);

});



/**
 * Яндекс Станция - простой продукт
 */
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

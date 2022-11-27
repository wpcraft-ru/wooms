<?php

use WooMS\Products;
use function WooMS\Products\get_product_id_by_uuid;
use function WooMS\Products\walker;
use PHPUnit\Framework\TestCase;

class ProductsWalkerTest extends TestCase {

  public function test_Walker(){
    $args = array (
      'stop_manual' => 0,
      'timestamp' => '20221127133647',
      'finish' => NULL,
      'wooms_end_timestamp' => null,
      'session_id' => '20221127133647',
      'end_timestamp' => 0,
      'count' => 35,
      'query_arg' => array ( 'offset' => 0, 'limit' => '111', ),
    );

    $resutl = walker($args);
    var_dump($resutl);
    // $json = $this->getJsonForSimpleProduct_code00045();
    // $product_id = get_product_id_by_uuid($json['id']);
		// $this->assertIsInt( $product_id );
  }




  function getProductsRows(){
    $strJsonFileContents = file_get_contents(__DIR__ . "/../json/products.json");
    $data = json_decode($strJsonFileContents, true);
    return $data['rows'];
  }

  protected function setUp(): void
  {
    parent::setUp();

  }

  protected function tearDown(): void
  {
      //TBD
      parent::tearDown();
  }
}

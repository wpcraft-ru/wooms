<?php

use WooMS\Products;
use function WooMS\Products\get_product_id_by_uuid;
use function WooMS\Products\process_rows;
use PHPUnit\Framework\TestCase;

/**
 * Products sync
 */
class SyncAllCatalog extends TestCase {

  private $products_rows = null;

  public function test_process_rows(){

    $result = process_rows($this->products_rows);
    $this->assertTrue($result);

  }

  protected function setUp(): void
  {
    parent::setUp();
    $strJsonFileContents = file_get_contents(__DIR__ . "/../json/products.json");
    $data = json_decode($strJsonFileContents, true);
    $this->products_rows = $data['rows'];
  }

  protected function tearDown(): void
  {
      //TBD
      parent::tearDown();
  }
}

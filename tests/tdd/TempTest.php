<?php

use PHPUnit\Framework\TestCase;
use WooMS\ProductVariable;
/**
 * Products sync
 */
class TempTest extends TestCase {



  /**
   * check product by UUID
   */
  public function test_check(){

    $rows = $this->getVariantsRows();
    $product = wc_get_product(252);
    $wooms_id = $product->get_meta('wooms_id');
    var_dump($wooms_id);
    // ProductVariable::process_rows();

    $this->assertTrue(true);
  }
  function getVariantsRows(){
    $strJsonFileContents = file_get_contents(__DIR__ . "/../json/variants.json");
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

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

    ProductVariable::process_rows($this->getVariantsRows());

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

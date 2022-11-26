<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

use PHPUnit\Framework\TestCase;

/**
 * Sample test case.
 */
class Products extends TestCase {

  private $product_id = null;
	/**
	 * Product Loaded by JSON and get ID
	 */
	public function test_Products_Walker_ProductLoaded() {
    $json = $this->getJsonForSimpleProduct_code00045();

    $product_id = \WooMS\Products\load_product($json);

    $product = wc_get_product($product_id);
    $name = $product->get_name();

    $this->product_id = $product_id;
		$this->assertIsInt( $product_id );
    $this->assertEquals('Яндекс.Станция', $name);
	}


  public function test_CanStart() {
    $can_start = wooms_can_start();

		$this->assertTrue( $can_start );
	}


  function getJsonForSimpleProduct_code00045(){
    $rows = $this->readJsonApiFromFile();
    foreach($rows as $row){
      if($row['code'] == "00045"){
        return $row;
      }
    }
    return false;
  }

  function readJsonApiFromFile(){
    $strJsonFileContents = file_get_contents(__DIR__ . "/json/rows.json");
    $data = json_decode($strJsonFileContents, true);
    return $data;
  }

  protected function setUp(): void
  {
    parent::setUp();
    $json = $this->getJsonForSimpleProduct_code00045();
    $product_id = \WooMS\Products\load_product($json);

    $this->product_id = $product_id;
  }

  protected function tearDown(): void
  {
      //TBD
      parent::tearDown();
  }



}

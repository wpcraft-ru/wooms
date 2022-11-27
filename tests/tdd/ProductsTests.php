<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

use WooMS\Products;
use function WooMS\Products\get_product_id_by_uuid;
use function WooMS\Products\walker;

/**
 * Sample test case.
 */
class ProductsTests extends WP_UnitTestCase {

  private $product_id = null;


  public function test_walker(){
    $data = [
      'stop_manual' => 0,
      'timestamp' => '20221127110457',
      'finish' => NULL,
      'wooms_end_timestamp' => '2022-11-27 11:02:13',
      'session_id' => '20221127110457',
      'end_timestamp' => 0,
      'count' => 0,
      'query_arg' => array ( 'offset' => 0, 'limit' => '20', ),
    ];

    $result = walker();

    var_dump($result);

  }

  /**
   * check product by UUID
   */
  public function test_checkProductByUuid(){
    $json = $this->getJsonForSimpleProduct_code00045();
    $product_id = get_product_id_by_uuid($json['id']);
		$this->assertIsInt( $product_id );
  }

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
    $strJsonFileContents = file_get_contents(__DIR__ . "/../json/rows.json");
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

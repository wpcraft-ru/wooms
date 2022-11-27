<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

use WooMS\Products;
use function WooMS\Products\get_product_id_by_uuid;
use function WooMS\Products\process_rows;


class Main extends WP_UnitTestCase {

  private $product_id = null;

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

  /**
   * test all rows for products
   */
  public function test_process_rows(){
    $rows = $this->getProductsRows();

    $result = process_rows($rows);
    $this->assertTrue($result);

  }

  public function test_AddPost() {

    $new_post = array(
      'post_title'    => 'test post title',
      'post_content'  => 'test post content',
      'post_status'   => 'publish',
    );

    // Insert the post into the database
    $post_id = wp_insert_post( $new_post );


		$this->assertIsInt( $post_id );
	}

  public function test_CanStart() {
    $can_start = wooms_can_start();

		$this->assertTrue( $can_start );
	}


  function getJsonForSimpleProduct_code00045(){
    $rows = $this->getProductsRows();
    foreach($rows as $row){
      if($row['code'] == "00045"){
        return $row;
      }
    }
    return false;
  }


  function getProductsRows(){
    $strJsonFileContents = file_get_contents(__DIR__ . "/../json/products.json");
    $data = json_decode($strJsonFileContents, true);
    return $data['rows'];
  }

  protected function setUp(): void
  {
    parent::setUp();
    //TBD
  }

  protected function tearDown(): void
  {
      //TBD
      parent::tearDown();
  }
}

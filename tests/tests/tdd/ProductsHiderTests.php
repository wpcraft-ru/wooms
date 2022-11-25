<?php
/**
 * Products hide with old sessions
 */

/**
 * Sample test case.
 */
class ProductsHiderTests extends WP_UnitTestCase {


  /**
   * getting products with old session
   */
	public function test_Products_WithOldSessions() {

    $this->prepare_ProductsWithOldSessions();
    $products = \WooMS\ProductsHider\get_products_old_session();

    if( $products && is_array($products) ){
      $res = true;
    } else {
      $res = false;
    }

    $this->assertTrue( $res );
	}


  /**
   * add some products with old session
   */
  function prepare_ProductsWithOldSessions(){

    $now = date("YmdHis");
    \WooMS\Products\set_state('session_id', $now);
    $args = array(
      'post_type'   => 'product',
      'numberposts' => 3,
      'fields'      => 'ids',
      'tax_query'   => array(
        array(
          'taxonomy'  => 'product_visibility',
          'terms'     => array('exclude-from-catalog', 'exclude-from-search'),
          'field'     => 'name',
          'operator'  => 'NOT IN',
        ),
      ),
      'meta_query'  => array(
        array(
          'key'     => 'wooms_session_id',
          'compare' => 'EXISTS',
        ),
        array(
          'key'     => 'wooms_id',
          'compare' => 'EXISTS',
        ),
      ),
    );

    $items = get_posts($args);

    foreach($items as $item_id){
      update_post_meta( $item_id, 'wooms_session_id', 'old-session' );
    }
  }

  public function test_True() {
		$this->assertTrue( true );
	}

}

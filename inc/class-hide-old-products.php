<?php
/**
 * Hide old products
 */
class WooMS_Hide_Old_Products
{
  public function __construct()
  {
    //Main Walker
    add_action( 'init', array($this, 'cron_init'));
    add_action( 'wooms_cron_clear_old_products_walker', array($this, 'cron_starter'));

  }
	
	public function get_session() {
		$session_id = get_option( 'wooms_session_id' );
		if ( empty( $session_id ) ) {
			return false;
		}
		return $session_id;
  }
  
	public function get_product_old_session($offset = 0){
		$args = array(
			'post_type'    => 'product',
			'numberposts' => 5,
			'fields'       => 'ids',
			'offset' => $offset,
			'meta_query' => array(
				array(
					'key'     => 'wooms_session_id',
					'value'   => $this->get_session(),
					'compare' => '!='
				),
				array(
					'key'     => 'wooms_id',
					'compare' => 'EXISTS'
				)
			)
		);

		return get_posts( $args );
	}
 
	public function get_product_new_session($offset){
		$args = array(
			'post_type'    => 'product',
			'numberposts' => 5,
			'fields'       => 'ids',
			'offset' => $offset,
			'meta_query' => array(
				array(
					'key'     => 'wooms_session_id',
					'value'   => $this->get_session(),
					'compare' => '='
				),
				array(
					'key'     => 'wooms_id',
					'compare' => 'EXISTS'
				)
			)
		);
		
		return get_posts( $args );
		
	}
	function walker() {
		
		if ( ! $offset = get_transient( 'wooms_offset_hide_product' ) ) {
			$offset = 0;
			set_transient( 'wooms_offset_hide_product', $offset );
		}
		// do_action('logger_u7', ['tt2', 1]);
		

		//$products = array_diff($this->get_product_old_session(), $this->get_product_new_session());
		$products = $this->get_product_old_session($offset);
		do_action( 'logger_u7', [
			'tt1',
			$this->get_session(),
			$this->get_product_old_session(),
			$this->get_product_new_session()
			]  );
		//do_action( 'logger_u7', [ 'tt2', $this->get_product_new_session() ] );
		$i = 0;
		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			
			$product->set_catalog_visibility('hidden');
			$product->set_stock_status( 'outofstock' );

			//do_action( 'logger_u7', [ 'tt3',$product_id, $offset ] );
			$product->save();
			$i ++;
		}
		set_transient( 'wooms_offset_hide_product', $offset + $i );
		
		if (empty($products)){
			//do_action( 'logger_u7', [ 'tt4',$products, get_transient( 'wooms_offset_hide_product' ) ] );
			delete_transient( 'wooms_offset_hide_product');
		}
	}

  /**
  * Cron task restart
  */
  function cron_init()
  {
    if ( ! wp_next_scheduled( 'wooms_cron_clear_old_products_walker' ) ) {
      wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_clear_old_products_walker' );
    }
  }

  /**
  * Starter walker by cron if option enabled
  */
  function cron_starter(){

    $this->walker();

  }
}

new WooMS_Hide_Old_Products;

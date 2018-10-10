<?php

/**
 * Hide old products
 */
class WooMS_Hide_Old_Products {
	/**
	 * WooMS_Hide_Old_Products constructor.
	 */
	public function __construct() {
		//Main Walker
		add_action( 'init', array( $this, 'cron_init' ) );
		add_action( 'wooms_cron_clear_old_products_walker', array( $this, 'cron_starter' ) );
	}
	
	/**
	 * Cron task restart
	 */
	public function cron_init() {
		if ( ! wp_next_scheduled( 'wooms_cron_clear_old_products_walker' ) ) {
			wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_clear_old_products_walker' );
		}
	}
	
	/**
	 * Starter walker by cron if option enabled
	 */
	public function cron_starter() {
		
		$this->walker();
		
	}
	
	/**
	 * Walker
	 */
	public function walker() {
		
		$this->set_hide_old_product();
		
	}
	
	/**
	 * Adding hiding attributes to products
	 */
	public function set_hide_old_product() {
		if ( ! $offset = get_transient( 'wooms_offset_hide_product' ) ) {
			$offset = 0;
			set_transient( 'wooms_offset_hide_product', $offset );
		}
		
		$products = $this->get_product_old_session( $offset );

		$i = 0;
		
		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			
			if ( $product->get_type() == 'variable' ) {
				$product->set_manage_stock( 'yes' );
			}
			
			$product->set_stock_status( 'outofstock' );
			$product->save();
			$i ++;
			
		}

		do_action('wooms_hide_old_product', $products , $offset);
		
		set_transient( 'wooms_offset_hide_product', $offset + $i );
		
		if ( empty( $products ) ) {
			delete_transient( 'wooms_offset_hide_product' );
		}
		
	}
	
	/**
	 * Obtaining products with specific attributes
	 *
	 * @param int $offset
	 *
	 * @return array
	 */
	public function get_product_old_session( $offset = 0 ) {
		$args = array(
			'post_type'   => 'product',
			'numberposts' => 60,
			'fields'      => 'ids',
			'offset'      => $offset,
			'meta_query'  => array(
				array(
					'key'     => 'wooms_session_id',
					'value'   => $this->get_session(),
					'compare' => '!=',
				),
				array(
					'key'     => 'wooms_id',
					'compare' => 'EXISTS',
				),
			),
			'no_found_rows' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'cache_results' => false
		);
		
		return get_posts( $args );
	}
	
	/**
	 * Method for getting the value of an option
	 *
	 * @return bool|mixed
	 */
	public function get_session() {
		$session_id = get_option( 'wooms_session_id' );
		if ( empty( $session_id ) ) {
			return false;
		}
		
		return $session_id;
	}
}

new WooMS_Hide_Old_Products;

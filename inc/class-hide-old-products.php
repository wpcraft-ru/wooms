<?php

namespace WooMS\Products;

/**
 * Hide old products
 */
class Hide_Old_Products {

	/**
	 * The init
	 */
	public static function init() {
		//Main Walker
		add_action( 'init', array( __CLASS__, 'cron_init' ) );
		add_action( 'wooms_cron_clear_old_products_walker', array( __CLASS__, 'cron_starter' ) );
	}

	/**
	 * Cron task restart
	 */
	public static function cron_init() {
		if ( ! wp_next_scheduled( 'wooms_cron_clear_old_products_walker' ) ) {
			wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_clear_old_products_walker' );
		}
	}

	/**
	 * Starter walker by cron if option enabled
	 */
	public static function cron_starter() {

		self::walker();

	}

	/**
	 * Walker
	 */
	public static function walker() {

		self::set_hide_old_product();

	}

	/**
	 * Adding hiding attributes to products
	 */
	public static function set_hide_old_product() {
		if ( ! $offset = get_transient( 'wooms_offset_hide_product' ) ) {
			$offset = 0;
			set_transient( 'wooms_offset_hide_product', $offset );
		}

		$products = self::get_product_old_session( $offset );
    if( empty($products) ){
      return;
    }

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
	public static function get_product_old_session( $offset = 0 ) {

    $session = self::get_session();
    if(empty($session)){
      return false;
    }

		$args = array(
			'post_type'   => 'product',
			'numberposts' => 60,
			'fields'      => 'ids',
			'offset'      => $offset,
			'meta_query'  => array(
				array(
					'key'     => 'wooms_session_id',
					'value'   => $session,
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
	public static function get_session() {
		$session_id = get_option( 'wooms_session_id' );
		if ( empty( $session_id ) ) {
			return false;
		}

		return $session_id;
	}
}

Hide_Old_Products::init();

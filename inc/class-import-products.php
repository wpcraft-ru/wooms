<?php

/**
 * Import products from MoySklad
 */
class woomss_tool_products_import {
	
	/**
	 * woomss_tool_products_import constructor.
	 */
	public function __construct() {
		//do_action('wooms_product_import_row', $value, $key, $data);
		add_action( 'wooms_product_import_row', [ $this, 'load_data' ], 10, 3 );
	}
	
	
	/**
	 * Load data and set product type simple
	 *
	 * @param $value
	 * @param $key
	 * @param $data
	 */
	public function load_data( $value, $key, $data ) {
		
		if ( ! empty( $value['archived'] ) ) {
			return;
		}
		
		if ( empty( get_option( 'wooms_use_uuid' ) ) ) {
			if ( empty( $value['article'] ) ) {
				return;
			}
		}
		
		if ( ! empty( $value['article'] ) ) {
			$product_id = wc_get_product_id_by_sku( $value['article'] );
		} else {
			$product_id = '';
		}
		
		if ( intval( $product_id ) ) {
			$this->update_product( $product_id, $value );
		} elseif ( $product_id = $this->get_product_id_by_uuid( $value['id'] ) ) {
			$this->update_product( $product_id, $value );
		} else {
			$product_id = $this->add_product( $value );
			$this->update_product( $product_id, $value );
		}
		
		do_action( 'wooms_product_update', $product_id, $value, $data );
		
	}
	
	/**
	 * Update product from source data
	 *
	 * @param $product_id var id product
	 * @param $data_of_source var data of source from MoySklad
	 *
	 */
	public function update_product( $product_id, $data_of_source ) {
		
		wp_set_object_terms( $product_id, 'simple', 'product_type', false );
		
		$product = wc_get_product( $product_id );
		
		//save data of source
		$now = date( "Y-m-d H:i:s" );
		update_post_meta( $product_id, 'wooms_data_of_source', print_r( $data_of_source, true ) );
		
		//Set session id for product
		if ( $session_id = get_option( 'wooms_session_id' ) ) {
			update_post_meta( $product_id, 'wooms_session_id', $session_id );
		}
		
		//the time stamp for database cleanup by cron
		update_post_meta( $product_id, 'wooms_updated_timestamp', $now );
		
		update_post_meta( $product_id, 'wooms_id', $data_of_source['id'] );
		
		//update title
		if ( isset( $data_of_source['name'] ) and $data_of_source['name'] != $product->get_title() ) {
			if ( ! empty( get_option( 'wooms_replace_title' ) ) ) {
				$product->set_name( $data_of_source['name'] );
			}
		}
		
		//update description
		if ( isset( $data_of_source['description'] ) and empty( $product->get_description() ) ) {
			$product->set_description( $data_of_source['description'] );
		}
		
		//Price Retail 'salePrices'
		if ( isset( $data_of_source['salePrices'][0]['value'] ) ) {
			$price_source = floatval( $data_of_source['salePrices'][0]['value'] );
			$price        = apply_filters( 'wooms_product_price', $price_source, $data_of_source );
			
			$price = $price / 100;
			
			$product->set_price( $price );
			$product->set_regular_price( $price );
		}
		
		$product->set_stock_status( 'instock' );
		$product->set_manage_stock( 'no' );
		
		$product->set_status( 'publish' );
		$product->save();
		
	}
	
	/**
	 * Product Check
	 *
	 * @param $uuid
	 *
	 * @return bool
	 */
	public function get_product_id_by_uuid( $uuid ) {
		
		$posts = get_posts( 'post_type=product&meta_key=wooms_id&meta_value=' . $uuid );
		
		if ( empty( $posts[0]->ID ) ) {
			return false;
		} else {
			return $posts[0]->ID;
		}
	}
	
	/**
	 * Add product from source data
	 *
	 * @param $data_of_source var data of source from MoySklad
	 *
	 * @return return bool - true or false if updated
	 */
	public function add_product( $data_source ) {
		
		// $product = new WC_Product_Simple();
		$post_data = array(
			'post_type'   => 'product',
			'post_title'  => wp_filter_post_kses( $data_source['name'] ),
			'post_status' => 'draft',
		);
		
		if ( ! apply_filters( 'wooms_add_product', true, $data_source ) ) {
			return false;
		}
		
		// Вставляем запись в базу данных
		$post_id = wp_insert_post( $post_data );
		
		// $product = wc_get_product($post_id);
		
		if ( empty( $post_id ) ) {
			return false;
		}
		
		update_post_meta( $post_id, $meta_key = 'wooms_id', $meta_value = $data_source['id'] );
		
		if ( isset( $data_source['article'] ) ) {
			update_post_meta( $post_id, $meta_key = '_sku', $meta_value = $data_source['article'] );
		}
		
		return $post_id;
	}
}

new woomss_tool_products_import;

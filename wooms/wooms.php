<?php
/*
Plugin Name: WooMS
Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
Plugin URI: https://wpcraft.ru/product/wooms/
Author: WPCraft
Author URI: https://wpcraft.ru/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Version: 1.9.1
*/

require_once 'inc/class-menu-settings.php';
require_once 'inc/class-menu-tool.php';
require_once 'inc/class-import-products-walker.php';
require_once 'inc/class-import-products.php';
require_once 'inc/class-import-product-categories.php';
require_once 'inc/class-import-product-images.php';
require_once 'inc/class-import-prices.php';
require_once 'inc/class-hide-old-products.php';

/**
* Helper function for get data from moysklad.ru
*/
function wooms_get_data_by_url($url = ''){

  if(empty($url)){
    return false;
  }

  $args = array(
      'headers' => array(
          'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) )
      )
    );

  $response = wp_remote_get( $url, $args );

  if ( is_wp_error( $response ) ){
    set_transient('wooms_error_background', $response->get_error_message());
    return false;
  }

  if ( empty($response['body']) ){
    set_transient('wooms_error_background', "REST API вернулся без требуемых данных");
    return false;
  }

  $data = json_decode( $response['body'], true );

  if(empty($data)){
    set_transient('wooms_error_background', "REST API вернулся без JSON данных");
    return false;
  } else {
    return $data;
  }

}
/**
 * Helper new function for responses data from moysklad.ru
 */
function wooms_request( $url = '', $data = array(), $type = 'GET' ) {
	if ( empty( $url ) ) {
		return false;
	}
	switch ( $type ) {
		case 'GET':
			$data = null;
			break;
		case 'POST':
			$data = json_encode( $data );
			break;
		case 'PUT':
			$data = json_encode( $data );
			break;
	}
	$request = wp_remote_request( $url, array(
		'method'      => $type,
		'timeout'     => 45,
		'redirection' => 5,
		'headers'     => array(
			"Content-Type"  => 'application/json',
			'Authorization' => 'Basic ' .
			                   base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
		),
		'body'        => $data,
	) );
	if ( is_wp_error( $request ) ) {
		set_transient( 'wooms_error_background', $request->get_error_message() );
		
		return false;
	}
	if ( empty( $request['body'] ) ) {
		set_transient( 'wooms_error_background', "REST API вернулся без требуемых данных" );
		
		return false;
	}
	$response = json_decode( $request['body'], true );
	
	return $response;
}
/**
* Get product id by UUID from metafield
* or false
*/
function wooms_get_product_id_by_uuid($uuid){

  $posts = get_posts('post_type=product&meta_key=wooms_id&meta_value='.$uuid);

  if(empty($posts[0]->ID)){
    return false;
  } else {
    return $posts[0]->ID;
  }
}

/**
* Add Settings link in pligins list
*/
function wooms_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=mss-settings">Настройки</a>';
    array_push( $links, $settings_link );
    return $links;
}

add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), 'wooms_plugin_add_settings_link' );

<?php
/*
Plugin Name: WooMS
Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
Plugin URI: https://wpcraft.ru/product/wooms/
Author: WPCraft
Author URI: https://wpcraft.ru/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Version: 1.5.2
*/



require_once 'inc/class-import-products-walker.php';
require_once 'inc/class-import-products.php';
require_once 'inc/class-import-product-categories.php';
require_once 'inc/class-menu-settings.php';
require_once 'inc/class-menu-tool.php';
require_once 'inc/class-cron-walker.php';
require_once 'inc/class-import-supervisor.php';
require_once 'inc/class-import-product-images.php';


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
	$body = $response['body'];

	return json_decode( $body, true );

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

<?php
/*
Plugin Name: WooMS
Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
Plugin URI: https://wpcraft.ru/product/wooms/
Author: WPCraft
Author URI: https://wpcraft.ru/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Version: 0.9.6
*/



require_once 'inc/class-import-products-walker.php';
require_once 'inc/class-import-products.php';
require_once 'inc/class-import-product-categories.php';
require_once 'inc/class-menu-settings.php';
require_once 'inc/class-menu-tool.php';
require_once 'inc/class-cron-walker.php';
require_once 'inc/class-import-supervisor.php';


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

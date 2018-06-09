<?php
/**
 * Plugin Name: WooMS
 * Plugin URI: https://wpcraft.ru/product/wooms/
 * Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
 * Author: WPCraft
 * Author URI: https://wpcraft.ru/
 * Developer: WPCraft
 * Developer URI: https://wpcraft.ru/
 * Text Domain: wooms
 * Domain Path: /languages
 * Version: 2.0.6
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.3.3
 *
 * PHP requires at least: 5.6
 * WP requires at least: 4.8
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$wooms_version = get_file_data( __FILE__, array('wooms_ver' => 'Version') );

define( 'WOOMS_PLUGIN_VER', $wooms_version['wooms_ver'] );


add_action( 'plugins_loaded', 'wooms_check_php_and_wp_version' );
add_action( 'admin_notices', 'wooms_show_notices' );
function wooms_check_php_and_wp_version() {
	global $wp_version;
	$php       = 5.6;
	$wp        = 4.7;
	$php_check = version_compare( PHP_VERSION, $php, '<' );
	$wp_check  = version_compare( $wp_version, $wp, '<' );

	if ( $php_check ) {
		$flag = 'PHP';
	} elseif ( $wp_check ) {
		$flag = 'WordPress';
	}

	if ( $php_check || $wp_check ) {
		$version = 'PHP' == $flag ? $php : $wp;
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		deactivate_plugins( plugin_basename( __FILE__ ) );
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$error_text = sprintf( 'Для корректной работы плагин требует версию <strong>%s %s</strong> или выше.', $flag, $version );
		set_transient( 'wooms_activation_error_message', $error_text, 60 );

	} elseif ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		deactivate_plugins( plugin_basename( __FILE__ ) );
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$error_text = sprintf( 'Для работы плагина WooMS требуется плагин <strong><a href="//wordpress.org/plugins/woocommerce/" target="_blank">%s %s</a></strong> или выше.', 'WooCommerce', '3.0' );
		set_transient( 'wooms_activation_error_message', $error_text, 60 );
	} else {
		wooms_activate_plugin();
		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), 'wooms_plugin_add_settings_link' );
	}
}

function wooms_show_notices() {
	$message = get_transient( 'wooms_activation_error_message' );
	if ( ! empty( $message ) ) {
		echo '<div class="notice notice-error">
            <p><strong>Плагин WooMS не активирован!</strong> ' . $message . '</p>
        </div>';
		delete_transient( 'wooms_activation_error_message' );
	}
}

function wooms_activate_plugin() {
	require_once 'inc/class-menu-settings.php';
	require_once 'inc/class-menu-tool.php';
	require_once 'inc/class-import-products-walker.php';
	require_once 'inc/class-import-products.php';
	require_once 'inc/class-import-product-categories.php';
	require_once 'inc/class-import-product-images.php';
	require_once 'inc/class-import-prices.php';
	require_once 'inc/class-hide-old-products.php';
}

/**
 * Helper function for get data from moysklad.ru
 */
function wooms_get_data_by_url( $url = '' ) {

	if ( empty( $url ) ) {
		return false;
	}
	$args     = array(
		'timeout' => 45,
		'headers' => array(
			'Authorization' => 'Basic ' .
			                   base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
		),
	);
	$response = wp_remote_get( $url, $args );
	if ( is_wp_error( $response ) ) {
		set_transient( 'wooms_error_background', $response->get_error_message() );

		return false;
	}
	if ( empty( $response['body'] ) ) {
		set_transient( 'wooms_error_background', "REST API вернулся без требуемых данных" );

		return false;
	}
	$data = json_decode( $response['body'], true );
	if ( empty( $data ) ) {
		set_transient( 'wooms_error_background', "REST API вернулся без JSON данных" );

		return false;
	} else {
		return $data;
	}
}

/**
 * Helper new function for responses data from moysklad.ru
 *
 *
 * @param string $url
 * @param array $data
 * @param string $type
 *
 * @return array|bool|mixed|object
 */
function wooms_request( $url = '', $data = array(), $type = 'GET' ) {
	if ( empty( $url ) ) {
		return false;
	}
	if ( isset( $data ) && ! empty( $data ) && 'GET' == $type ) {
		$type = 'POST';
	}
	if ( 'GET' == $type ) {
		$data = null;
	} else {
		$data = json_encode( $data );
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
function wooms_get_product_id_by_uuid( $uuid ) {

	$posts = get_posts( 'post_type=product&meta_key=wooms_id&meta_value=' . $uuid );
	if ( empty( $posts[0]->ID ) ) {
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

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
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 *
 * PHP requires at least: 7.0
 * WP requires at least: 5.0
 * Tested up to: 6.4.2
 * WC requires at least: 7.0
 * WC tested up to: 8.4.0
 *
 * Version: 9.12
 */

namespace WooMS;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Add hook for activate plugin
 */
register_activation_hook( __FILE__, function () {
	do_action( 'wooms_activate' );
} );

register_deactivation_hook( __FILE__, function () {
	do_action( 'wooms_deactivate' );
} );


require_once __DIR__ . '/includes/functions.php';

add_action( 'plugins_loaded', function () {
	if ( ! wooms_can_start() ) {
		return;
	}

	$files = glob( __DIR__ . '/includes/*.php' );
	foreach ( $files as $file ) {
		require_once $file;
	}
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\' . 'admin_styles' );
	add_action( 'save_post', 'wooms_id_check_if_unique', 10, 3 );
} );

add_filter( 'plugin_row_meta', __NAMESPACE__ . '\\add_wooms_plugin_row_meta', 10, 2 );


add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), function ($links) {
	$mng_link = '<a href="admin.php?page=moysklad">Управление</a>';
	$settings_link = '<a href="admin.php?page=mss-settings">Настройки</a>';
	array_unshift( $links, $mng_link );
	array_unshift( $links, $settings_link );
	return $links;
} );


/**
 * сообщяем про то что Extra плагин более не актуален
 */
add_action( 'after_plugin_row_wooms-extra/wooms-extra.php', function ($data, $response) {

	$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

	printf(
		'<tr class="plugin-update-tr">
		  <td colspan="%s" class="plugin-update update-message notice inline notice-warning notice-alt">
			<div class="update-message">
			  <span>Этот плагин следует удалить. Теперь все работает на базе 9й версии и в одном плагине</a></span>
			</div>
		  </td>
		</tr>',
		$wp_list_table->get_column_count()
	);
}, 10, 2 );
add_filter( 'wooms_xt_load', '__return_false' );


/**
 * Add GettingStarted link in row meta at pligins list
 */
function add_wooms_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'wooms.php' ) !== false ) {
		$new_links = array(
			'<a href="https://github.com/wpcraft-ru/wooms/wiki/GettingStarted" target="_blank"><strong>Руководство по началу работы</strong></a>',
			'<a href="https://wpcraft.ru/wooms/?utm_source=plugin" target="_blank"><strong>Консультации</strong></a>',
			'<a href="https://github.com/orgs/wpcraft-ru/projects/2" target="_blank"><strong>Задачи</strong></a>',
		);

		$links = array_merge( $links, $new_links );
	}

	return $links;
}


/**
 * Styles for Dashboard
 *
 * @return void
 */
function admin_styles() {
	$admin_style = plugin_dir_url( __FILE__ ) . 'css/admin.css';

	wp_enqueue_style( 'wooms_styles', $admin_style, array() );
}



function get_api_url( $path ) {
	return $url = 'https://api.moysklad.ru/api/remap/1.2/' . $path;
}

function request( $path = '', $data = array(), $type = 'GET' ) {
	// https://api.moysklad.ru/api/remap/1.2/


	if ( empty ( $path ) ) {
		return false;
	}

	if ( str_contains( $path, 'https://api.moysklad.ru/api/remap/1.2/' ) ) {
		$url = $path;
	} else {
		$url = 'https://api.moysklad.ru/api/remap/1.2/' . $path;
	}



	//@link https://github.com/wpcraft-ru/wooms/issues/177
	$url = str_replace( 'product_id', 'product.id', $url );
	$url = str_replace( 'store_id', 'store.id', $url );
	$url = str_replace( 'consignment_id', 'consignment.id', $url );
	$url = str_replace( 'variant_id', 'variant.id', $url );
	$url = str_replace( 'productFolder_id', 'productFolder.id', $url );

	if ( ! empty ( $data ) && 'GET' == $type ) {
		$type = 'POST';
	}
	if ( 'GET' == $type ) {
		$data = null;
	} else {
		$data = json_encode( $data );
	}

	$args = array(
		'method' => $type,
		'timeout' => 45,
		'redirection' => 5,
		'headers' => array(
				"Content-Type" => 'application/json;charset=utf-8',
				"Accept-Encoding" => "gzip",
				'Authorization' => 'Basic ' .
					base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
			),
		'body' => $data,
	);

	$request = wp_remote_request( $url, $args );
	if ( is_wp_error( $request ) ) {
		do_action(
			'wooms_logger_error',
			$type = 'WooMS-Request',
			$title = 'Ошибка REST API WP Error',
			$desc = $request->get_error_message()
		);

		return false;
	}

	if ( empty ( $request['body'] ) ) {
		do_action(
			'wooms_logger_error',
			$type = 'WooMS-Request',
			$title = 'REST API вернулся без требуемых данных'
		);

		return false;
	}

	$response = json_decode( $request['body'], true );

	if ( ! empty ( $response["errors"] ) and is_array( $response["errors"] ) ) {
		foreach ( $response["errors"] as $error ) {
			do_action(
				'wooms_logger_error',
				$type = 'WooMS-Request',
				$title = $url,
				$response
			);
		}
	}

	return $response;
}


function get_session_id() {
	return \WooMS\Products\get_session_id();
}

/**
 * doc https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
 * doc https://woo.com/document/high-performance-order-storage/
 * issue https://github.com/wpcraft-ru/wooms/issues/539
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

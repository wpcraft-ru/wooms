<?php
/**
 * Plugin Name: WooMS
 * Plugin URI: https://wpcraft.ru/product/wooms/
 * Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
 * Author: WPCraft
 * Author URI: https://wpcraft.ru/
 * Developer: WPCraft
 * Developer URI: https://wpcraft.ru/
 * Version: 2.0.4
 * WC requires at least: 3.0
 * WC tested up to: 3.3.3
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
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
	  'timeout'     => 45,
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
			'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
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
 * Helper new function for translit slug data from moysklad.ru
 *
 * @param null $str
 *
 * @return null|string|string[]
 */
function wooms_translit($str = null){
	$tr = array(
		"А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D",
		"Е"=>"E","Ё"=>"Yo","Ж"=>"J","З"=>"Z","И"=>"I",
		"Й"=>"Y","К"=>"K","Л"=>"L","М"=>"M","Н"=>"N",
		"О"=>"O","П"=>"P","Р"=>"R","С"=>"S","Т"=>"T",
		"У"=>"U","Ф"=>"F","Х"=>"H","Ц"=>"C","Ч"=>"Ch",
		"Ш"=>"Sh","Щ"=>"Sch","Ъ"=>"","Ы"=>"Yi","Ь"=>"",
		"Э"=>"E","Ю"=>"Yu","Я"=>"Ya","а"=>"a","б"=>"b",
		"в"=>"v","г"=>"g","д"=>"d","е"=>"e","ё"=>"yo","ж"=>"j",
		"з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
		"м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
		"с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
		"ц"=>"c","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
		"ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya",
		" "=> "-", "."=> "", "/"=> "_"
	);
	$str = strtr($str,$tr);
	if (preg_match('/[^A-Za-z0-9_\-]/', $str)) {
		$str = preg_replace('/[^A-Za-z0-9_\-]/', '', $str);
	}
	return $str;
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

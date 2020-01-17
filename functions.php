<?php 
/**
 * General function
 */

/**
 * Helper new function for responses data from moysklad.ru
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
  
    $url = wooms_fix_url($url);
  
    if ( isset( $data ) && ! empty( $data ) && 'GET' == $type ) {
      $type = 'POST';
    }
    if ( 'GET' == $type ) {
      $data = null;
    } else {
      $data = json_encode( $data );
    }
  
      $args = array(
      'method'      => $type,
      'timeout'     => 45,
      'redirection' => 5,
      'headers'     => array(
        "Content-Type"  => 'application/json',
        'Authorization' => 'Basic ' .
                           base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
      ),
      'body'        => $data,
    );
  
    $request = wp_remote_request( $url, $args);
    if ( is_wp_error( $request ) ) {
      do_action(
        'wooms_logger_error',
        $type = 'Request',
        $title = 'Ошибка REST API',
        $desc = $request->get_error_message()
      );
  
      return false;
    }
  
    if ( empty( $request['body'] ) ) {
      do_action(
        'wooms_logger_error',
        $type = 'Request',
        $title = 'REST API вернулся без требуемых данных'
      );
  
      return false;
    }
  
    $response = json_decode( $request['body'], true );
  
    if( ! empty($response["errors"]) and is_array($response["errors"]) ){
      foreach ($response["errors"] as $error) {
        do_action(
          'wooms_logger_error',
          $type = 'Request',
          $title = $error['error']
        );
      }
    }
  
    return $response;
  }
  
  /**
   * Get product id by UUID from metafield
   * or false
   *
   * XXX move to \WooMS\Products\Bundle::get_product_id_by_uuid
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
   * fix bug with url
   *
   * @link https://github.com/wpcraft-ru/wooms/issues/177 
   */
  function wooms_fix_url($url = ''){
      $url = str_replace('product_id', 'product.id', $url);
      $url = str_replace('store_id', 'store.id', $url);
      $url = str_replace('consignment_id', 'consignment.id', $url);
      $url = str_replace('variant_id', 'variant.id', $url);
      $url = str_replace('productFolder_id', 'productFolder.id', $url);
      return $url;
  }
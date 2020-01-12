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
  
  /**
   * Download Image by URL and retrun att id or false or WP_Error
   *
   * @param [type] $url_api
   * @param [type] $file_name
   * @param [type] $post_id
   * @return void
   */
  function download_img($url_api, $file_name, $post_id)
  {

    if ($check_id = check_exist_image_by_url($url_api)) {
      return $check_id;
    }

    if (!function_exists('curl_init')) {
      do_action(
        'wooms_logger_error',
        __CLASS__,
        'Не удалось обнаружить curl_init. Нужно настроить curl на сервера.'
      );
      return false;
    }

    if (!function_exists('wp_read_image_metadata')) {
      require_once ABSPATH . '/wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $header_array = [
      'Authorization' => 'Basic ' . base64_encode(get_option('woomss_login') . ':' . get_option('woomss_pass')),
    ];

    $headers = array();
    foreach ($header_array as $name => $value) {
      $headers[] = "{$name}: $value";
    }

    $ch = \curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Checking operation system if windows add needed parameter or it will not work
    //If PHP_SHLIB_SUFFIX is equal to "dll",
    //then PHP is running on a Windows operating system.
    if(strtolower(PHP_SHLIB_SUFFIX) === 'dll'){
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $output = curl_exec($ch);
    $info   = curl_getinfo($ch); // Получим информацию об операции
    curl_close($ch);

    $file_name = sanitize_file_name($file_name);
    $tmpfname = wp_tempnam($file_name);
    $fh       = fopen($tmpfname, 'w');

    fwrite($fh, $output);

    fclose($fh);

    $filetype = wp_check_filetype($file_name);

    // Array based on $_FILE as seen in PHP file uploads.
    $file_args = array(
      'name'     => $file_name, // ex: wp-header-logo.png
      'type'     => $filetype['type'], //todo do right
      'tmp_name' => $tmpfname,
      'error'    => 0,
      'size'     => filesize($tmpfname),
    );

    $overrides = array(
      'test_form'   => false,
      'test_size'   => false,
      'test_upload' => false,
    );

    $file_data = wp_handle_sideload($file_args, $overrides);

    // If error storing permanently, unlink.
    if (is_wp_error($file_data)) {
      @unlink($tmpfname);
      do_action(
        'wooms_logger_error',
        __CLASS__,
        'Загрузка картинки - не удалось получить файл',
        sprintf('Данные %s', PHP_EOL . print_r($file_data, true))
      );

      return false;
    }

    if (empty($file_data['url'])) {
      do_action(
        'wooms_logger_error',
        __CLASS__,
        'Загрузка картинки - не удалось получить URL',
        sprintf('Данные %s', PHP_EOL . print_r($file_data, true))
      );
      @unlink($tmpfname);

      return false;
    }

    $url     = $file_data['url'];
    $type    = $file_data['type'];
    $file    = $file_data['file'];
    $title   = preg_replace('/\.[^.]+$/', '', basename($file));
    $content = '';

    // Use image exif/iptc data for title and caption defaults if possible.
    if ($image_meta = \wp_read_image_metadata($file)) {
      if (trim($image_meta['title']) && !is_numeric(sanitize_title($image_meta['title']))) {
        $title = $image_meta['title'];
      }
      if (trim($image_meta['caption'])) {
        $content = $image_meta['caption'];
      }
    }

    if (isset($desc)) {
      $title = $desc;
    }

    // Construct the attachment array.
    $attachment = array(
      'post_mime_type' => $type,
      'guid'           => $url,
      'post_parent'    => $post_id,
      'post_title'     => $title,
      'post_content'   => $content,
    );

    // This should never be set as it would then overwrite an existing attachment.
    unset($attachment['ID']);

    // Save the attachment metadata
    $id = wp_insert_attachment($attachment, $file, $post_id);
    if (!is_wp_error($id)) {
      wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
    } else {
      return false;
    }

    @unlink($tmpfname);

    update_post_meta($id, 'wooms_url', $url_api);

    return $id;
  }

  /**
   * Check exist image by URL
   *
   * @param [type] $url_api
   * @return void
   */
  function check_exist_image_by_url($url_api)
  {
    $posts = get_posts('post_type=attachment&meta_key=wooms_url&meta_value=' . $url_api);

    if (empty($posts)) {
      return false;
    } else {
      return $posts[0]->ID;
    }
  }
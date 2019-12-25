<?php

namespace WooMS\Products;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class ImagesGallery
{

  /**
   * WooMS_Import_Product_Images constructor.
   */
  public static function init()
  {

    /**
     * Обновление данных о продукте
     */
    add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 40, 3);
  }

  /**
   * update_product
   */
  public static function update_product($product, $value, $data)
  {


    if (empty(get_option('woomss_images_sync_enabled'))) {
      return $product;
    }
    $product_id = $product->get_id();

    // Getting data from mysklad product directly using id of product
    $pm_id = get_post_meta($product_id, 'wooms_id', true);
    $url = 'https://online.moysklad.ru/api/remap/1.2/entity/product/' . $pm_id . '/images';
    $data_api = wooms_request($url);

    //Check image
    if (empty($data_api['rows'])) {
      return $product;
    }

    // Making array with image data
    $product_gallery_data = [];

    foreach ($data_api['rows'] as $image) {

      $product_gallery_data[$image['filename']] = $image['meta']['downloadHref'];
    }

    // encoding array to json
    $product_gallery_data = json_encode($product_gallery_data);


    self::download_images_from_metafield();

    // check current meta is set already or not
    if (!empty(get_post_meta($product_id, 'wooms_data_for_get_gallery'))) {
      return $product;
    } else {
      $product->update_meta_data('wooms_data_for_get_gallery', $product_gallery_data);
    }

    return $product;
  }

  /**
   * Download images from meta
   *
   * @return void
   */
  public static function download_images_from_metafield()
  {

    if (empty(get_option('woomss_images_sync_enabled'))) {
      return;
    }

    $args = array(
      'post_type'              => 'product',
      'meta_query'             => array(
        array(
          'key'     => 'wooms_data_for_get_gallery',
          'compare' => 'EXISTS',
        ),
      ),
      'no_found_rows'          => true,
      'update_post_term_cache' => false,
      'update_post_meta_cache' => false,
      'cache_results'          => false,
    );

    $list = get_posts($args);

    if (empty($list)) {
      return false;
    }

    $result = [];

    foreach ($list as $key => $value) {
      $img_data_list = get_post_meta($value->ID, 'wooms_data_for_get_gallery', true);
      $img_data_list = json_decode($img_data_list);

      $media_id_list = [];

      foreach ($img_data_list as $image_name => $url) {
        $media_id_list[] = self::download_img($url, $image_name, $value->ID);
      }

      if (!empty($media_id_list)) {

        // Set the gallery images
        update_post_meta($value->ID, '_product_image_gallery', implode(',', $media_id_list));
        // Delte meta for correct query work
        delete_post_meta($value->ID, 'wooms_data_for_get_gallery');

        $result[] = $value->ID;

        do_action(
          'wooms_logger',
          __CLASS__,
          sprintf('Загружена картинка для продукта %s (ИД %s, filename: %s)', $value->ID, $media_id_list, $image_name)
        );
      } else {
        do_action(
          'wooms_logger_error',
          __CLASS__,
          sprintf('Ошибка назначения картинки для продукта %s (url %s, filename: %s)', $value->ID, $url, $image_name)
        );
      }
    }

    if (empty($result)) {
      return false;
    } else {
      return $result;
    }
  }

  /**
   * Download Image by URL and retrun att id or false or WP_Error
   *
   * @param [type] $url_api
   * @param [type] $file_name
   * @param [type] $post_id
   * @return void
   */
  public static function download_img($url_api, $file_name, $post_id)
  {

    if ($check_id = self::check_exist_image_by_url($url_api)) {
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
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $output = curl_exec($ch);
    $info   = curl_getinfo($ch); // Получим информацию об операции
    curl_close($ch);


    if (!function_exists('wp_tempnam')) {
      require_once(ABSPATH . 'wp-admin/includes/file.php');
      require_once(ABSPATH . 'wp-admin/includes/image.php');
    }

    $file_name = sanitize_file_name($file_name);
    $tmpfname = wp_tempnam($file_name);
    $fh       = fopen($tmpfname, 'wb');



    if ($url_api == $info['url']) { //если редиректа нет записываем файл
      fwrite($fh, $output);
    } else {

      // fix https://github.com/wpcraft-ru/wooms/issues/203
      $context_options = array(
        "ssl" => array(
          "verify_peer" => false,
          "verify_peer_name" => false,
        ),
      );

      //если редирект есть то скачиваем файл по ссылке
      $file = file_get_contents($info['url'], false, stream_context_create($context_options));

      if (!$file) {
        do_action(
          'wooms_logger_error',
          __CLASS__,
          'Загрузка картинки - не удалось закачать файл',
          sprintf('Данные %s', PHP_EOL . print_r($info['url'], true))
        );
        return false;
      }

      fwrite($fh, $file);
    }


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
  public static function check_exist_image_by_url($url_api)
  {

    $posts = get_posts('post_type=attachment&meta_key=wooms_url&meta_value=' . $url_api);

    if (empty($posts)) {
      return false;
    } else {
      return $posts[0]->ID;
    }
  }
}

ImagesGallery::init();

<?php

namespace WooMS;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class ImagesGallery
{

  use MSImages;

  /**
   * WooMS_Import_Product_Images constructor.
   */
  public static function init()
  {

    add_filter('wooms_product_save', [__CLASS__, 'update_product'], 40, 3);

    add_action('admin_init', [__CLASS__, 'settings_init'], 70);

    add_action('init', [__CLASS__, 'add_schedule_hook']);

    add_action('gallery_images_download_schedule', [__CLASS__, 'download_images_from_metafield']);
  }

  /**
   * update_product
   */
  public static function update_product($product, $value, $data)
  {


    if (empty(get_option('woomss_gallery_sync_enabled'))) {
      return $product;
    }
    $product_id = $product->get_id();

    self::get_gallery_from_api($product_id);

    return $product;
  }

  public static function get_gallery_from_api($product_id)
  {
    // Getting data from mysklad product directly using id of product
    $pm_id = get_post_meta($product_id, 'wooms_id', true);
    $url = sprintf('https://online.moysklad.ru/api/remap/1.2/entity/product/%s/images', $pm_id);
    $data_api = wooms_request($url);

    //Check image
    if (empty($data_api['rows']) || count($data_api['rows']) == 1) {
      return false;
    }

    // Making array with image data
    $product_gallery_data = [];

    foreach ($data_api['rows'] as $key => $image) {
      // First key is the first image that already downloading with another class https://github.com/uptimizt/dev-wms-local/issues/4
      if ($key !== 0) {
        $product_gallery_data[$image['filename']] = $image['meta']['downloadHref'];
      }
    }

    // encoding array to json
    $product_gallery_data = json_encode($product_gallery_data);

    // check current meta is set already or not
    if (!empty(get_post_meta($product_id, 'wooms_data_for_get_gallery'))) {
      return false;
    } else {
      update_post_meta($product_id, 'wooms_data_for_get_gallery', $product_gallery_data);
    }
  }

  /**
   * Setup schedule
   *
   * @return mixed
   */
  public static function add_schedule_hook()
  {

    if (empty(get_option('woomss_gallery_sync_enabled'))) {
      return;
    }

    if (!as_next_scheduled_action('gallery_images_download_schedule')) {
      as_schedule_recurring_action(
        time(),
        60,
        'gallery_images_download_schedule'
      );
    }

  }

  /**
   * Download images from meta
   *
   * @return void
   */
  public static function download_images_from_metafield($pid = 0)
  {

    if (empty(get_option('woomss_gallery_sync_enabled'))) {
      return;
    }

    $args = array(
      'post_type'              => 'product',
      'numberposts'            => 1,
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
      $result[] = self::download_images_by_id($value->ID);
    }

    if (empty($result)) {
      return false;
    } else {
      return $result;
    }
  }

  /**
   * Downloading gallery images from meta product
   *
   * @param [type] $product_id
   * @return void
   */
  public static function download_images_by_id($product_id, $all = false)
  {
    $img_data_list = get_post_meta($product_id, 'wooms_data_for_get_gallery', true);

    if (empty($img_data_list)) {
      self::get_gallery_from_api($product_id);
      $img_data_list = get_post_meta($product_id, 'wooms_data_for_get_gallery', true);
    }

    $img_data_list = json_decode($img_data_list, true);

    $media_data_list = [];

    foreach ($img_data_list as $image_name => $url) {

      $media_id = self::uploadRemoteImageAndAttach($url, $product_id, $image_name);

      if (!empty($media_id)) {
        $media_data_list[] = $media_id;
      }
    }

    if (!empty($media_data_list)) {

      // Set the gallery images
      update_post_meta($product_id, '_product_image_gallery', implode(',', $media_data_list));

      // Delete meta for correct query work
      delete_post_meta($product_id, 'wooms_data_for_get_gallery');

      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('Загружена картинка для продукта %s (ИД %s, filename: %s)', $product_id, $media_id, $image_name)
      );
    } else {
      do_action(
        'wooms_logger_error',
        __CLASS__,
        sprintf('Ошибка нозначения галереи продукта %s', $product_id)
      );
    }

    return $product_id;
  }

  /**
   * Settings UI
   */
  public static function settings_init()
  {
    add_settings_section('woomss_section_images', 'Изображения', null, 'mss-settings');

    register_setting('mss-settings', 'woomss_gallery_sync_enabled');
    add_settings_field(
      $id = 'woomss_gallery_sync_enabled',
      $title = 'Включить синхронизацию Галлереи',
      $callback = array(__CLASS__, 'setting_images_sync_enabled'),
      $page = 'mss-settings',
      $section = 'woomss_section_images'
    );

    register_setting('mss-settings', 'woomss_gallery_replace_to_sync');
    add_settings_field(
      'woomss_gallery_replace_to_sync',
      'Замена изображений Галлереи при синхронизации',
      array(__CLASS__, 'setting_images_replace_to_sync'),
      $page = 'mss-settings',
      $section = 'woomss_section_images'
    );
  }

  /**
   * setting_images_replace_to_sync
   */
  public static function setting_images_replace_to_sync()
  {

    $option = 'woomss_gallery_sync_enabled';
    $desc = '<small>Внимание! Это тест опция! Если включить опцию, то плагин будет обновлять изображения галереи, если они изменились в МойСклад.</small><p><small><strong>Внимание!</strong> Для корректной перезаписи изображений, необходимо провести повторную синхронизацию товаров. Если синхронизация товаров происходит по крону, то дождаться окончания очередной сессии синхронизации товаров</small></p>';
    printf('<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked(1, get_option($option), false), $desc);
  }

  /**
   * setting_images_sync_enabled
   */
  public static function setting_images_sync_enabled()
  {
    $option = 'woomss_gallery_replace_to_sync';
    $desc = '<small>Внимание! Это тест опция! Если включить опцию, то плагин будет загружать изображения галереи из МойСклад.</small>';
    printf('<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked(1, get_option($option), false), $desc);
  }
}

ImagesGallery::init();

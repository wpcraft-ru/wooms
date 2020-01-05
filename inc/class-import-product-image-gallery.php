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

    add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 40, 3);

    add_action( 'admin_init', array( __CLASS__, 'settings_init' ), 50 );

    add_filter('cron_schedules', array(__CLASS__, 'add_schedule'));

    add_action('init', array(__CLASS__, 'add_cron_hook'));

    add_action('wooms_cron_image_downloads', array(__CLASS__, 'download_images_from_metafield'));
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

    foreach ($data_api['rows'] as $key => $image) {
      // First key is the first image that already downloading with another class https://github.com/uptimizt/dev-wms-local/issues/4
      if( $key !== 0){
        $product_gallery_data[$image['filename']] = $image['meta']['downloadHref'];
      }
    }

    // encoding array to json
    $product_gallery_data = json_encode($product_gallery_data);


    // check current meta is set already or not
    if (!empty(get_post_meta($product_id, 'wooms_data_for_get_gallery'))) {
      return $product;
    } else {
      $product->update_meta_data('wooms_data_for_get_gallery', $product_gallery_data);
    }

    return $product;
  }

  /**
   * Setup cron
   *
   * @param $schedules
   *
   * @return mixed
   */
  public static function add_schedule($schedules)
  {

    $schedules['wooms_cron_worker_images'] = array(
      'interval' => 60,
      'display'  => 'WooMS Cron Load Images 60 sec',
    );

    return $schedules;
  }

  /**
   * Init Cron
   */
  public static function add_cron_hook()
  {

    if (empty(get_option('woomss_gallery_sync_enabled'))) {
      return;
    }

    if (!wp_next_scheduled('wooms_cron_image_downloads')) {
      wp_schedule_event(time(), 'wooms_cron_worker_images', 'wooms_cron_image_downloads');
    }
  }


  /**
   * Action for UI
   */
  public static function ui_action()
  {

    $data = self::download_images_from_metafield();

    echo '<hr>';

    if (empty($data)) {
      echo '<p>Нет картинок для загрузки</p>';
    } else {
      echo "<p>Загружены миниатюры для продуктов:</p>";
      foreach ($data as $key => $value) {
        printf('<p><a href="%s">ID %s</a></p>', get_edit_post_link($value), $value);
      }
      echo "<p>Чтобы повторить загрузку - обновите страницу</p>";
    }
  }

  /**
   * Download images from meta
   *
   * @return void
   */
  public static function download_images_from_metafield()
  {

    if (empty(get_option('woomss_gallery_sync_enabled'))) {
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
        $media_id_list[] = download_img($url, $image_name, $value->ID);
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
    $desc = '<small>Если включить опцию, то плагин будет обновлять изображения галереи, если они изменились в МойСклад.</small><p><small><strong>Внимание!</strong> Для корректной перезаписи изображений, необходимо провести повторную синхронизацию товаров. Если синхронизация товаров происходит по крону, то дождаться окончания очередной сессии синхронизации товаров</small></p>';
    printf('<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked(1, get_option($option), false), $desc);
  }

  /**
   * setting_images_sync_enabled
   */
  public static function setting_images_sync_enabled()
  {
    $option = 'woomss_gallery_replace_to_sync';
    $desc = '<small>Если включить опцию, то плагин будет загружать изображения галереи из МойСклад.</small>';
    printf('<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked(1, get_option($option), false), $desc);
  }
}

ImagesGallery::init();

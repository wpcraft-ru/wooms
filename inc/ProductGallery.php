<?php

namespace WooMS;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class ProductGallery
{
  use MSImages;

  public static $walker_hook_name = 'gallery_images_download_schedule';

  /**
   * the init
   */
  public static function init()
  {


    // add_action('init', function(){
    //   if(!isset($_GET['dd'])){
    //     return;
    //   }

    //   self::download_images_by_id(12237);


    //   dd(0);
    // });

    add_action('gallery_images_download_schedule', [__CLASS__, 'download_images_from_metafield']);

    add_filter('wooms_product_save', [__CLASS__, 'update_product'], 40, 3);

    add_action('admin_init', [__CLASS__, 'settings_init'], 70);

    add_action('init', [__CLASS__, 'add_schedule_hook']);

    add_action('wooms_main_walker_finish', [__CLASS__, 'restart']);

    add_action('wooms_product_images_info', [__CLASS__, 'render_state_info']);
  }


  /**
   * restart walker after finish main product walker
   */
  public static function restart()
  {
    delete_transient('gallery_images_downloaded');
  }


  /**
   * check disable option
   */
  public static function is_disable()
  {
    if (empty(get_option('woomss_gallery_sync_enabled'))) {
      return true;
    }

    return false;
  }


  /**
   * render_state_info
   */
  public static function render_state_info()
  {
    if (self::is_disable()) {
      return;
    }

    $strings = [];

    if (as_next_scheduled_action(self::$walker_hook_name)) {
      $strings[] = sprintf('<strong>Статус:</strong> %s', 'галлереи продуктов загружаются очередями в фоне');
    } else {
      $strings[] = sprintf('<strong>Статус:</strong> %s', 'в ожидании новых задач');
    }

    $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=gallery_images_download_schedule&orderby=schedule&order=desc'));

    if (defined('WC_LOG_HANDLER') && 'WC_Log_Handler_DB' == WC_LOG_HANDLER) {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs&source=WooMS-ProductGallery'));
    } else {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs'));
    }

?>
    <hr>
    <div>
      <br>
      <strong>Галлереи:</strong>
      <ul>
        <li>
          <?php
          echo implode('</li><li>', $strings);
          ?>
        </li>
      </ul>
    </div>
<?php
  }


  /**
   * update_product
   */
  public static function update_product($product, $data_api, $data)
  {

    if (empty(get_option('woomss_gallery_sync_enabled'))) {
      return $product;
    }

    if (empty($data_api['images']['meta']['size'])) {
      return $product;
    }

    $img_count = $data_api['images']['meta']['size'];
    $href = $data_api['images']['meta']['href'];

    if ($img_count < 2) {
      return $product;
    }

    $product->update_meta_data('wooms_data_for_get_gallery', $href);


    return $product;
  }


  /**
   * Setup schedule
   *
   * @return mixed
   */
  public static function add_schedule_hook()
  {

    if (self::is_disable()) {
      as_unschedule_all_actions(self::$walker_hook_name);
      return;
    }

    if (self::is_wait()) {
      as_unschedule_all_actions(self::$walker_hook_name);
      return;
    }

    if (as_next_scheduled_action(self::$walker_hook_name)) {
      return;
    }

    // Adding schedule hook
    as_schedule_recurring_action(time(), 60, self::$walker_hook_name, [], 'WooMS');
  }


  /**
   * check new task for walker
   */
  public static function is_wait()
  {
    if (get_transient('gallery_images_downloaded')) {
      return true;
    }

    return false;
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

    // If no images left to download
    if (empty($list)) {

      // If sync product already finished

      // Adding the option that all images downloaded and the sync is over
      set_transient('gallery_images_downloaded', time());

      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('All gallery images is downloaded and sync is over ')
      );

      return false;
    }

    foreach ($list as $key => $value) {
      self::download_images_by_id($value->ID);
      delete_post_meta($value->ID, 'wooms_data_for_get_gallery');
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
    $imgages_url = get_post_meta($product_id, 'wooms_data_for_get_gallery', true);

    $img_data_list = $imgages_url;

    $images_data = wooms_request($imgages_url);

    if (empty($images_data['rows'])) {
      return false;
    }

    $media_data_list = [];

    foreach ($images_data['rows'] as $key => $row) {

      $url_download = $row['meta']['downloadHref'];
      $media_id = self::uploadRemoteImageAndAttach($url_download, $product_id, $row['filename']);

      if ($media_id == get_post_thumbnail_id($product_id)) {
        continue;
      }

      if (!empty($media_id)) {
        $media_data_list[] = $media_id;
      }
    }

    if (!empty($media_data_list)) {

      // Set the gallery images
      update_post_meta($product_id, '_product_image_gallery', implode(',', $media_data_list));

      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('Image is attach to the product %s (Image id list [%s], filename: %s)', $product_id, implode(',', $media_data_list), $row['filename'])
      );
    } else {
      do_action(
        'wooms_logger_error',
        __CLASS__,
        sprintf('Error image attachments %s', $product_id)
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

ProductGallery::init();

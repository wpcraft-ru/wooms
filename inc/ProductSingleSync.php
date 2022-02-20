<?php

namespace WooMS;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Single Product Import
 */
class ProductSingleSync
{

  public static $state_key = 'wooms_product_single_sync_state';

  /**
   * The Init
   */
  public static function init()
  {
    add_action('wooms_display_product_metabox', array(__CLASS__, 'display_checkbox'));
    add_action('woocommerce_update_product', array(__CLASS__, 'product_save'), 100);

    add_action('wooms_product_single_update_schedule', array(__CLASS__, 'update_variations'));

    add_action('init', [__CLASS__, 'add_schedule_hook']);
  }


  public static function is_wait()
  {
    if (self::get_state('end_timestamp')) {
      return true;
    }

    return false;
  }


  /**
   * Cron task restart
   */
  public static function add_schedule_hook()
  {
    if (self::is_wait()) {
      return;
    }

    if (as_next_scheduled_action('wooms_product_single_update_schedule')) {
      return;
    }

    $state = self::get_state();

    // Adding schedule hook
    as_schedule_single_action(
      time() + 30,
      'wooms_product_single_update_schedule',
      $state,
      'WooMS'
    );
  }


  /**
   * get_state
   */
  public static function get_state($key = '')
  {
    if (!$state = get_transient(self::$state_key)) {
      $state = [];
    }

    if (empty($key)) {
      return $state;
    }

    if (isset($state[$key])) {
      return $state[$key];
    } else {
      return null;
    }
  }


  /**
   * set_state
   */
  public static function set_state($key = '', $value = '')
  {

    $state = get_transient(self::$state_key);

    if (is_array($state)) {
      $state[$key] = $value;
    } else {
      $state = [
        $key => $value
      ];
    }

    set_transient(self::$state_key, $state);

    return $state;
  }


  /**
   * update_variations
   */
  public static function update_variations($product_id = 0)
  {
    if (empty($product_id)) {
      $product_id = self::get_state('product_id');
    }

    if (empty($product_id)) {
      $product_id = self::get_update_variations_product_id();
    }

    if (empty($product_id)) {
      return false;
    }

    $product  = wc_get_product($product_id);
    $wooms_id = $product->get_meta('wooms_id', true);

    $url_args = array(
      'limit'  => 20,
      'offset' => 0,
    );

    if ($offset = self::get_state('offset')) {
      $url_args['offset'] = $offset;
    }

    $url = 'https://online.moysklad.ru/api/remap/1.2/entity/variant/?filter=productid=' . $wooms_id;
    $url = add_query_arg($url_args, $url);

    do_action(
      'wooms_logger',
      __CLASS__,
      sprintf('API запрос на вариации: %s (продукт ID %s)', $url, $product_id)
    );

    $data_api = wooms_request($url);

    if (empty($data_api['rows'])) {
      //finish
      self::set_state('product_id', 0);
      self::set_state('offset', 0);
      $product->delete_meta_data('wooms_need_update_variations');
      $product->save();

      return true;
    }

    $i = 0;
    foreach ($data_api['rows'] as $item) {
      $i++;

      do_action('wooms_products_variations_item', $item);
    }

    self::set_state('offset', self::get_state('offset') + $i);

    return true;
  }


  /**
   * Find the product that need to be updated
   */
  public static function get_update_variations_product_id()
  {

    $args = [
      'post_type'      => 'product',
      'post_status'      => 'any',
      'posts_per_page' => 1,
      'meta_query'     => [
        [
          'key'     => 'wooms_need_update_variations',
          'compare' => 'EXISTS',
        ],
      ],
    ];

    $posts = get_posts($args);

    if (empty($posts)) {
      self::set_state('product_id', 0);
      self::set_state('end_timestamp', time());
      return false;
    }

    if (isset($posts[0]->ID)) {
      $product_id = $posts[0]->ID;
      self::set_state('product_id', $product_id);

      return $product_id;
    }

    return false;
  }


  /**
   * save
   */
  public static function product_save($product_id)
  {
    if (!isset($_REQUEST['wooms_product_sinle_sync'])) {
      return;
    }


    if (!empty($_REQUEST['wooms_product_sinle_sync'])) {

      remove_action('woocommerce_update_product', array(__CLASS__, 'product_save'), 100);

      self::sync($product_id);
      self::set_state('end_timestamp', 0);

      add_action('woocommerce_update_product', array(__CLASS__, 'product_save'), 100);
    }
  }

  /**
   * sync
   */
  public static function sync($product_id = '')
  {
    if (empty($product_id)) {
      return false;
    }

    $product = wc_get_product($product_id);
    $uuid = $product->get_meta('wooms_id', true);
    if (empty($uuid)) {
      return false;
    }

    $url = 'https://online.moysklad.ru/api/remap/1.2/entity/assortment?filter=id=' . $uuid;

    $data = wooms_request($url);

    if (!isset($data['rows'][0])) {
      return false;
    }

    $row = $data['rows'][0];

    do_action('wooms_product_data_item', $row);

    if (empty($data['variantsCount'])) {
      return false;
    }

    $product->update_meta_data('wooms_need_update_variations', 1);


    $product->save();

    return true;
  }


  /**
   * display_checkbox
   */
  public static function display_checkbox($product_id = '')
  {

    $product = wc_get_product($product_id);
    $need_update_variations = $product->get_meta('wooms_need_update_variations', true);
    echo '<hr/>';
    if (empty($need_update_variations)) {
      printf(
        '<input id="wooms-product-single-sync" type="checkbox" name="wooms_product_sinle_sync"> <label for="wooms-product-single-sync">%s</label>',
        'Синхронизировать отдельно'
      );
    } else {
      printf('<p>%s</p>', 'Вариации ждут очереди на обновление');
    }

    printf(
      '<p><a href="%s">%s</a></p>',
      admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_product_single_update_schedule&orderby=schedule&order=desc'),
      'Открыть очередь задач'
    );
  }
}

ProductSingleSync::init();

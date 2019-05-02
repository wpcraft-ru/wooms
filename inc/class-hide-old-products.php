<?php

namespace WooMS\Products;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Hide old products
 */
class Hiding {

  /**
   * The init
   */
  public static function init()
  {
    add_action( 'init', array( __CLASS__, 'cron_init' ) );
    add_action( 'wooms_cron_clear_old_products_walker', array( __CLASS__, 'walker_starter' ) );

    add_action('wooms_products_state_before', array(__CLASS__, 'display_state'));
    add_action('wooms_main_walker_finish', array(__CLASS__, 'finis_main_walker'));

    add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );
  }

  /**
   * Cron task restart
   */
  public static function cron_init() {
    if ( ! wp_next_scheduled( 'wooms_cron_clear_old_products_walker' ) ) {
      wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_clear_old_products_walker' );
    }
  }

  /**
   * Starter walker by cron if option enabled
   */
  public static function walker_starter()
  {
    if(get_transient('wooms_product_hiding_disable')){
      return;
    }

    //Если работает синк товаров, то блокируем работу
    if( ! empty(get_transient('wooms_start_timestamp'))){
      return;
    }

    if( get_transient('wooms_products_old_hide_pause') ){
      return;
    }

    self::set_hidden_old_product();
  }

  /**
   * Убираем паузу для сокрытия продуктов
   */
  public static function finis_main_walker()
  {
    delete_transient('wooms_products_old_hide_pause');
  }

  /**
   * display_state
   */
  public static function display_state(){

    if( $timestamp = get_transient('wooms_products_old_hide_pause')){
      $msg = sprintf('<p>Скрытие устаревших продуктов: успешно завершено в последний раз %s</p>', $timestamp);
    } else {
      $msg = sprintf('<p>Скрытие устаревших продуктов: <strong>%s</strong></p>', 'выполняется');
    }

    echo $msg;
  }

  /**
   * Adding hiding attributes to products
   */
  public static function set_hidden_old_product()
  {
    do_action('wooms_logger', __CLASS__,
      sprintf('Проверка очереди скрытия продуктов: %s', date( "Y-m-d H:i:s" ))
    );

    $products = self::get_products_old_session();

    if( empty($products) ){
      delete_transient( 'wooms_offset_hide_product' );
      set_transient('wooms_products_old_hide_pause', date( "Y-m-d H:i:s" ), HOUR_IN_SECONDS);
      do_action('wooms_recount_terms');
      do_action('wooms_logger', __CLASS__,
        sprintf('Финишь скрытия продуктов: %s', date( "Y-m-d H:i:s" ))
      );
      return;
    }

    foreach ( $products as $product_id ) {
      $product = wc_get_product( $product_id );

      if ( $product->get_type() == 'variable' ) {
        $product->set_manage_stock( 'yes' );
      }

      // $product->set_status( 'draft' );
      $product->set_catalog_visibility( 'hidden' );
      // $product->set_stock_status( 'outofstock' );
      $product->save();

      do_action('wooms_logger', __CLASS__,
        sprintf('Скрытие продукта: %s', $product_id)
      );

    }

    do_action('wooms_hide_old_product', $products);

  }

  /**
   * Obtaining products with specific attributes
   *
   * @param int $offset
   *
   * @return array
   */
  public static function get_products_old_session()
  {
    $session = self::get_session();
    if(empty($session)){
      return false;
    }

    $args = array(
      'post_type'   => 'product',
      'numberposts' => 30,
      'fields'      => 'ids',
      'tax_query'   => array(
        array(
            'taxonomy'  => 'product_visibility',
            'terms'     => array('exclude-from-catalog', 'exclude-from-search'),
            'field'     => 'name',
            'operator'  => 'NOT IN',
        ),
      ),
      'meta_query'  => array(
        array(
          'key'     => 'wooms_session_id',
          'value'   => $session,
          'compare' => '!=',
        ),
        array(
          'key'     => 'wooms_id',
          'compare' => 'EXISTS',
        ),
      ),

    );

    return get_posts( $args );
  }

  /**
   * Method for getting the value of an option
   *
   * @return bool|mixed
   */
  public static function get_session() {
    $session_id = get_option( 'wooms_session_id' );
    if ( empty( $session_id ) ) {
      return false;
    }

    return $session_id;
  }

  /**
   * settings_init
   */
  public static function settings_init(){
    register_setting( 'mss-settings', 'wooms_product_hiding_disable' );
    add_settings_field(
      $id = 'wooms_product_hiding_disable',
      $title = 'Отключить скрытие продуктов',
      $callback = array(__CLASS__, 'display_field_wooms_product_hiding_disable' ),
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );
  }

  /**
   * display_field_wooms_product_hiding_disable
   */
  public static function display_field_wooms_product_hiding_disable(){
    $option = 'wooms_product_hiding_disable';
    printf( '<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option( $option ), false ) );
    printf(
      '<p><small>%s</small></p>',
      'Если включить опцию, то обработчик скрытия продуктов из каталога будет отключен. Иногда это бывает полезно.'
    );
  }

}

Hiding::init();

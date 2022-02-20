<?php

namespace WooMS;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Hide old variations
 */
class VariationsHider {

  /**
   * The init
   */
  public static function init() {
    add_action( 'wooms_cron_variations_hiding', array( __CLASS__, 'walker' ) );

    add_action('wooms_wakler_variations_finish', array(__CLASS__, 'reset_after_finish_parent_walker'));

    add_action( 'init', array( __CLASS__, 'add_schedule_hook' ) );

  }

  /**
   * Add schedule hook
   */
  public static function add_schedule_hook()
  {
    if ( empty( get_option( 'woomss_variations_sync_enabled' ) ) ) {
      return;
    }

    //Если стоит пауза, то ничего не делаем
    if( ! empty(get_transient('wooms_variations_hiding_pause'))){
      return;
    }

    //Если работает синк товаров, то блокируем работу
    if( ! empty(get_transient('wooms_start_timestamp'))){
      return;
    }

    //Если работает синк вариаций, то блокируем работу
    if( ! empty(get_transient('wooms_variant_start_timestamp'))){
      return;
    }

    if (!as_next_scheduled_action('wooms_cron_variations_hiding')) {
      // Adding schedule hook
      as_schedule_single_action(
        time() + 60,
        'wooms_cron_variations_hiding',
        [],
        'WooMS'
      );
    }
    
  }

  
  /**
   * reset_after_finish_parent_walker
   */
  public static function reset_after_finish_parent_walker(){
    delete_transient('wooms_variations_hiding_pause');
  }

  /**
   * Walker
   */
  public static function walker() {

    if ( empty( get_option( 'woomss_variations_sync_enabled' ) ) ) {
      return;
    }

    //Если стоит пауза, то ничего не делаем
    if( ! empty(get_transient('wooms_variations_hiding_pause'))){
      return;
    }

    //Если работает синк товаров, то блокируем работу
    if( ! empty(get_transient('wooms_start_timestamp'))){
      return;
    }

    //Если работает синк вариаций, то блокируем работу
    if( ! empty(get_transient('wooms_variant_start_timestamp'))){
      return;
    }

    if ( ! $offset = get_transient( 'wooms_offset_hide_variations' ) ) {
      $offset = 0;
      set_transient( 'wooms_offset_hide_variations', $offset );
      do_action('wooms_logger', __CLASS__, sprintf('Старт скрытия вариаций: %s', date("Y-m-d H:i:s")) );

    }

    $args = array(
      'post_type'   => 'product_variation',
//      'post_parent' => $product_parent,
      'numberposts' => 20,
      'fields'      => 'ids',
      'offset'      => $offset,
      'meta_query'  => array(
        array(
          'key'     => 'wooms_session_id',
          'value'   => self::get_session(),
          'compare' => '!=',
        ),
        array(
          'key'     => 'wooms_id',
          'compare' => 'EXISTS',
        ),
      ),
    );

    $variations = get_posts( $args );

    $i = 0;

    foreach ( $variations as $variations_id ) {
      $variation = wc_get_product( $variations_id );
      $variation->set_stock_status( 'outofstock' );
      $variation->save();
      $i ++;
      do_action('wooms_logger', __CLASS__, sprintf('Скрытие вариации: %s', $variations_id) );

    }

    set_transient( 'wooms_offset_hide_variations', $offset + $i );

    if ( empty( $product_parent ) ) {
      delete_transient( 'wooms_offset_hide_variations' );
      set_transient('wooms_variations_hiding_pause', 1, HOUR_IN_SECONDS);

      do_action('wooms_logger', __CLASS__, sprintf('Скрытие вариаций завершено: %s', date("Y-m-d H:i:s")) );

    }
  }

  /**
   * Method for getting the value of an option
   */
  public static function get_session() {
    $session_id = get_option( 'wooms_session_id' );
    if ( empty( $session_id ) ) {
      return false;
    }

    return $session_id;
  }
}

VariationsHider::init();

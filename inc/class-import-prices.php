<?php
namespace WooMS\Products;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Select specific price is setup
 */
class Prices {

  /**
   * The init
   */
    public static function init() {

      /**
      * Обновление данных о ценах
      */
      add_filter( 'wooms_product_price', array( __CLASS__, 'chg_price' ), 10, 3 );
      add_action( 'admin_init', array( __CLASS__, 'settings_init' ), $priority = 101, $accepted_args = 1 );


    }

    /**
     * Update prices for product
     * Check specifiec price and replace if isset price
     */
    public static function chg_price( $price, $data, $product_id ) {

        $price_name = get_option( 'wooms_price_id' );
        if ( empty( $price_name ) ) {
            return $price;
        } else {

          $price_value = self::get_value_for_price_type( $data );

          do_action('wooms_logger', __CLASS__,
            sprintf('Выбрана цена "%s" = %s. Для продукта ИД: %s', $price_name, $price_value, $product_id)
          );

          if ( empty( $price_value ) ) {
              return $price;
          } else {
              return $price_value;
          }
        }
    }

    /**
     * Get specific price
     * Return 0 or value price
     */
    public static function get_value_for_price_type( $data ) {

        $price_name = get_option( 'wooms_price_id' );
        if ( empty( $price_name ) ) {
            return 0;
        }

        $price_value = 0;
        foreach ( $data["salePrices"] as $price ) {
            if ( $price["priceType"] == $price_name ) {
                $price_value = $price["value"];
            }
        }
        if ( empty( $price_value ) ) {
            return 0;
        } else {
            return $price_value;
        }
    }

    /**
     * Add settings
     */
    public static function settings_init() {
      register_setting( 'mss-settings', 'wooms_price_id' );
      add_settings_field(
        $id = 'wooms_price_id',
        $title = 'Тип Цены',
        $callback = array(__CLASS__, 'display_field_wooms_price_id' ),
        $page = 'mss-settings',
        $section = 'woomss_section_other'
      );
    }

  /**
   * display_field_wooms_price_id
   */
    public static function display_field_wooms_price_id() {
        $id = 'wooms_price_id';
        printf( '<input type="text" name="%s" value="%s" />', $id, sanitize_text_field( get_option( $id ) ) );
        echo '<p><small>Укажите наименование цены, если нужно выбрать специальный тип цен. Система будет проверять такой тип цены и если он указан то будет подставлять его вместо базового.</small></p>';
    }
}

Prices::init();

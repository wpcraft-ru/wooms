<?php

namespace WooMS;

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Select specific value for sale price
 */
class SalePrices
{
  /**
   * The init
   */
  public static function init()
  {
    add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 30, 2);
    add_filter('wooms_variation_save', array(__CLASS__, 'update_product'), 30, 2);

    add_action('admin_init', array(__CLASS__, 'settings_init'), $priority = 101, $accepted_args = 1);
  }

  /**
   * Update product
   */
  public static function update_product($product, $value)
  {

    $product_id = $product->get_id();

    $price_name = esc_html(get_option('wooms_price_sale_name'));


    if (empty($price_name)) {
      $product->set_sale_price('');
      return $product;
    }

    if (empty($value['salePrices'])) {
      $product->set_sale_price('');
      do_action(
        'wooms_logger_error',
        __CLASS__,
        sprintf('Нет цен для продукта %s', $product_id)
      );

      return $product;
    }

    $sale_price = 0;
    $price_meta = [];
    foreach ($value['salePrices'] as $price) {

      if ($price['priceType']["name"] == $price_name && floatval($price['value']) > 0) {
        $sale_price = floatval($price['value'] / 100);
        $price_meta = $price;
      }
    }

    $sale_price = apply_filters('wooms_sale_price', $sale_price, $value, $product_id, $price_meta);

    if ($sale_price) {
      $sale_price = round($sale_price, 2);
      $sale_price = (string) $sale_price;
      $product->set_sale_price($sale_price);

      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf(
          'Цена распродажи %s сохранена для продукта %s (%s)',
          $sale_price,
          $product->get_name(),
          $product_id
        )
      );
    } else {
      $product->set_sale_price('');
    }

    return $product;
  }


  /**
   * Add settings
   */
  public static function settings_init()
  {
    register_setting('mss-settings', 'wooms_price_sale_name');
    add_settings_field(
      $id = 'wooms_price_sale_name',
      $title = 'Тип Цены Распродажи',
      $callback = function ($args) {
        printf('<input type="text" name="%s" value="%s" />', $args['key'], $args['value']);
        echo '<p><small>Укажите наименование цены для Распродаж. Система будет проверять такой тип цены и если он указан то будет сохранять его в карточке Продукта.</small></p>';
        echo '<p><small>Если оставить поле пустым, то цена Распродажи у всех продуктов будут удалены после очередной синхронизации.</small></p>';
      },
      $page = 'mss-settings',
      $section = 'woomss_section_other',
      $args = [
        'key' => 'wooms_price_sale_name',
        'value' => sanitize_text_field(get_option('wooms_price_sale_name')),
      ]
    );
  }
}

SalePrices::init();

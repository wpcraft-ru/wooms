<?php

namespace WooMS\ProductsPrices;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

add_filter('wooms_product_save', __NAMESPACE__  . '\\product_chg_price', 10, 2);
add_filter('wooms_variation_save', __NAMESPACE__  . '\\product_chg_price', 10, 2);
add_action('admin_init', __NAMESPACE__  . '\\add_settings', 101);

function product_chg_price($product, $data_api)
{
  $product_id = $product->get_id();

  $price = 0;
  $price_meta = [];

  if ($price_name = get_option('wooms_price_id')) {
    foreach ($data_api["salePrices"] as $price_item) {
      if ($price_item["priceType"]['name'] == $price_name) {
        $price = $price_item["value"];
        $price_meta = $price_item;
      }
    }
  }

  if (empty($price)) {
    $price = floatval($data_api['salePrices'][0]['value']);
    $price_meta = $data_api['salePrices'][0];
  }

  $price = apply_filters('wooms_product_price', $price, $data_api, $product_id, $price_meta);

  $price = floatval($price) / 100;
  $price = round($price, 2);
  // $product->set_price($price);
  $product->set_regular_price($price);

  return $product;
}

function add_settings()
{
  $key = 'wooms_price_id';
  register_setting('mss-settings', $key);
  add_settings_field(
    $id = 'wooms_price_id',
    $title = 'Тип Цены',
    $callback = function($args) {
      printf('<input type="text" name="%s" value="%s" />', $args['key'], $args['value']);
      echo '<p><small>Укажите наименование цены, если нужно выбрать специальный тип цен. Система будет проверять такой тип цены и если он указан то будет подставлять его вместо базового.</small></p>';
    },
    $page = 'mss-settings',
    $section = 'woomss_section_other',
    $args = [
      'key' => $key,
      'value' => get_option($key),
    ]
  );
}

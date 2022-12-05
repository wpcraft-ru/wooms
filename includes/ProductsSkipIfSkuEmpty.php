<?php
/**
 * Skip product sync if no SKU
 *
 * @issue https://github.com/wpcraft-ru/wooms/issues/461
 */

namespace WooMS\ProductsSkipIfSkuEmpty;

defined('ABSPATH') || exit;

const CONFIG_KEY = 'product_skip_if_no_sku';

add_action('admin_init', __NAMESPACE__ . '\\add_settings', 30);

add_filter('wooms_skip_product_import', __NAMESPACE__ . '\\process', 20, 2);

function process($is_skip, $item)
{
  $is_active = get_option('wooms_config')[CONFIG_KEY] ?? false;
  if (empty($is_active)) {
    return $is_skip;
  }

  if (!empty($item['article'])) {
    return $is_skip;
  }

  return true;
}

function add_settings()
{
  add_settings_field(
    $id = CONFIG_KEY,
    $title = 'Пропускать продукты без артикула',
    $callback = function ($args) {
      printf('<input type="checkbox" name="%s" value="1" size="50" id="%s" %s />', $args['key'], $args['label_for'], checked(1, $args['value'], false));
      printf('<p>%s</p>', 'Если опция активна, то плагин будет пропускать синхронизацию продуктов без артикула: https://github.com/wpcraft-ru/wooms/issues/461');
    },
    $page = 'mss-settings',
    $section = 'woomss_section_other',
    $args = [
      'key' => sprintf("wooms_config[%s]", CONFIG_KEY),
      'value' => get_option('wooms_config')[CONFIG_KEY] ?? false,
      'label_for' => CONFIG_KEY,
    ]
  );
}

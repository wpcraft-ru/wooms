<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * UseCodeAsArticle
 *
 * @todo may be it have to delete
 *
 * issue https://github.com/wpcraft-ru/wooms/issues/98
 */
class UseCodeAsArticle
{
    public static function init()
    {
        add_filter('wooms_get_product_id', [__CLASS__, 'get_product_id_by_code'], 40, 2);
        add_filter('wooms_product_save', [__CLASS__, 'product_save'], 40, 2);
        add_action('admin_init', [__CLASS__, 'add_settings'], 40);
    }

    /**
     * @param \WC_Product $product
     * @param array $data_api
     */
    public static function product_save($product, $data_api){

      if( self::is_disable() ){
        return $product;
      }

      $product->set_sku($data_api['code']);
      return $product;
    }

    public static function get_product_id_by_code($product_id, $data_api)
    {
        if ( self::is_disable() ) {
            return $product_id;
        }

        if ($product_id_by_code = wc_get_product_id_by_sku($data_api['code'])) {
            return $product_id_by_code;
        }

        return $product_id;
    }

    public static function is_disable()
    {
        if (get_option('wooms_use_code_as_article_enable')) {
            return false;
        }

        return true;
    }

    /**
     * Setting
     */
    public static function add_settings()
    {
        $option_key = 'wooms_use_code_as_article_enable';

        register_setting('mss-settings', $option_key);
        add_settings_field(
            $id = $option_key,
            $title = 'Использовать код как артикул',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    $args['key'],
                    checked(1, $args['value'], $echo = false)
                );
                printf('<p>%s</p>', 'Если включена опция, то плагин будет пытаться связывать товары по коду из МойСклад и артикулу из Сайта');
                printf('<p>%s</p>', 'Подробнее: <a href="https://github.com/wpcraft-ru/wooms/issues/98" target="_blank">https://github.com/wpcraft-ru/wooms/issues/98</a>');
            },
            $page = 'mss-settings',
            $section = 'woomss_section_other',
            $args = [
                'key' => $option_key,
                'value' => get_option($option_key),
            ]
        );
    }
}

UseCodeAsArticle::init();

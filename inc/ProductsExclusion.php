<?php
/**
 * Исключение отмеченных в МойСклад товаров из синхронизации с сайтом
 *
 * @package WooMS XT (Extra)
 */

namespace WooMS\ProductsExclusion;

defined('ABSPATH') || exit;

const OPTION_KEY = 'wooms_product_exclude_flag';

add_action('plugins_loaded', function(){
    add_action( 'admin_init', __NAMESPACE__ . '\\add_settings', 30 );

    add_filter( 'wooms_url_get_products_filters',  __NAMESPACE__ . '\\explude_products_from_walker', 20, 1 );

});

function explude_products_from_walker( $filters ) {

    $flag = get_option( OPTION_KEY );
    $attr_url = 'https://online.moysklad.ru/api/remap/1.2/entity/product/metadata/attributes/';

    if ( $flag ) {
        $filters[] = $attr_url . trim( $flag ) . '=false';
    }

    return $filters;
}


function add_settings() 
{
    register_setting( 'mss-settings', OPTION_KEY );

    add_settings_field(
        $id       = OPTION_KEY,
        $title    = 'Не загружать товары с отметкой',
        $callback = function ($args) {
            printf('<input type="text" name="%s" value="%s" size="50" />', $args['key'], $args['value']);
            printf('<p>%s</p>', 'Укажите название поля по которому поймем что этот продукт загружать на сайт не нужно. Тип поля в МойСклад должен быть - Флажок');
        },
        $page    = 'mss-settings',
        $section = 'woomss_section_other',
        $args = [
            'key'   => OPTION_KEY,
            'value' => get_option( OPTION_KEY ),
        ]
    );
}

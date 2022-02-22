<?php
/**
 * Исключение отмеченных в МойСклад товаров из синхронизации с сайтом
 *
 * @package WooMS XT (Extra)
 */

namespace WooMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Exclusion of some products (exceptions)
 */
class ProductsExclusion {

    /**
     * Option to save ID for a flag (attribute) to filter products
     * 
     * @var string
     */
    public static $exclude_flag = 'wooms_product_exclude_flag';

    /**
     * The init
     */
    public static function init() {

        add_action( 'admin_init', array( __CLASS__, 'add_settings' ), 30 );

        add_filter( 'wooms_url_get_products_filters',  array( __CLASS__, 'explude_products_from_walker' ), 20, 1 );
    }

    /**
     * Settings UI
     */
    public static function add_settings() {

        self::add_setting_exclude_flag_id();
    }

    /**
     * Display field: select warehouse
     */
    public static function add_setting_exclude_flag_id() {

        $option = self::$exclude_flag;
        register_setting( 'mss-settings', $option );

        add_settings_field(
            $id       = $option,
            $title    = 'Не загружать товары с отметкой',
            $callback = function ($args) {
?>

                <input type="text", name="<?= self::$exclude_flag ?>" size="50">
                <p>Укажите название поля по которому поймем что этот продукт загружать на сайт не нужно. Тип поля в МойСклад должен быть - Флажок</p>
                <?php
            },
            $page    = 'mss-settings',
            $section = 'woomss_section_other',
            $args = [
                'key'   => $option,
                'value' => get_option( $option ),
            ]
        );
    }

    /* Filter to Products Walker */
    public static function explude_products_from_walker( $filters ) {

        $flag = get_option( self::$exclude_flag );
        $attr_url = 'https://online.moysklad.ru/api/remap/1.2/entity/product/metadata/attributes/';

        if ( $flag ) {
            $filters[] = $attr_url . trim( $flag ) . '=false';
        }

        return $filters;
    }

}

ProductsExclusion::init();

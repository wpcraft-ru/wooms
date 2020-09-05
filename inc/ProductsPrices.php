<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Select specific price is setup
 */
class ProductsPrices
{

    /**
     * The init
     */
    public static function init()
    {
        /**
         * Обновление данных о ценах
         */
        // add_filter('wooms_product_price', array(__CLASS__, 'chg_price'), 10, 3);
        add_filter('wooms_product_save', array(__CLASS__, 'product_chg_price'), 10, 2);
        add_filter('wooms_variation_save', array(__CLASS__, 'product_chg_price'), 10, 2);
        add_action('admin_init', array(__CLASS__, 'settings_init'), $priority = 101, $accepted_args = 1);
    }


    public static function product_chg_price($product, $data_api)
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
   
        if(empty($price)){
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


    /**
     * Update prices for product
     * Check specifiec price and replace if isset price
     */
    public static function chg_price($price, $data, $product_id)
    {
        $price_name = get_option('wooms_price_id');

        if (empty($price_name)) {
            return $price;
        }

        $price_value = 0;
        $price_meta = [];

        foreach ($data["salePrices"] as $price_item) {
            if ($price_item["priceType"]['name'] == $price_name) {
                $price_value = $price_item["value"];
                $price_meta = $price_item;
            }
        }

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Выбрана цена "%s" = %s. Для продукта ИД: %s', $price_name, $price_value, $product_id)
        );

        if ($price_value == 0) {
            return $price;
        }


        return $price_value;
    }


    /**
     * Add settings
     */
    public static function settings_init()
    {
        register_setting('mss-settings', 'wooms_price_id');
        add_settings_field(
            $id = 'wooms_price_id',
            $title = 'Тип Цены',
            $callback = array(__CLASS__, 'display_field_wooms_price_id'),
            $page = 'mss-settings',
            $section = 'woomss_section_other'
        );
    }

    /**
     * display_field_wooms_price_id
     */
    public static function display_field_wooms_price_id()
    {
        $id = 'wooms_price_id';
        printf('<input type="text" name="%s" value="%s" />', $id, sanitize_text_field(get_option($id)));
        echo '<p><small>Укажите наименование цены, если нужно выбрать специальный тип цен. Система будет проверять такой тип цены и если он указан то будет подставлять его вместо базового.</small></p>';
    }
}


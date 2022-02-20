<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * Additional notes for send order
 */
class CurrencyConverter
{
    public static function init()
    {
        add_filter('wooms_product_price', [__CLASS__, 'chg_price'], 33, 4);
        add_filter('wooms_sale_price', [__CLASS__, 'chg_price'], 32, 4);

        add_action('admin_init', array(__CLASS__, 'add_settings'), 50);
    }

    public static function chg_price($price, $data_api, $product_id, $price_meta)
    {
        if (!self::is_enable()) {
            return $price;
        }

        if(empty($price)){
            return $price;
        }

        if (!$currency = get_transient('wooms_currency_api')) {
            $url = 'https://online.moysklad.ru/api/remap/1.2/entity/currency/';
            $currency = wooms_request($url);
            set_transient('wooms_currency_api', $currency, HOUR_IN_SECONDS);
        }

        $woocommerce_currency = get_woocommerce_currency();
        $api_currency = self::get_currency_code_price_meta($price_meta);

        if(empty($api_currency)){
            return $price;
        }

        if ($woocommerce_currency == $api_currency) {
            return $price;
        }

        $price_by_rate = self::update_price_by_rate($price, $api_currency);

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Цена сконвертирована (продукт id: %s, название: %s, )', $product_id, get_the_title( $product_id )),
            [
                'цена исходная' => $price,
                'цена после конвертации' => $price_by_rate,
                'валюта сайта' => $woocommerce_currency,
                'валюта api' => $api_currency,
            ]
        );
        // if ($product_id == 26226) {
            // dd($api_currency, $woocommerce_currency, $price, $currency, $price_meta);
        // }

        return $price_by_rate;
    }


    public static function update_price_by_rate($price = 0, $api_currency = 'RUB'){
        if (!$currency = get_transient('wooms_currency_api')) {
            $url = 'https://online.moysklad.ru/api/remap/1.2/entity/currency/';
            $currency = wooms_request($url);
            set_transient('wooms_currency_api', $currency, HOUR_IN_SECONDS);
        }

        $rate = 1;

        foreach($currency['rows'] as $currency_row){
            if($currency_row['isoCode'] == $api_currency){
                $rate = $currency_row['rate'];
            }
        }

        $price = $price * $rate;

        return $price;
    }


    public static function get_currency_code_price_meta($price_meta = [])
    {
        if (empty($price_meta['currency']['meta']['href'])) {
            return false;
        }

        $price_currency_href = $price_meta['currency']['meta']['href'];

        if (!$currency = get_transient('wooms_currency_api')) {
            $url = 'https://online.moysklad.ru/api/remap/1.2/entity/currency/';
            $currency = wooms_request($url);
            set_transient('wooms_currency_api', $currency, HOUR_IN_SECONDS);
        }

        if (empty($currency['rows'])) {
            return false;
        }

        $currency_code = 'RUB';
        foreach ($currency['rows'] as $currency_item) {
            if ($price_currency_href == $currency_item['meta']['href']) {
                $currency_code = $currency_item['isoCode'];
                break;
            }
        }

        return $currency_code;
    }


    public static function is_enable()
    {
        if (get_option('wooms_currency_converter_enable')) {
            return true;
        }

        return false;
    }

    /**
     * Setting
     */
    public static function add_settings()
    {
        $option_key = 'wooms_currency_converter_enable';

        register_setting('mss-settings', $option_key);
        add_settings_field(
            $id = $option_key,
            $title = 'Автоконвертация валюты',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    $args['key'],
                    checked(1, $args['value'], $echo = false)
                );
                printf('<p>%s</p>', 'Если включена опция, то плагин будет конвертировать валюту из МойСклад в валюту указанную в настройках WooCommerce');
                printf('<p>%s</p>', 'Подробнее: <a href="https://github.com/wpcraft-ru/wooms/issues/277" target="_blank">https://github.com/wpcraft-ru/wooms/issues/277</a>');
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

CurrencyConverter::init();

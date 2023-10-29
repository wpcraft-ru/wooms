<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * Additional notes for send order
 *
 * @todo need refactoring after https://github.com/wpcraft-ru/wooms/issues/516
 */
class CurrencyConverter
{

	const OPTION_KEY = 'wooms_currency_converter_enable';

    public static function init()
    {
        add_filter('wooms_product_price', [__CLASS__, 'chg_price'], 33, 4);
        add_filter('wooms_sale_price', [__CLASS__, 'chg_price'], 32, 4);

        add_action('wooms_main_walker_started', [__CLASS__, 'cache_data']);

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

		$currency_ms = self::get_currency();

		$price_by_rate = self::get_price_converted($price_meta, $currency_ms);

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Цена сконвертирована (продукт id: %s, название: %s, )', $product_id, get_the_title( $product_id )),
            [
                'цена исходная' => $price,
                'цена после конвертации' => $price_by_rate,
            ]
        );

        return $price_by_rate;
    }

    public static function get_price_converted($price_meta, $currency_ms){

		$woocommerce_currency = get_woocommerce_currency();

		$rate = 1;

        foreach($currency_ms['rows'] as $currency_row){
            if($currency_row['meta']['href'] == $price_meta['currency']['meta']['href']){
                $rate = $currency_row['rate'];

            }
        }

        $price = $price_meta['value'] * $rate;
		return $price;

	}

    public static function get_currency(){
		$currency = get_transient('wooms_currency_api');
		if($currency){
			return $currency;
		}

		$currency = request('entity/currency/');
		set_transient('wooms_currency_api', $currency, DAY_IN_SECONDS);
		return $currency;
	}

    public static function cache_data(){
		delete_transient('wooms_currency_api');
		$currency = request('entity/currency/');
		set_transient('wooms_currency_api', $currency, DAY_IN_SECONDS);
	}


    public static function update_price_by_rate($price = 0, $api_currency = 'RUB'){

		$currency = self::get_currency();

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

        $currency = self::get_currency();

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
        if (get_option(self::OPTION_KEY)) {
            return true;
        }

        return false;
    }

    /**
     * Setting
     */
    public static function add_settings()
    {
        $option_key = self::OPTION_KEY;

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

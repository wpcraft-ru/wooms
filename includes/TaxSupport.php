<?php

namespace WooMS;

defined('ABSPATH') || exit;

final class TaxSupport
{
    public static function init()
    {

        // add_filter('wooms_order_data', [__CLASS__, 'add_order_tax'], 11, 2);
        add_filter('wooms_order_sender_position', [__CLASS__, 'chg_order_sender_position'], 11, 2);

        add_filter('wooms_product_update', array(__CLASS__, 'update_product'), 50, 2);

        add_action('admin_init', array(__CLASS__, 'add_settings'), 40);
    }

    /**
     * chg_order_sender_position
     *
     * use hook $position = apply_filters('wooms_order_sender_position', $position, $product_id);
     */
    public static function chg_order_sender_position($position, $product_id)
    {
        if (!self::is_enable()) {
            return $position;
        }

        $product = wc_get_product($product_id);

        $tax_class = $product->get_tax_class();

        switch ($tax_class) {
            case 20:
                $position['vat'] = 20;
                break;
            case 18:
                $position['vat'] = 18;
                break;
            case 10:
                $position['vat'] = 10;
                break;
            default:
                $position['vat'] = 0;
                break;
        }

        // if($position['vat'] != 0){
        //     // dd($product, $position);
        // }

        return $position;
    }


    public static function update_product($product, $data_api)
    {
        if (!self::is_enable()) {
            return $product;
        }

        // $product = wc_get_product($product);

        if (!isset($data_api['effectiveVat'])) {
            return $product;
        }

        // dd(\WC_Tax::get_tax_class_slugs());
        // dd($product->get_tax_class());

        switch ($data_api['effectiveVat']) {
            case 20:
                $product->set_tax_class('20%');
                break;
            case 18:
                $product->set_tax_class('18%');
                break;
            case 10:
                $product->set_tax_class('10%');
                break;
            default:
                $product->set_tax_class('');
                break;
        }

        return $product;
    }


    /**
     * XXX - maybe it will be needed in the future
     */
    public static function add_order_tax($data_order, $order_id)
    {

        if (!self::is_enable()) {
            return $data_order;
        }

        return $data_order;
    }

    public static function is_enable()
    {
        if (get_option('wooms_tax_support')) {
            return true;
        }

        return false;
    }

    public static function add_settings()
    {
        $section_key = 'wooms_section_orders';
        $option_key = 'wooms_tax_support';
        register_setting('mss-settings', $option_key);
        add_settings_field(
            $id = $option_key . '_input',
            $title = 'Включить работу с налогами',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    $args['key'],
                    checked(1, $args['value'], $echo = false)
                );

                printf('<p>%s</p>', 'Эксперементальная опция. Детали и обсуждение тут: <a href="https://github.com/wpcraft-ru/wooms/issues/173" target="_blank">https://github.com/wpcraft-ru/wooms/issues/173</a>');
            },
            $page = 'mss-settings',
            $section_key,
            $args = [
                'key' => $option_key,
                'value' => get_option($option_key),
            ]
        );
    }
}

TaxSupport::init();

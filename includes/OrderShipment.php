<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * Adds an option to select an item in the order
 * as a shipment on the MoySklad side
 */
class OrderShipment
{
    public static function init()
    {
        add_filter('wooms_order_data', array(__CLASS__, 'chg_order_data'), 30, 2);

        add_filter('wooms_skip_service', array(__CLASS__, 'skip_service_if_shipment'), 10, 2);

        add_action('admin_init', array(__CLASS__, 'add_settings'), 50);
    }

    /**
     * skip import service if the service is shipment
     * 
     * issue https://github.com/wpcraft-ru/wooms/issues/314
     */
    public static function skip_service_if_shipment($skip_boolean, $row_api_data)
    {
        if (!$order_shipment_item_code = get_option('wooms_order_shipment_item_code')) {
            return $skip_boolean;
        }

        if ($order_shipment_item_code == $row_api_data['code']) {
            $skip_boolean = true;
        }

        return $skip_boolean;
    }

    /**
     * chg_order_data
     * 
     * fix https://github.com/wpcraft-ru/wooms/issues/186
     */
    public static function chg_order_data($data, $order_id)
    {
        if (!$order_shipment_item_code = get_option('wooms_order_shipment_item_code')) {
            return $data;
        }

        if(empty($data['positions'])){
            return $data;
        }

        if (!$meta = self::get_meta_for_shipment_item($order_shipment_item_code)) {
            return $data;
        }

        $order = wc_get_order($order_id);

        $data['positions'][] = array(
            'quantity'   => 1,
            'price'      => $order->get_shipping_total() * 100,
            'assortment' => array(
                'meta' => $meta,
            ),
            'reserve'    => 0,
        );

        return $data;
    }

    /**
     * get meta for shipment item
     *
     * @param $order_shipment_item_code
     */
    public static function get_meta_for_shipment_item($order_shipment_item_code)
    {
        $url = 'https://online.moysklad.ru/api/remap/1.2/entity/service';
        $url = add_query_arg('filter=code', $order_shipment_item_code, $url);
        $data = wooms_request($url);

        if (empty($data['rows'][0]['meta'])) {
            return false;
        }

        $meta = $data['rows'][0]['meta'];
        return $meta;
    }

    /**
     * Settings UI
     */
    public static function add_settings()
    {

        $order_shipment_item_key = 'wooms_order_shipment_item_code';
        register_setting('mss-settings', $order_shipment_item_key);
        add_settings_field(
            $id = $order_shipment_item_key,
            $title = 'Код позиции для передачи стоимости доставки',
            $callback = function ($args) {
                printf('<input type="text" name="%s" value="%s" />', $args['key'], $args['value']);
                printf(
                    '<p><small>%s</small></p>',
                    'Если нужно передавать стоимость доставки, укажите тут код соответствующей услуги из МойСклад (поле Код в карточке Услуги)'
                );
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $order_shipment_item_key,
                'value' => get_option($order_shipment_item_key)
            ]
        );
    }
}

OrderShipment::init();

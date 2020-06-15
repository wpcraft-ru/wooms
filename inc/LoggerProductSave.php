<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * LoggerProductSave
 * 
 * issue https://github.com/wpcraft-ru/wooms/issues/310
 */
class LoggerProductSave
{
    /**
     * The Init
     */
    public static function init()
    {
        add_action('woocommerce_update_product', array(__CLASS__, 'product_save'));
    }

    public static function product_save($product_id)
    {
        $product = wc_get_product($product_id);

        $data = [
            'status' => $product->get_status(),
            'type' => $product->get_type(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
        ];

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Продукт сохранен: %s (ID %s)', $product->get_name(), $product_id),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

LoggerProductSave::init();

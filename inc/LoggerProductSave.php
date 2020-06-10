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
        add_action('woocommerce_update_product', array(__CLASS__, 'product_save'), 20, 2);
    }

    public static function product_save($product_id, $product)
    {
        $product = wc_get_product($product);

        $data = [
            'status' => $product->get_status(),
            'type' => $product->get_type(),
            'catalog_visibility' => $product->get_catalog_visibility(),
        ];

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Продукт сохранен: %s (продукт ID %s)', $product->get_name(), $product_id),
            $data
        );
    }
}

LoggerProductSave::init();

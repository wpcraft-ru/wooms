<?php 

namespace WooMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Select specific price is setup
 */
class ProductsServices {

  /**
   * The init
   */
    public static function init() {

        add_filter('wooms_product_save', array(__CLASS__, 'product_save'), 50, 2);

    }


    public static function product_save($product, $api_data){

        if($api_data["meta"]["type"] != 'service'){
            return $product;
        }

        $product->set_virtual(true);
        $product->set_manage_stock(false);
        $product->set_stock_status( $status = 'instock' );

        do_action('wooms_logger', __CLASS__, 
            sprintf('Продукт Услуга - сброс данных об остатках: %s (id::%s)', $product->get_name(), $product->get_id())
        );


        return $product;
    }

}

ProductsServices::init();
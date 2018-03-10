<?php

/**
 * Description functionality.
 *
 * @link https://wordpress.org/ see WordPress
 */
class WooMS_Import_Prices
{
  public function __construct()
  {
    // add_action('wooms_product_update', [$this, 'load_data'], 100, 3);
    add_filter('wooms_product_price', array($this, 'chg_price'), 10, 2);
  }


  /**
   * Update prices for product
   */
  public function chg_price( $price, $data ) {
    //Code
    // do_action('logger_u7', ['t1', $data]);

    return $price;
  }

}
new WooMS_Import_Prices;

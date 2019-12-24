<?php

namespace WooMS\Products;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class ImagesGallery {

    /**
     * WooMS_Import_Product_Images constructor.
     */
    public static function init() {

      /**
       * Обновление данных о продукте
       */
      add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 40, 3);


    }

  /**
   * update_product
   */
  public static function update_product($product, $value, $data){
    

    if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
            return $product;
        }
    $product_id = $product->get_id();


$pm_id = get_post_meta( $product_id, 'wooms_id', true );
$url = 'https://online.moysklad.ru/api/remap/1.2/entity/product/'.$pm_id.'/images';
$data_api = wooms_request($url);
var_dump($data_api);
//exit;


      //Check image
      if ( empty( $value['image']['meta']['href'] ) ) {
          return $product;
      } else {
          $url = $value['image']['meta']['href'];
      }

      //check current thumbnail. if isset - break, or add url for next downloading
      if ( $id = get_post_thumbnail_id( $product_id ) && empty( get_option( 'woomss_images_replace_to_sync' ) ) ) {
          return $product;
      } else {
      $product->update_meta_data('wooms_url_for_get_thumbnail', $url);
      $product->update_meta_data('wooms_image_data', $value['image']);
      }

    return $product;
  }

}

ImagesGallery::init();

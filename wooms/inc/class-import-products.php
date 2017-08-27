<?php

/**
 * Import products from MoySklad
 */
class woomss_tool_products_import {

    function __construct(){
      //do_action('wooms_product_import_row', $value, $key, $data);
        add_action('wooms_product_import_row', [$this, 'load_data'], 10, 3);
    }


    function load_data($value, $key, $data){

      if( ! empty($value['archived']))
        return;

      if( empty($value['article']))
        return;

      $product_id = wc_get_product_id_by_sku($value['article']);

      if( intval($product_id) ){
        $this->update_product($product_id, $value);
      } else {
        $this->add_product($value);
      }

      do_action('wooms_product_update', $product_id, $value, $data);

    }

    /**
     * Add product from source data
     *
     * @param $data_of_source var data of source from MoySklad
     * @return return bool - true or false if updated
     */
    function add_product($data_source){

        // $product = new WC_Product_Simple();
        $post_data = array(
          'post_type' => 'product',
          'post_title'    => wp_filter_post_kses( $data_source['name'] ),
          'post_status'   => 'draft'
        );

        if( ! apply_filters('wooms_add_product', true, $data_source)){
          return false;
        }

        // Вставляем запись в базу данных
        $post_id = wp_insert_post( $post_data );

        $product = wc_get_product($post_id);

        if( isset($data_source['article']) ){
          update_post_meta( $post_id, $meta_key = '_sku', $meta_value = $data_source['article'] );
        }

    }

    /**
     * Update product from source data
     *
     * @param $product_id var id product
     * @param $data_of_source var data of source from MoySklad
     * @return return bool - true or false if updated
     */
    function update_product($product_id, $data_of_source){

        $product = wc_get_product($product_id);


        //save data of source
        $now = date("Y-m-d H:i:s");
        update_post_meta($product_id, 'woomss_data_of_source', print_r($data_of_source, true));

        //the time stamp for database cleanup by cron
        update_post_meta($product_id, 'woomss_updated_timestamp', $now);

        update_post_meta($product_id, 'wooms_id', $data_of_source['id']);

        //update title
        if( isset($data_of_source['name']) and $data_of_source['name'] != $product->get_title() ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_title'  =>  $data_of_source['name']
          ));
        }

        //update description
        if( isset($data_of_source['description']) and empty($product->post->post_content) ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_content'  =>  $data_of_source['description']
          ));
        }


        //Price Retail 'salePrices'
        if(isset($data_of_source['salePrices'][0]['value'])){
          $price_source = floatval($data_of_source['salePrices'][0]['value']/100);

          if($price_source != $product->get_price()){
            update_post_meta( $product->id, '_regular_price', $price_source );
            update_post_meta( $product->id, '_price', $price_source );

          }
        }

        //update post
        wp_update_post( array(
          'ID' =>  $product_id
        ));

        $product->set_status('publish');
        $product->save();

        if(get_option('woomss_debug')){
          unset($data_of_source['description']);

        }

    }



} new woomss_tool_products_import;

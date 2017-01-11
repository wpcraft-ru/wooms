<?php

/**
 * Import products from MoySklad
 */
class woomss_tool_products_import extends woomss_import {

    function __construct(){
      parent::__construct();
      $this->section_title = __('Импорт продуктов');
      $this->section_exerpt = __('Эта обработка запускает поэтапную загрузку продуктов по 25 штук. Может занимать много времени.');
      $this->slug = 'woomss-import-products';
      $this->slug_action = 'woomss-import-products-async';

      add_action('add_meta_boxes', function(){
        add_meta_box( 'woomss_product_mb', 'МойСклад', array($this, 'woomss_product_mb_cb'), 'product', 'side' );
      });

    }


    function load_data(){
      echo '<p>load data start...</p>';

      $offset = 0;

      if( ! empty($_REQUEST['offset'])){
        $offset = intval($_REQUEST['offset']);
      }

      $url_get = add_query_arg(
                    array(
                      'offset' => $offset,
                      'limit' => 25
                    ),
                    'https://online.moysklad.ru/api/remap/1.1/entity/product/');

      $data = $this->get_data_by_url( $url_get );
      $rows = $data['rows'];

      printf('<p>Объем записей: %s</p>', $data['meta']['size']);

      foreach ($rows as $key => $value) {

        printf('<h2>%s</h2>', $value['name']);
        echo '<p><strong># data from MS</strong></p>';
        printf('<p>id: %s</p>', $value['id']);
        printf('<p>article: %s</p>', $value['article']);
        printf('<p>modificationsCount: %s</p>', $value['modificationsCount']);

        if( ! empty($value['archived']))
          continue;

        if( empty($value['article']))
          continue;

        $product_id = wc_get_product_id_by_sku($value['article']);

        if( intval($product_id) ){
          $this->update_product($product_id, $value);
        } else {
          $this->add_product($value);
        }
      }

      echo '<p>load data end...</p>';
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

        // Вставляем запись в базу данных
        $post_id = wp_insert_post( $post_data );

        $product = wc_get_product($post_id);

        if( isset($data_source['article']) ){
          update_post_meta( $post_id, $meta_key = '_sku', $meta_value = $data_source['article'] );
        }

        printf('<p>+ Добавлен продукт в базу: %s</p>', $post_id);
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

        printf('<p><strong># data updating for product id: %s</strong> <a href="%s">(edit link)</a></p>', $product_id, get_edit_post_link($product_id, ''));

        //save data of source
        $now = date("Y-m-d H:i:s");
        update_post_meta($product_id, 'woomss_data_of_source', print_r($data_of_source, true));
        update_post_meta($product_id, 'woomss_updated_timestamp', $now);
        printf('<p>+ Save source data in meta field "woomss_data_of_source" at time: %s</p>', $now);


        if( isset($data_of_source['name']) and $data_of_source['name'] != $product->get_title() ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_title'  =>  $data_of_source['name']
          ));

          printf('<p>+ Update title: %s</p>', $data_of_source['name']);
        } else {
          printf('<p>- Title no updated</p>');
        }

        if( isset($data_of_source['description']) and empty($product->post->post_content) ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_content'  =>  $data_of_source['description']
          ));
          printf('<p>+ Update post content: %s</p>', $product_id);
        } else {
          printf('<p>- Content no updated</p>');
        }

        //Image product update or rest
        if(isset($data_of_source['image'])){
          $this->save_image_product_from_moysklad($data_of_source['image'], $product_id);
        } else {
          $this->save_image_product_from_moysklad(null, $product_id);
        }

        //Price Retail 'salePrices'
        if(isset($data_of_source['salePrices'][0]['value'])){
          $price_source = floatval($data_of_source['salePrices'][0]['value']/100);

          if($price_source != $product->get_price()){
            update_post_meta( $product->id, '_regular_price', $price_source );
            update_post_meta( $product->id, '_price', $price_source );

            printf('<p>+ Update product price: %s</p>', $price_source);
          } else {
            printf('<p>- No update product price: %s</p>', $price_source);
          }
        }

        // Update data of attributes
        if(isset($data_of_source['attributes'])){
          $this->update_attributes( $product_id, $data_of_source['attributes'] );
        }

        wp_update_post( array(
          'ID' =>  $product_id
        ));

        if(get_option('woomss_debug')){
          unset($data_of_source['description']);
          printf('<pre>%s</pre>', print_r($data_of_source, true));

        }

        do_action( 'woomss_update_product', $product_id, $data_of_source );
    }

    /**
     * Check save or not image for product
     *
     * @param $product_id - id of product
     * @param $url_image_moysklad - irl image from MoySklad
     * @return bool
     */
    function is_image_save($product_id, $url_image_moysklad){
      $data = get_posts('post_type=attachment&meta_key=_href_moysklad&meta_value=' . esc_url_raw($url_image_moysklad) );
      if( ! empty($data) ){
        return true;
      }
      return false;
    }

   /**
    * Save image from MoySklad for Product
    *
    * @param $data_ms  - data of image from MoySklad REST API
    * @param $product_id  - ID of product WooCommerce
    * @return
    */
    private function save_image_product_from_moysklad($data_ms, $product_id){

      if( get_option( 'woomss_img' ) != 1){
        return;
      }

      if ( is_array( $data_ms ) ) {

  			if ( isset( $data_ms['meta']['href'] ) and ! $this->is_image_save($product_id, $data_ms['meta']['href']) ) {

  				$upload = woomss_upload_image_from_url( esc_url_raw( $data_ms['meta']['href'] ), $data_ms['filename'] );

  				if ( is_wp_error( $upload ) ) {
  					return false;
  				}

  				$attachment_id = woomss_set_uploaded_image_as_attachment( $upload, $product_id );
  			} else {
          printf('<p>- The image is already saved: %s</p>', $data_ms['meta']['href']);
        }

  			if ( ! wp_attachment_is_image( $attachment_id ) ) {
  				return false;
  			}

        printf('<p>+ For product loaded image id: %s</p>', $attachment_id);

        update_post_meta($attachment_id, '_href_moysklad', esc_url_raw( $data_ms['meta']['href'] ) );

  			set_post_thumbnail( $product_id, $attachment_id );

  		} else {
        if(get_option( 'woomss_img' )){
          delete_post_meta( $product_id, '_thumbnail_id' );
          printf('<p>Removed images for product id: %s</p>', $product_id);
        }
  		}
    }

    /**
     * Updating attributes for product
     *
     * @param $product_id - id product
     * @param $attributes - array of attributes
     * @return return type
     */
    public function update_attributes($product_id, $attributes){

      if(is_array($attributes)){
        $product = wc_get_product($product_id);

        $product_attributes_v1 = get_post_meta($product_id, '_product_attributes', true);
        $product_attributes_v2 = array();

        foreach ($attributes as $key => $attribute) {
          //Type attribute
          $product_attributes_v2[$attribute['id']] = array(
              //Make sure the 'name' is same as you have the attribute
              'name' => htmlspecialchars(stripslashes($attribute['name'])),
              'value' => $attribute['value'],
              'position' => 0,
              'is_visible' => 0,
              'is_variation' => 0,
              'is_taxonomy' => 0
          );
        }

        if($product_attributes_v1 != $product_attributes_v2) {
          //Add as post meta
          update_post_meta($product_id, '_product_attributes', $product_attributes_v2);

          printf('<p>+ Updated product attributes: %s</p>', count($product_attributes_v2));
        } else {
          printf('<p>- Product attributes not updated: %s</p>', count($product_attributes_v1));

        }

      } else {
        delete_post_meta( $product_id, '_product_attributes' );
        printf('<p>+ Deleted attributes for product</p>');

      }

      return true;
    }


    /**
     * Show UI for product edit form and update data from MoySklad
     *
     * @return HTML block for Metabox
     */
    public function woomss_product_mb_cb(){
      $post = get_post();
      ?>
        <input id="woomss_updating_enable" type="checkbox" name="woomss_updating_enable" value="1" />
        <label for="woomss_updating_enable">Обновить данные</label>
        <p><small>Если отметить этот параметр, то при сохранении система обновит данные из МойСклад</small></p>
      <?php
    }

} new woomss_tool_products_import;

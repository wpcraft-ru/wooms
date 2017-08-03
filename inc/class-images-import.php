<?php



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

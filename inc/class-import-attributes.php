<?php

/**

// Update data of attributes
if(isset($data_of_source['attributes'])){
  $this->update_attributes( $product_id, $data_of_source['attributes'] );
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

        }

      } else {
        delete_post_meta( $product_id, '_product_attributes' );

      }

      return true;
    }

**/
 ?>

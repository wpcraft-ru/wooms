<?php

/**
 * Import variants from MoySklad
 */
class woomss_tool_import_variaints extends woomss_import {

  function __construct(){
      parent::__construct();
      $this->section_title = __('Импорт вариаций');
      $this->section_exerpt = __('Импортируем вариации и привязываем их к продуктам WooCommerce');
      $this->slug = 'woomss_tool_import_variaints';
      $this->slug_action = 'woomss_tool_import_variaints_do';
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
                  'https://online.moysklad.ru/api/remap/1.1/entity/variant/');



    $data = $this->get_data_by_url( $url_get );
    $rows = $data['rows'];

    printf('<p>Объем записей: %s</p>', $data['meta']['size']);

    foreach ($rows as $key => $row) {
      printf('<h2>%s</h2>', $row['name']);


      $product_data = $this->get_data_by_url($row['product']['meta']['href']);

      if(empty($product_data['article'])){
        continue;
      } else {
        $article = $product_data['article'];
      }

      $product_id = intval(wc_get_product_id_by_sku($article));

      if(empty($product_id)){
        echo '<p>no found products</p>';
        continue;
      }



      $product_type = get_the_terms( $product_id, 'product_type' );

      //Convert product type from array as string
      if( ! empty($product_type)){
        $product_type = $product_type[0]->name;
      }

      if($product_type != 'variable'){
        wp_set_object_terms( $product_id, 'variable', 'product_type' );
        
        printf('<p>+ Set product as: %s</p>', 'variable');
      }

      $this->save_variations_data($product_id, $row);

    }

  }



  function save_variations_data($product_id = 0, $data_variation){

    if(empty($product_id))
      return;

    printf('<p>for product id: %s, name: %s</p>', $product_id, get_the_title( $product_id ));
    printf('<p><a href="%s">edit link</a></p>', get_edit_post_link( $product_id, '' ));

    // printf('<hr><pre>%s</pre><hr>', print_r($data_variation, true));

    $product = wc_get_product($product_id);

    $characteristics = $data_variation['characteristics'];

    printf('<p># try update characteristics. count: %s</p>', count($characteristics));
    $this->save_characteristics_as_attributes($product_id, $characteristics);


    if ( $product->is_type( 'variable' ) && $product->has_child() ) {
      $variations = $product->get_children();
    }

    printf('<p># Check and get isset variation for %s</p>', $data_variation['id']);

    //Isset variation?
    $check_variations = get_posts( array(
      'meta_key' => 'woomss_id',
      'meta_value' => esc_textarea($data_variation['id']),
      'include' => $variations,
      'post_parent'  => $product_id,
      'post_type' => 'product_variation'
    ));


    if( empty($check_variations) ){

      //create variation from data
      $variation_post_title = esc_textarea($data_variation['name']);

			$new_variation = array(
				'post_title'   => $variation_post_title,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_parent'  => $product_id,
				'post_type'    => 'product_variation'
			);

			$variation_id = wp_insert_post( $new_variation );

      update_post_meta( $variation_id, 'woomss_id', esc_textarea($data_variation['id']) );

      // $key_pa = 'pa_' . esc_textarea($characteristic['id']);
      // update_post_meta( $variation_id, $key_pa, esc_textarea($characteristic['value']) );


      printf('<p>+ Added variation: %s</p>', $data_variation['name']);


    } else {
      //Get variation id
      $variation_id = $check_variations[0]->ID;
      $variation = $product->get_child($variation_id);

      printf('<p>- Isset variation: %s</p>', $variation_id);

    }

    printf('<p># Try update data for variation %s</p>', $variation_id);

    foreach ($characteristics as $characteristic) {
      $attribute_key           = 'attribute_' . sanitize_title( $characteristic['name'] );
      if( update_post_meta( $variation_id, $attribute_key, $characteristic['value']) ){
        printf('<p>+ Update attribute key: %s</p>', $attribute_key);
      } else {
        printf('<p>- Attribute key isset: %s</p>', $attribute_key);
      }
    }

    $status_update = wc_update_product_stock_status( $variation_id, 'instock' );
    printf('<p>+ Stock status update: %s</p>', 'ok');


    // printf('<pre>%s</pre>', print_r($data_variation, true));


    $product = (array)$product;
    unset($product['post']->post_content);


    return true;
  }


    /**
     * Save attributes after check from data MS
     *
     * @param integer $product_id
     * @return return type
     */
    function save_characteristics_as_attributes($product_id, $characteristics){

          $product = wc_get_product($product_id);


          //Check and save characteristics as attributes with variation tag
          foreach ($characteristics as $characteristic) {


            $key_pa = 'pa_' . $characteristic['id'];
            $attributes = $product->get_attributes();

            $saved_value = $product->get_attribute( $key_pa );

            if(empty($saved_value)){
              $attributes[$key_pa] = array(
                'name' => esc_textarea($characteristic['name']),
                'value' => esc_textarea($characteristic['value']),
                'position' => 0,
                'is_visible' => 0,
                'is_variation' => 1,
                'is_taxonomy' => 0
              );
              printf('<p>+ Attribute "%s". Added with value: %s</p>', $characteristic['name'], $characteristic['value']);
              //Save $attributes in metafield
              update_post_meta($product_id, '_product_attributes', $attributes);


            } else {
              //Если атрибут есть, но значение не совпадает

              $values_array = array_map('trim', explode("|", $saved_value));

              if ( ! in_array(esc_textarea($characteristic['value']), $values_array) ){
                $attributes[$key_pa]['value'] .= ' | ' . esc_textarea($characteristic['value']);
                printf('<p>+ Attribute "%s". Saved value: %s</p>', $characteristic['name'], $attributes[$key_pa]['value']);

                //Save $attributes in metafield
                update_post_meta($product_id, '_product_attributes', $attributes);

              }
            }


            // printf('<hr><pre>%s</pre><hr>', print_r($att, true));


          }
    }

} new woomss_tool_import_variaints;

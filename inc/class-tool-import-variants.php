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

      printf('<p><a href="%s">edit link</a></p>', get_edit_post_link( $product_id, '' ));

      if(empty($product_id)){
        continue;
      }

      $product = wc_get_product($product_id);
      $product_type = get_the_terms( $product->id, 'product_type' );
      if( ! empty($product_type)){
        $product_type = $product_type[0]->name;
      }

      if($product_type != 'variable'){
        wp_set_object_terms( $product->id, 'variable', 'product_type' );
        printf('<p>+ Set product as: %s</p>', 'variable');
      }

      $this->save_variations_data($product_id, $row);

    }

  }


  function save_variations_data($product_id = 0, $data_variation){

    if(empty($product_id))
      return;

    $product = wc_get_product($product_id);

    $attributes = $product->get_attributes();

    $characteristics = $data_variation['characteristics'];

    //Check and save characteristics as attributes with variation tag
    foreach ($characteristics as $characteristic) {
      $key_pa = 'pa_' . $characteristic['id'];

      //Если нет атрибута соответствующего характеристике то создаем таковой
      if(empty($attributes[$key_pa])){
        $attributes[$key_pa] = array(
          'name' => htmlspecialchars(stripslashes($characteristic['name'])),
          'value' => htmlspecialchars(stripslashes($characteristic['value'])),
          'position' => 0,
          'is_visible' => 0,
          'is_variation' => 1,
          'is_taxonomy' => 0
        );

        //Add $attributes
        update_post_meta($product_id, '_product_attributes', $attributes);

        printf('<p>+ Add attribute is_variation: %s</p>', $characteristic['name']);
      }
    }

    if ( $product->is_type( 'variable' ) && $product->has_child() ) {
      $variations = $product->get_children();
    }

    //Isset variation?
    $check_variations = get_posts( array(
      'meta_key' => 'woomss_id',
      'meta_value' => htmlspecialchars(stripslashes($characteristic['id'])),
      'include' => $variations,
      'post_type' => 'product_variation'
    ));


    if( empty($check_variations) ){
      //create variation from data
      $variation_post_title = htmlspecialchars(stripslashes($data_variation['name']));

			$new_variation = array(
				'post_title'   => $variation_post_title,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_parent'  => $product->id,
				'post_type'    => 'product_variation'
			);

			$variation_id = wp_insert_post( $new_variation );

      update_post_meta( $variation_id, 'woomss_id', htmlspecialchars(stripslashes($characteristic['id'])) );

      foreach ($characteristics as $characteristic) {
        $key_pa = 'pa_' . $characteristic['id'];
        update_post_meta( $variation_id, $key_pa, htmlspecialchars(stripslashes($characteristic['value'])) );
      }


      printf('<p>+ Added variation: %s</p>', $data_variation['name']);


    } else {
      //update variation

      var_dump($check_variations);

      $variation_id = $check_variations; //???
      $variation = $product->get_child($variation_id);
    }



    var_dump($check_variations);

    // printf('<pre>%s</pre>', print_r($characteristics, true));
    // printf('<pre>%s</pre>', print_r($attributes, true));




    printf('<pre>%s</pre>', print_r($data_variation, true));


    $product = (array)$product;
    unset($product['post']->post_content);


    return true;
  }



} new woomss_tool_import_variaints;

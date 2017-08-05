<?php


/**
 * Import Product Categories from MoySklad
 */
class WooMS_Import_Product_Categories
{

  function __construct()
  {
    //do_action('wooms_product_import_row', $value, $key, $data);

    add_action('wooms_product_import_row', [$this, 'load_data'], 100, 3);
  }

  function load_data($value, $key, $data){
    if(empty($value['productFolder']['meta']['href'])){
      return;
    }

    $url = $value['productFolder']['meta']['href'];

    if( $term_id = $this->update_category($url) ){

      if( empty($value['article']) ){
        return;
      }

      if( $post_id = wc_get_product_id_by_sku($value['article']) ){

        wp_set_object_terms( $post_id, $term_id, $taxonomy = 'product_cat' );
      }
    }



  }

  function update_category($url){
    $data = wooms_get_data_by_url($url);

    if($term_id = $this->check_term_by_ms_id($data['id'])){
      return $term_id;
    } else {

      $new = [
        'id' => $data['id'],
        'name' => $data['name'],
        'archived' => $data['archived'],
      ];


      if(isset($data['productFolder']['meta']['href'])){
        $url_parent = $data['productFolder']['meta']['href'];
        if($term_id_parent = $this->update_category($url_parent)){
          $new['parent_id'] = intval($term_id_parent);


        }
      }



      $args = array(
      	'description' => '',
      );

      if(isset($new['parent_id'])){
        $args['parent'] = $new['parent_id'];
      }



      $term_id = wp_insert_term( $term = $new['name'], $taxonomy = 'product_cat', $args );

      if(is_wp_error($term_id)){

        if(isset($term_id->errors["term_exists"])){
          $term_id = $term_id->error_data['term_exists'];
          update_term_meta($term_id, 'wooms_id', $new['id']);
        }

      }

      $term_id = intval($term_id);

      if(empty($term_id)){
        return false;
      } else {
        update_term_meta($term_id, 'wooms_id', $new['id']);
        return $term_id;
      }
    }

  }



  //If isset term return term_id, else return false
  function check_term_by_ms_id($id){

    $terms = get_terms('taxonomy=product_cat&meta_key=wooms_id&meta_value='.$id);

    if(empty($terms)){
      return false;
    } else {
      return $terms[0]->term_id;
    }
  }

}
new WooMS_Import_Product_Categories;

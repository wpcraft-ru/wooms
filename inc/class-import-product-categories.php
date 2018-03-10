<?php
/**
 * Import Product Categories from MoySklad
 */
class WooMS_Import_Product_Categories {

  function __construct() {
    /**
    * Use hook: do_action('wooms_product_update', $product_id, $value, $data);
    */
    add_action('wooms_product_update', [$this, 'load_data'], 100, 3);

    add_action( 'admin_init', array($this, 'settings_init'), 100 );
  }

  function load_data($product_id, $value, $data){

    //Если опция отключена - пропускаем обработку
    if(empty(get_option('woomss_categories_sync_enabled'))){
      return;
    }
    
    if(empty($value['productFolder']['meta']['href'])){
      return;
    }

    $url = $value['productFolder']['meta']['href'];

    if( $term_id = $this->update_category($url) ){

      wp_set_object_terms( $product_id, $term_id, $taxonomy = 'product_cat' );
    }

  }

  function update_category($url){
    $data = wooms_get_data_by_url($url);

    if($term_id = $this->check_term_by_ms_id($data['id'])){

      return $term_id;
    } else {

      $args = array();

      $term_new = [
        'wooms_id' => $data['id'],
        'name' => $data['name'],
        'archived' => $data['archived'],
      ];

      if(isset($data['productFolder']['meta']['href'])){
        $url_parent = $data['productFolder']['meta']['href'];
        if($term_id_parent = $this->update_category($url_parent)){
          $args['parent'] = intval($term_id_parent);
        }
      }

      $term = wp_insert_term( $term_new['name'], $taxonomy = 'product_cat', $args );

      if(isset($term->errors["term_exists"])){
        $term_id = intval($term->error_data['term_exists']);
        if(empty($term_id)){
          return false;
        }
      } elseif(isset($term->term_id)){
        $term_id = $term->term_id;
      } elseif(isset($term["term_id"])){
        $term_id = $term["term_id"];
      } else {
        return false;
      }

      update_term_meta($term_id, 'wooms_id', $term_new['wooms_id']);
      return $term_id;
    }

  }

  /**
  * If isset term return term_id, else return false
  */
  function check_term_by_ms_id($id){

    $terms = get_terms('taxonomy=product_cat&meta_key=wooms_id&meta_value='.$id);

    if(empty($terms)){
      return false;
    } else {
      return $terms[0]->term_id;
    }
  }

  /**
  * Settings UI
  */
  function settings_init(){

    register_setting('mss-settings', 'woomss_categories_sync_enabled');
    add_settings_field(
      $id = 'woomss_categories_sync_enabled',
      $title = 'Включить синхронизацию категорий',
      $callback = [$this, 'display_option_categories_sync_enabled'],
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );

  }

  //Display field
  function display_option_categories_sync_enabled(){
    $option = 'woomss_categories_sync_enabled';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option($option), false ));
    ?>
    <small>Если включить опцию, то при обновлении продуктов будут создаваться и связываться категории в соответствии с группами МойСклад.</small>
    <?php
  }
}
new WooMS_Import_Product_Categories;

<?php
/*
Функции CRUD для работы с uuid групп МойСклад и категорий продуктов

UUID это идентификатор группы в МойСклад, которую нужно хранить для связи с соответствующей категорией продуктов МойСклад
*/
$term_id = get_term_id_by_uuid_mss($uuid);

//добавляем uuid МС для категории продуктов WC
function update_uuid_product_category_mss($term_id, $uuid){

  update_term_meta( $term_id, 'uuid_product_category_mss', $uuid );


}

//удаляем uuid МС для категории продуктов WC
function delete_uuid_product_category_mss($term_id){

  delete_term_meta( $term_id, 'uuid_product_category_mss' );

}

//получает uuid МС для категории продуктов WC
//Возвращает значение uuid для term_id
function get_uuid_product_category_mss($term_id){

  $data = get_term_meta( $term_id, 'uuid_product_category_mss', true );

  return $data;
}


//получает term_id категории продуктов WC по uuid МС
//Возвращает значение term_id для uuid

function get_term_id_by_uuid_mss($uuid){
  global $wpdb;
  $data =  $wpdb->get_results("SELECT term_id FROM $wpdb->termmeta WHERE meta_value = '" . $uuid . "' LIMIT 1" );
  $data = $data[0]->term_id;

  return $data;
}



//Добавляем поля на форму категорий продуктов
function mss_uuid_product_cat_form($term){
  wp_nonce_field( basename( __FILE__ ), 'mss_uuid_product_cat_nonce' );
  //echo print_r( get_option('uuid_product_category_mss'), true);
  ?>
    <div class="form-field mss_uuid_product_cat">
        <label for="mss_uuid_product_cat">uuid МойСклад</label><br/>
        <input type="text" name="mss_uuid_product_cat" id="mss_uuid_product_cat" value="<?php echo get_uuid_product_category_mss($term->term_id); ?>"/>
        <p><small>Это поле идентификатор для синхронизации с МойСклад</small></p>
    </div>
  <?php
}
add_action( $tag = 'product_cat_edit_form_fields', $function_to_add = 'mss_uuid_product_cat_form', $priority = 10 );

//Сохранение данных поля uuid для категории продуктов
function mss_uuid_product_cat_form_edit($term_id){
  if ( ! isset( $_POST['mss_uuid_product_cat'] ) || ! wp_verify_nonce( $_POST['mss_uuid_product_cat_nonce'], basename( __FILE__ ) ) )
          return;

  $mss_uuid_product_cat = $_POST['mss_uuid_product_cat'];

  if(empty($mss_uuid_product_cat)) {
    delete_uuid_product_category_mss($term_id);
  } else {
    update_uuid_product_category_mss($term_id, $mss_uuid_product_cat);
  }

}
add_action( 'edit_product_cat', 'mss_uuid_product_cat_form_edit' );

<?php

/**
 * Import products from MoySklad
 */
class woomss_tool_products_import {

  public $url;
  public $slug = 'woomss-import-products';
  public $slug_action = 'woomss-import-products-async';
  public $btn_text = 'Импортировать';



    function __construct(){

      $this->url = $_SERVER['REQUEST_URI'];

      add_action( 'woomss_tool_actions_btns', [$this, 'woomss_tool_actions_btns_callback'], $priority = 10, $accepted_args = 1 );

      add_action( 'woomss_tool_actions', [$this, 'woomss_tool_actions_cb'], $priority = 10, $accepted_args = 1 );

      // add_action( 'admin_action_' . $this->slug_action, [$this, 'load_data'] );

      add_action('add_meta_boxes', function(){
        add_meta_box( 'woomss_product_mb', 'МойСклад', array($this, 'woomss_product_mb_cb'), 'product', 'side' );
      });

      // add_action('update_post')

    }


    function woomss_product_mb_cb(){
      $post = get_post();
      ?>
        <input id="woomss_updating_enable" type="checkbox" name="woomss_updating_enable" value="1" />
        <label for="woomss_updating_enable">Обновить данные</label>
        <p><small>Если отметить этот параметр, то при сохранении система обновит данные из МойСклад</small></p>
      <?php
    }

    /**
     * Update product from source data
     *
     * @param $product_id var id product
     * @param $data_of_source var data of source from MoySklad
     * @return return bool - true or false if updated
     */
    private function update_product($product_id, $data_of_source){

        $product = wc_get_product($product_id);

        printf('<p><strong># data compare for SKU: %s</strong></p>',$data_of_source['article']);
        printf('<p>product id: %s</p>', $product_id);
        printf('<p>woo product title: %s</p>', $product->get_title());
        printf('<p>ms good title: %s</p>', $data_of_source['name']);

        // $res = $product->set_name($data_of_source['name']);

        printf('<p><strong># data updating...</strong></p>');
        //save data of source
        update_post_meta($product_id, 'woomss_data_of_source', print_r($data_of_source, true));
        update_post_meta($product_id, 'woomss_updated_timestamp', date("Y-m-d H:i:s"));

        if( isset($data_of_source['name']) and $data_of_source['name'] != $product->get_title() ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_title'  =>  $data_of_source['name']
          ));

          printf('<p>update title: %s</p>', $data_of_source['name']);
        }

        if( isset($data_of_source['description']) and empty($product->post->post_content) ){
          wp_update_post( array(
            'ID'          =>  $product_id,
            'post_content'  =>  $data_of_source['description']
          ));

          printf('<p>update post content: %s</p>', $product_id);
        }


        printf('<pre>%s</pre>', print_r($data_of_source, true));

        do_action( 'woomss_update_product', $product_id, $data_of_source );
    }




  function load_data(){
    echo '<p>load data start...</p>';

    $offset = 0;

    if( ! empty($_REQUEST['offset'])){
      $offset = intval($_REQUEST['offset']);
    }

    $url_get = 'https://online.moysklad.ru/api/remap/1.1/entity/product/';

    $url_get = add_query_arg(
                  array(
                    'offset' => $offset,
                    'limit' => 25
                  ),
                  $url_get);

    $username = get_option( 'woomss_login' );
    $password = get_option( 'woomss_pass' );

    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
        )
      );


    $response = wp_remote_get( $url_get, $args );
    $body = $response['body'];

    $data = json_decode( $body, true );
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
      }
    }

    echo '<p>load data end...</p>';
  }


  function add_product($data_source){

      $product = new WC_Product_Simple();

      if ( isset( $data_source['name'] ) ) {
  			$product->set_name( wp_filter_post_kses( $data_source['name'] ) );
  		}

      $product->set_status( 'draft' );

      $product->save();

      var_dump($product);
  }


  function woomss_tool_actions_cb(){

    if( empty($_GET['a']) or $_GET['a'] != $this->slug)
      return;

    echo '<hr/>';
    $this->load_data();

  }

  function woomss_tool_actions_btns_callback(){
    ?>
    <form method="GET" action="<?php echo $this->url ?>">
      <h2>Импорт товаров из МойСклад</h2>
      <p>Эта обработка запускает поэтапную загрузку продуктов по 25 штук. Может занимать много времени.</p>

      <?php
        printf('<input type="hidden" name="a" value="%s" />', $this->slug);
        printf('<input type="hidden" name="page" value="%s" />', 'moysklad');
        printf('<input type="submit" class="button" value="%s" >', $this->btn_text);
      ?>

      <hr/>
    </form>
    <?php
  }

} new woomss_tool_products_import;

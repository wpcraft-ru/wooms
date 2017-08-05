<?php

/**
 * Product Import Walker
 * do_action('wooms_product_import_row', $value, $key, $data);
 */
class WooMS_Product_Import_Walker {

  function __construct(){
    add_action('woomss_tool_actions_btns', [$this, 'ui']);
    add_action('woomss_tool_actions', [$this, 'ui_action']);

    add_action('wp_ajax_nopriv_wooms_walker_import', [$this, 'walker']);
    add_action('wp_ajax_wooms_walker_import', [$this, 'walker']);
  }

  function ui(){

    ?>
    <h2>Импорт продуктов</h2>
    <p>Обработка запускает импорт продуктов</p>

    <a href="<?php echo add_query_arg('a', 'wooms_products_start_import', admin_url('tools.php?page=moysklad')) ?>" class="button">Старт импорта продуктов</a>
    <?php

    echo get_transient('wooms_last_url');
  }

  function ui_action(){
    if(! empty($_GET['a'] and $_GET['a'] == 'wooms_products_start_import')){

      $url = add_query_arg('action', 'wooms_walker_import', admin_url('admin-ajax.php'));
      wp_remote_get($url);

      printf( '<p>Импорт запущен. Запрос: %s</p>', $url);

    }
  }

  function walker(){
    // wp_mail( $to = 'yumashev@fleep.io', $subject='test', $message='ddd' );

    $iteration = 10;

    if( empty($_GET['count'])){
      $count = $iteration;
    } else {
      $count = intval($_GET['count']);
    }

    if( empty($_GET['offset'])){
      $offset = 0;
    } else {
      $offset = intval($_GET['offset']);
    }

    $args_ms_api = [
      'offset' => $offset,
      'limit' => $count
    ];

    $url_get = add_query_arg($args_ms_api, 'https://online.moysklad.ru/api/remap/1.1/entity/product/');

    try {

        $data = $this->get_data_by_url( $url_get );
        $rows = $data['rows'];

        if(empty($rows)){
          //If no rows, that send 'end' and stop walker
          wp_send_json(['end waler', $data]);
        }


        foreach ($rows as $key => $value) {
          do_action('wooms_product_import_row', $value, $key, $data);
        }

        if( isset($_GET['batch'])){
          $args = [
            'action' => 'wooms_walker_import',
            'batch' => 1,
            'count' => $iteration,
            'offset' => $offset + $iteration,
          ];
          $url = add_query_arg('action', 'wooms_walker_import', add_query_arg($args,admin_url('admin-ajax.php')) );
          set_transient('wooms_last_url', $url, 60*60);
          wp_remote_get($url);
        }

        wp_send_json($data);

    } catch (Exception $e) {
      wp_send_json_error( $e->getMessage() );
    }

  }

  function get_data_by_url($url = ''){

    if(empty($url)){
      return false;
    }

    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) )
        )
      );

    $response = wp_remote_get( $url, $args );
    $body = $response['body'];

    return json_decode( $body, true );

  }

}

new WooMS_Product_Import_Walker;

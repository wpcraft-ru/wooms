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

    add_action( 'admin_notices', [$this, 'notice'] );
    add_action( 'admin_notices', [$this, 'error_notice'] );

    add_action( 'admin_init', array($this, 'settings_init'), $priority = 100, $accepted_args = 1 );

  }

  function error_notice(){

    $screen = get_current_screen();

    if($screen->base != 'tools_page_moysklad'){
      return;
    }

    if(empty(get_transient('woomss_error_background'))){
      return;
    }

    ?>
    <div class="update-nag">
      <p><strong>Обработка заверишлась с ошибкой.</strong></p>
      <p>Данные: <?php echo get_transient('woomss_error_background') ?></p>
    </div>
    <?php



  }

  function settings_init(){
    register_setting('mss-settings', 'wooms_check_security_disable');
    add_settings_field(
      $id = 'wooms_check_security_disable',
      $title = 'Отключить проверку безопасности AJAX',
      $callback = [$this, 'wooms_check_security_disable_display'],
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );
  }

  function wooms_check_security_disable_display(){
    $option_name = 'wooms_check_security_disable';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option($option_name), false ));
    ?>

    <p><small>По умолчанию опция галочка должна быть снята. Включать ее можно только тем кто понимает что делает для целей отладки и разработки фоновых задач</small></p>
    <?php

  }

  function notice() {

    $screen = get_current_screen();

    if($screen->base != 'tools_page_moysklad'){
      return;
    }

    if(empty(get_transient('wooms_start_timestamp'))){
      return;
    }

    $time_string = get_transient('wooms_start_timestamp');
    $time = strtotime($time_string);
    $diff_minutes = round(($time - strtotime('-5 minutes'))/60, 2);

    ?>
    <div class="update-nag">
      <p><strong>Сейчас выполняется пакетная обработка данных в фоне.</strong></p>
      <p>Отметка времени о последней итерации: <?php echo $time_string ?>, количество прошедших минут: <?php echo $diff_minutes ?></p>
      <p>Ссылка на последний запрос в очереди: <?php echo get_transient('wooms_last_url') ?></p>
    </div>
    <?php
  }


  function ui(){

    ?>
    <h2>Импорт продуктов</h2>
    <p>Нажмите на кнопку ниже, чтобы запустить импорт продуктов вручную</p>

    <a href="<?php echo add_query_arg('a', 'wooms_products_start_import', admin_url('tools.php?page=moysklad')) ?>" class="button">Старт импорта продуктов</a>
    <?php

  }

  function ui_action(){
    if(isset($_GET['a']) and $_GET['a'] == 'wooms_products_start_import'){

      delete_transient('wooms_start_timestamp');


      $args =[
        'action' => 'wooms_walker_import',
        'batch' => '1',
        'nonce' => wp_create_nonce('wooms-nonce')

      ];
      $url = add_query_arg($args, admin_url('admin-ajax.php'));
      wp_remote_get($url);

      printf( '<p>Импорт запущен. Вы можете вернуться на шаг назад, чтобы увидеть сообщение о статусе и прогрессе.</p><p><small>Запрос: %s</small></p>', $url);

    }
  }

  function walker(){


    if(empty(get_option('wooms_check_security_disable'))){
      check_ajax_referer( 'wooms-nonce', 'nonce' );
    }

    $iteration = apply_filters('wooms_iteration_size', 10);

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
        set_transient('wooms_start_timestamp', date("Y-m-d H:i:s"), 60*30);

        $data = wooms_get_data_by_url( $url_get );
        $rows = $data['rows'];

        if(empty($rows)){
          //If no rows, that send 'end' and stop walker

          delete_transient('wooms_start_timestamp');
          wp_send_json(['end walker', $data]);
        }


        foreach ($rows as $key => $value) {
          do_action('wooms_product_import_row', $value, $key, $data);
        }

        if( isset($_GET['batch'])){
          $args = [
            'action' => 'wooms_walker_import',
            'nonce' => wp_create_nonce('wooms-nonce'),
            'batch' => 1,
            'count' => $iteration,
            'offset' => $offset + $iteration,
          ];
          $url = add_query_arg('action', 'wooms_walker_import', add_query_arg($args,admin_url('admin-ajax.php')) );
          set_transient('wooms_last_url', $url, 60*60);

          $args_remote = [
            'timeout'   => apply_filters('wooms_request_timeout', 55)
          ];

          $check = wp_remote_get($url,$args_remote);

          if(is_wp_error($check)){
            $check = wp_remote_get($url,$args_remote);
          }

          if(is_wp_error($check)){
            set_transient('woomss_error_background', "Error request: " . $url, 60*60);
            throw new Exception('Error request wp_remote_get. Link: ' . $url);
          }

        }

        wp_send_json($data);

    } catch (Exception $e) {

      delete_transient('wooms_start_timestamp');
      wp_send_json_error( $e->getMessage() );
    }

  }

}

new WooMS_Product_Import_Walker;

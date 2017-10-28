<?php

/**
 * Product Import Walker
 * do_action('wooms_product_import_row', $value, $key, $data);
 * Example url: /wp-admin/admin-ajax.php?action=wooms_walker_import&batch=1
 */
class WooMS_Product_Import_Walker
{

    function __construct()
    {
      add_action('woomss_tool_actions_btns', [$this, 'ui']);
      add_action('woomss_tool_actions', [$this, 'ui_action']);

      add_action('wp_ajax_nopriv_wooms_walker_import', [$this, 'walker']);
      add_action('wp_ajax_wooms_walker_import', [$this, 'walker']);

      add_action( 'admin_notices', array($this, 'notice_walker') );
      add_action( 'admin_notices', array($this, 'notice_error') );
      add_action( 'admin_notices', array($this, 'notice_results') );

      add_action( 'admin_init', array($this, 'settings_init'), $priority = 100, $accepted_args = 1 );
    }

    /**
    * Walker for data from MoySklad
    */
    function walker()
    {
      if (empty(get_option('wooms_check_security_disable'))) {
        if(empty($_REQUEST['_wooms_nonce'])){
          wp_send_json('security check failure. nonce empty');
        }

        if( ! $this->wooms_nonce_check($_REQUEST['_wooms_nonce'])){
          wp_send_json('security check failure');
        }
      }

      if( ! empty(get_transient('wooms_walker_stop')) ){
        delete_transient('wooms_start_timestamp');
        delete_transient('wooms_walker_stop');
        wp_send_json(['stopped manually']);
        return;
      }

      $iteration = apply_filters('wooms_iteration_size', 10);

      if (empty($_GET['count'])) {
          $count = $iteration;
      } else {
          $count = intval($_GET['count']);
      }

      if (empty($_GET['offset'])) {
          $offset = 0;
          delete_transient('wooms_count_stat');
      } else {
          $offset = intval($_GET['offset']);
          set_transient('wooms_count_stat', $offset + $count, DAY_IN_SECONDS);
      }

      $args_ms_api = [
        'offset' => $offset,
        'limit' => $count
      ];

      $url_api = add_query_arg($args_ms_api, 'https://online.moysklad.ru/api/remap/1.1/entity/product/');

      try {

          delete_option('wooms_end_timestamp');

          set_transient('wooms_start_timestamp', date("Y-m-d H:i:s"), 60*30);

          $data = wooms_get_data_by_url( $url_api );

          if (isset($data['errors'])) {
              $error = $data['errors'][0];
              $code = $error['code'];

              if ($code == 429001) {
                  $msg = sprintf('Wrong username or password: %s, исправьте в <a href="%s">настройках</a>', $code, admin_url('options-general.php?page=mss-settings'));
                  throw new Exception($msg);
              } else {
                  wp_send_json_error($data);
              }
          }

          if (empty($data['rows'])) {
            //If no rows, that send 'end' and stop walker
              delete_transient('wooms_start_timestamp');
              update_option('wooms_end_timestamp', date("Y-m-d H:i:s"), 'no');
              wp_send_json(['end walker', $data]);
          }

          foreach ($data['rows'] as $key => $value) {
              do_action('wooms_product_import_row', $value, $key, $data);
          }

          if (isset($_GET['batch'])) {
              $args = [
                'action' => 'wooms_walker_import',
                '_wooms_nonce' => $this->wooms_nonce_create(),
                'batch' => 1,
                'count' => $iteration,
                'offset' => $offset + $iteration,
              ];

              $url = add_query_arg($args, admin_url('admin-ajax.php') );
              set_transient('wooms_last_url', $url, 60*60);

              $args_remote = [
                'timeout'   => apply_filters('wooms_request_timeout', 50)
              ];

              $check = wp_remote_get($url, $args_remote);

              if (is_wp_error($check) || 200 !== wp_remote_retrieve_response_code( $check )) {
                  // $check = wp_remote_get($url,$args_remote);
                  throw new Exception('Error. Link: ' . $url);
              }
          }

          wp_send_json(array('url' => $url));
          // wp_send_json(['url' => $url, 'data' => $data]);
      } catch (Exception $e) {
          delete_transient('wooms_start_timestamp');
          set_transient('woomss_error_background', $e->getMessage(), 60*60);

          wp_send_json_error( $e->getMessage() );
      }
    }

    private function wooms_nonce_check($nonce = ''){
      if(empty($nonce)){
        return false;
      }

      if($nonce == get_transient('wooms_nonce')){
        delete_transient('wooms_nonce');
        return true;
      } else {
        return false;
      }

    }
    private function wooms_nonce_create(){
      set_transient('wooms_nonce', wp_hash(time()), 60*60);
      return get_transient('wooms_nonce');
    }


    function notice_results()
    {

      $screen = get_current_screen();

      if ($screen->base != 'tools_page_moysklad') {
          return;
      }

      if(empty(get_option('wooms_end_timestamp'))){
        return;
      }

      if ( ! empty(get_transient('wooms_start_timestamp'))) {
          return;
      }

      ?>
      <div class="updated">
        <p><strong>Успешно завершился импорт продуктов из МойСклад</strong></p>
        <?php
          printf('<p>Количество обработанных записей в последней итерации: %s</p>', get_transient('wooms_count_stat'));
          printf('<p>Время успешного завершения последней загрузки: %s</p>', get_option('wooms_end_timestamp'));
        ?>
      </div>
      <?php
    }


    function notice_walker()
    {

        $screen = get_current_screen();

        if ($screen->base != 'tools_page_moysklad') {
            return;
        }

        if (empty(get_transient('wooms_start_timestamp'))) {
            return;
        }

        $time_string = get_transient('wooms_start_timestamp');
        $time = strtotime($time_string);
        $diff_minutes = round(($time - strtotime('-5 minutes'))/60, 2);

        ?>
        <div class="updated">
          <p><strong>Сейчас выполняется пакетная обработка данных в фоне.</strong></p>
          <p>Отметка времени о последней итерации: <?php echo $time_string ?>, количество прошедших минут: <?php echo $diff_minutes ?></p>
          <p>Ссылка на последний запрос в очереди: <?php echo get_transient('wooms_last_url') ?></p>
        </div>
        <?php
    }

    function notice_error()
    {

        $screen = get_current_screen();

        if ($screen->base != 'tools_page_moysklad') {
            return;
        }

        if (empty(get_transient('woomss_error_background'))) {
            return;
        }

        ?>
        <div class="update-nag">
        <p><strong>Обработка заверишлась с ошибкой.</strong></p>
        <p>Данные: <?php echo get_transient('woomss_error_background') ?></p>
      </div>
        <?php
    }

    function settings_init()
    {
        register_setting('mss-settings', 'wooms_check_security_disable');
        add_settings_field(
          $id = 'wooms_check_security_disable',
          $title = 'Отключить проверку безопасности AJAX',
          $callback = [$this, 'wooms_check_security_disable_display'],
          $page = 'mss-settings',
          $section = 'woomss_section_other'
        );
    }

    function wooms_check_security_disable_display()
    {
        $option_name = 'wooms_check_security_disable';
        printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option($option_name), false ));
        ?>

        <p><small>По умолчанию опция галочка должна быть снята. Включать ее можно только тем кто понимает что делает для целей отладки и разработки фоновых задач</small></p>
        <?php
    }



    function ui()
    {

        ?>
        <h2>Импорт продуктов</h2>

        <?php

          if(empty(get_transient('wooms_start_timestamp'))) {
            echo "<p>Нажмите на кнопку ниже, чтобы запустить импорт продуктов вручную</p>";
            printf('<a href="%s" class="button">Старт импорта продуктов</a>', add_query_arg('a', 'wooms_products_start_import', admin_url('tools.php?page=moysklad')));
          } else {
            do_action('wooms_walker_ui_log');
            echo "<br>";
            printf('<a href="%s" class="button">Остановить импорта продуктов</a>', add_query_arg('a', 'wooms_products_stop_import', admin_url('tools.php?page=moysklad')));
          }
        ?>

        <?php

    }

    function ui_action()
    {
      if(empty($_GET['a'])){
        return;
      } else {
        $action = $_GET['a'];
      }

      switch ($action) {
        case 'wooms_products_start_import':
          delete_transient('wooms_start_timestamp');
          delete_transient('woomss_error_background');

          $args =[
            'action' => 'wooms_walker_import',
            'batch' => '1',
            '_wooms_nonce' => $this->wooms_nonce_create()
          ];

          $url = add_query_arg($args, admin_url('admin-ajax.php'));
          wp_remote_get($url);

          wp_redirect(admin_url('tools.php?page=moysklad'));

          break;

        case 'wooms_products_stop_import':
          set_transient('wooms_walker_stop', 1, 60*60);
          delete_transient('wooms_start_timestamp');

          wp_redirect(admin_url('tools.php?page=moysklad'));
          break;
      }

    }


}

new WooMS_Product_Import_Walker;

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
      //Cron settings
      add_action('init', [$this,'cron_init']);
      add_filter( 'cron_schedules', array($this, 'add_schedule') );

      //UI and actions manually
      add_action( 'woomss_tool_actions_btns', [$this, 'ui']);
      add_action( 'woomss_tool_actions_wooms_products_start_import', [$this, 'start_manually']);
      add_action( 'woomss_tool_actions_wooms_products_stop_import', [$this, 'stop_manually']);

      //Notices
      add_action( 'admin_notices', array($this, 'notice_walker') );
      add_action( 'admin_notices', array($this, 'notice_errors') );
      add_action( 'admin_notices', array($this, 'notice_results') );

      //Main Walker
      add_action( 'wooms_cron_walker', [$this, 'walker_cron_starter']);

    }


    /**
    * Walker for data from MoySklad
    */
    function walker()
    {

      if(get_transient('wooms_end_timestamp')){
        return;
      }

      //Stop via key
      if( get_transient('wooms_walker_stop') ){
        delete_transient('wooms_start_timestamp');
        delete_transient('wooms_walker_stop');
        delete_transient('wooms_offset');
        return;
      }

      $count = apply_filters('wooms_iteration_size', 20);

      if( ! $offset = get_transient('wooms_offset')){
        $offset = 0;
        set_transient('wooms_offset', $offset, 60*60*24);
        delete_transient('wooms_count_stat');
      }

      $args_ms_api = [
        'offset' => $offset,
        'limit' => $count
      ];

      $url_api = add_query_arg($args_ms_api, 'https://online.moysklad.ru/api/remap/1.1/entity/product/');

      try {

          delete_transient('wooms_end_timestamp');

          set_transient('wooms_start_timestamp', time());

          $data = wooms_get_data_by_url( $url_api );

          //Check for errors and send message to UI
          if (isset($data['errors'])) {
              $error_code = $data['errors'][0]["code"];

              if ($error_code == 1056) {
                  $msg = sprintf('Ошибка проверки имени и пароля. Код %s, исправьте в <a href="%s">настройках</a>', $error_code, admin_url('options-general.php?page=mss-settings'));
                  throw new Exception($msg);
              } else {
                  throw new Exception($error_code . ': '. $data['errors'][0]["error"]);
              }
          }

          if (empty($data['rows'])) {
            //If no rows, that send 'end' and stop walker
              delete_transient('wooms_start_timestamp');
              delete_transient('wooms_offset');

              if(empty(get_option('woomss_walker_cron_enabled'))){
                $timer = 0;
              } else {
                $timer = 60*60*intval(get_option('woomss_walker_cron_timer', 24));
              }
              set_transient('wooms_end_timestamp', date("Y-m-d H:i:s"), $timer);
              return true;
          }

          $i = 0;
          foreach ($data['rows'] as $key => $value) {
              do_action('wooms_product_import_row', $value, $key, $data);
              $i++;
          }

          if($count_saved = get_transient('wooms_count_stat')){
            set_transient('wooms_count_stat', $i + $count_saved);
          } else {
            set_transient('wooms_count_stat', $i);
          }

          set_transient('wooms_offset', $offset+$i);
          return;

      } catch (Exception $e) {
          delete_transient('wooms_start_timestamp');
          set_transient('wooms_error_background', $e->getMessage());
      }
    }

    /**
    * Cron shedule setup for 1 minute interval
    */
    function add_schedule($schedules)
    {
      $schedules['wooms_cron_walker_shedule'] = array(
        'interval' => 60,
        'display' => 'WooMS Cron Walker 60 sec'
      );

      return $schedules;
    }

    /**
    * Cron task restart
    */
    function cron_init()
    {

      if(empty(get_option('woomss_walker_cron_enabled'))){
        return;
      }

      if(get_transient('wooms_end_timestamp')){
        return;
      }

      if ( ! wp_next_scheduled( 'wooms_cron_walker' ) ) {
        wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_walker' );
      }
    }

    /**
    * Starter walker by cron if option enabled 
    */
    function walker_cron_starter(){

      if(empty(get_option('woomss_walker_cron_enabled'))){
        return;
      }

      $this->walker();
    }


    /**
    * Start manually actions
    */
    function start_manually()
    {
      delete_transient('wooms_start_timestamp');
      delete_transient('wooms_error_background');
      delete_transient('wooms_offset');
      delete_transient('wooms_end_timestamp');

      $this->walker();

      wp_redirect(admin_url('tools.php?page=moysklad'));
    }

    /**
    * Stop manually actions
    */
    function stop_manually()
    {
      set_transient('wooms_walker_stop', 1, 60*60);
      delete_transient('wooms_start_timestamp');
      delete_transient('wooms_offset');
      delete_transient('wooms_end_timestamp');
      wp_redirect(admin_url('tools.php?page=moysklad'));
    }

    /**
    * Notice about results
    */
    function notice_results()
    {

      $screen = get_current_screen();

      if ($screen->base != 'tools_page_moysklad') {
          return;
      }

      if(empty(get_transient('wooms_end_timestamp'))){
        return;
      }

      if ( ! empty(get_transient('wooms_start_timestamp'))) {
          return;
      }

      ?>
      <div id="message" class="updated notice">
        <p><strong>Успешно завершился импорт продуктов из МойСклад</strong></p>
        <?php
          printf('<p>Количество обработанных записей в последней итерации: %s</p>', get_transient('wooms_count_stat'));
          printf('<p>Время успешного завершения последней загрузки: %s</p>', get_transient('wooms_end_timestamp'));
          printf('<p>Количество операций: %s</p>', get_transient('wooms_count_stat'));
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
        $diff_sec = time() - $time_string;

        $time_string = date('Y-m-d H:i:s', $time_string);

        ?>
        <div id="message" class="updated notice">
          <p><strong>Сейчас выполняется пакетная обработка данных в фоне.</strong></p>
          <p>Отметка времени о последней итерации: <?php echo $time_string ?></p>
          <p>Количество операций: <?php echo get_transient('wooms_count_stat'); ?></p>
          <p>Секунд прошло: <?php echo $diff_sec ?>. Следующая серия данных должна отправиться примерно через минуту. Можно обновить страницу для проверки результатов работы.</p>
        </div>
        <?php
    }

    function notice_errors()
    {
        $screen = get_current_screen();

        if ($screen->base != 'tools_page_moysklad') {
            return;
        }

        if (empty(get_transient('wooms_error_background'))) {
            return;
        }

        ?>
        <div class="error">
          <p><strong>Обработка заверишлась с ошибкой.</strong></p>
          <p>Данные: <?php echo get_transient('wooms_error_background') ?></p>
        </div>
        <?php
    }

    /**
    * User interface for manually actions
    */
    function ui()
    {
      echo '<h2>Импорт продуктов</h2>';
      if(empty(get_transient('wooms_start_timestamp'))) {
        echo "<p>Нажмите на кнопку ниже, чтобы запустить импорт продуктов вручную</p>";
        printf('<a href="%s" class="button">Старт импорта продуктов</a>', add_query_arg('a', 'wooms_products_start_import', admin_url('tools.php?page=moysklad')));
      } else {
        printf('<a href="%s" class="button">Остановить импорт продуктов</a>', add_query_arg('a', 'wooms_products_stop_import', admin_url('tools.php?page=moysklad')));
      }
    }
}

new WooMS_Product_Import_Walker;

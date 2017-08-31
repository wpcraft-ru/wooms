<?php

/**
 * Sometimes php and web server get down with error (timeout 503 or 502). And superviser can ping and restart walker.
 */
class WooMS_Import_Supervisor {

  function __construct() {
    add_action('admin_init', [$this, 'add_setting']);
    add_action('init', [$this,'cron_init']);
    add_action('wooms_import_supervisor', [$this, 'supervisor']);

  }

  /**
  * Start by cron. Check last time walker start and if find error, that start URL for continue
  */
  function supervisor(){
      $time_last_start = get_transient('wooms_start_timestamp');
      if(empty($time_last_start)){
        return;
      }

      $time_1 = strtotime($time_last_start);
      $time_2 = strtotime('-5 minutes');
      $diff = ($time_1 - $time_2)/60;

      if($diff < 0){

        if($url = get_transient('wooms_last_url')){
          wp_remote_get($url);

        }
      }
  }


  function cron_init(){


    //if don't disable, that not add cron task
    if( ! empty(get_option('woomss_walker_supervisor_disabled'))){
      return;
    }


    if ( ! wp_next_scheduled( 'wooms_import_supervisor' ) ) {
      wp_schedule_event( time(), 'wp_wc_updater_cron_interval', 'wooms_import_supervisor' );
    }

  }

  function add_setting(){
    register_setting('mss-settings', 'woomss_walker_supervisor_disabled');
    add_settings_field(
      $id = 'woomss_walker_supervisor_disabled',
      $title = 'Выключить супервайзера бота',
      $callback = [$this, 'woomss_walker_supervisor_disabled_display'],
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );

  }

  function woomss_walker_supervisor_disabled_display(){
    $name = 'woomss_walker_supervisor_disabled';
    printf('<input type="checkbox" name="%s" value="1" %s />', $name, checked( 1, get_option($name), false ));
    ?>
      <p>
        <small>Супервайзер позволяет обнаружить ошибки таймаута на веб-сервере и делать рестарт фоновых обработок синхронизации. Его можно отключить если вы уверены в настройках своего веб-сервера</small>
      </p>
    <?php
  }
}
new WooMS_Import_Supervisor;

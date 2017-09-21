<?php


/**
 * WP Cront for Walker
 */
class WooMS_Walker_Cron {

  function __construct()
  {
    add_action('init', [$this,'cron_init']);

    add_action( 'admin_init', array($this, 'settings_init'), $priority = 100, $accepted_args = 1 );

    add_action('wooms_import_walker_cron', [$this, 'start_walker_by_cron']);

  }


  function start_walker_by_cron(){

    if( ! empty(get_transient('wooms_start_timestamp')) ){
      return;
    }

    if(empty(get_transient('woomss_walker_cron_enabled'))){
      return;
    }

    $args =[
      'action' => 'wooms_walker_import',
      'batch' => '1',
      'nonce' => wp_create_nonce('wooms-nonce')
    ];
    $url = add_query_arg($args, admin_url('admin-ajax.php'));
    $args = [
      'timeout'     => 30
    ];
    wp_remote_get($url,$args);
  }

  function settings_init(){
    add_settings_section(
      'wooms_section_cron',
      'Расписание синхронизации',
      null,
      'mss-settings'
    );

    register_setting('mss-settings', 'woomss_walker_cron_enabled');
    add_settings_field(
      $id = 'woomss_walker_cron_enabled',
      $title = 'Включить синхронизацию продуктов по расписанию',
      $callback = [$this, 'woomss_walker_cron_display'],
      $page = 'mss-settings',
      $section = 'wooms_section_cron'
    );

    register_setting('mss-settings', 'woomss_walker_cron_timer');
    add_settings_field(
      $id = 'woomss_walker_cron_timer',
      $title = 'Выберите период синхронизации',
      $callback = [$this, 'woomss_walker_cron_timer_display'],
      $page = 'mss-settings',
      $section = 'wooms_section_cron'
    );

  }

  function woomss_walker_cron_timer_display(){
    $schedules = wp_get_schedules();

    if(empty(get_option('woomss_walker_cron_timer'))){
      update_option('woomss_walker_cron_timer', 'twicedaily');
    }

    ?>
    <select class="woomss_walker_shedules" name="woomss_walker_cron_timer">
      <?php
      foreach ($schedules as $key => $value) {
        printf('<option value="%s" %s>%s</option>', $key, selected( get_option('woomss_walker_cron_timer'), $key ), $value['display']);
      }
      ?>
    </select>
    <?php
  }

  function woomss_walker_cron_display(){
    $option_name = 'woomss_walker_cron_enabled';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option($option_name), false ));

  }

  function cron_init(){

    if(empty(get_transient('woomss_walker_cron_enabled'))){
      return;
    }

    $shedule = get_option('woomss_walker_cron_timer', 'twicedaily');

    if ( ! wp_next_scheduled( 'wooms_import_walker_cron' ) ) {
    	wp_schedule_event( time(), $shedule, 'wooms_import_walker_cron' );
    }

  }

}
new WooMS_Walker_Cron;

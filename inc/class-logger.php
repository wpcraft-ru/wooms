<?php

namespace WooMS;

/**
 * Logger for WooMS
 *
 * Example: do_action('wooms_logger', $type = '123', $title = '123', $desc = '123');
 */
final class Logger {

  /**
   * The init
   */
  public static function init(){
    add_action('admin_init', array(__CLASS__, 'init_settings_page'));
    add_action('wooms_logger', array(__CLASS__, 'add_log'), 10, 3);
    add_action('wooms_logger_error', array(__CLASS__, 'add_log_error'), 10, 3);

    add_action('admin_menu', function(){
      if(get_option('wooms_logger_enable')){
        global $submenu;
        $permalink = admin_url( 'admin.php?page=wc-status&tab=logs' );
        $submenu['moysklad'][] = array( 'Журнал', 'manage_options', $permalink );
      }
    }, 111);

  }

  /**
   * add_log_error
   */
  public static function add_log_error($type = '', $title = '', $description = ''){
    if( ! get_option('wooms_logger_enable') ){
      return;
    }


    $data = '';

    $data .= $title;

    if( ! empty($description) ){
      $description = wc_print_r( $description, true );
      $description = wp_trim_words( $description, $num_words = 300, $more = null );
      $data .= ': ' . $description;
    }

    $source = 'wooms';
    if( ! empty($type) ){
      $type = str_replace('\\', '-', $type);
      $source .= '-' . $type;
    }

    $logger = wc_get_logger();
    $context = array( 'source' => $source );
    $logger->error( $data, $context );

  }

  /**
   * add log
   */
  public static function add_log($type = '', $title = '', $description = ''){
    if( ! get_option('wooms_logger_enable') ){
      return;
    }

    $description = wp_trim_words( $description, $num_words = 300, $more = null );

    $data = '';

    $data .= $title;

    if( ! empty($description) ){
      $data .= ': ' . wc_print_r( $description, true );
    }

    $source = 'wooms';
    if( ! empty($type) ){
      $type = str_replace('\\', '-', $type);
      $source .= '-' . $type;
    }

    $logger = wc_get_logger();
    $context = array( 'source' => $source );
    $logger->info( $data, $context );

  }

  /**
   * render_settings_page
   */
  public static function init_settings_page(){
    register_setting( 'mss-settings', 'wooms_logger_enable' );
    add_settings_field(
      $id = 'wooms_logger_enable',
      $title = 'Логирование',
      $callback = array(__CLASS__, 'display_field_logger_enable' ),
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );
  }

  /**
   * display_field_logger_enable
   */
  public static function display_field_logger_enable(){
    $option_name = 'wooms_logger_enable';
    printf(
      '<input type="checkbox" name="%s" value="1" %s />',
      $option_name, checked( 1, get_option( $option_name ), false )
    );
    ?>
    <p>При включении, ошибки и ключевые изменения данных будут записываться в <a href="<?= admin_url( 'admin.php?page=wc-status&tab=logs' ) ?>">журнал WooCommerce</a></p>
    <?php
  }
}

Logger::init();

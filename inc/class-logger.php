<?php

namespace WooMS;

/**
 * Logger for WooMS
 *
 * Example: do_action('wooms_logger', $type = '123', $title = '123', $desc = '123');
 */
class Logger {

  /**
   * Table name
   */
  public static $table_name = 'wooms_logger';

  /**
   * The init
   */
  public static function init(){
    add_action('admin_init', array(__CLASS__, 'init_settings_page'));
    add_action('wooms_activate', array(__CLASS__, 'add_db_table'));
    add_action('wooms_logger', array(__CLASS__, 'add_log'), 10, 3);
  }

  /**
   * add_db_table
   */
  public static function add_db_table(){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->base_prefix . self::$table_name;

    $sql = "CREATE TABLE `{$table_name}` (
      id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      type varchar(255) NOT NULL,
      title text,
      description longtext,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
      PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    $success = empty( $wpdb->last_error );

  }

  /**
   * add log
   */
  public static function add_log($type = 'message', $title = '', $description = ''){
    if( ! get_option('wooms_logger_enable') ){
      return;
    }

    global $wpdb;

    //check if table exists
    $table_name = $wpdb->base_prefix . self::$table_name;
    if($table_name != $wpdb->get_var("SHOW TABLES LIKE '$table_name'")){
      return;
    }

    $wpdb->insert("{$table_name}", array(
        'type' => $type,
        'title' => $title,
        'description' => $description,
        'created_at' => gmdate('Y-m-d H:i:s'),
    ));
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
    <p>При включении, ошибки и ключевые изменения данных будут записываться в специальную таблицу</p>
    <?php
  }
}

Logger::init();

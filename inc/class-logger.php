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
    // add_action('wooms_activate', array(__CLASS__, 'add_db_table'));
    add_action('wooms_logger', array(__CLASS__, 'add_log'), 10, 3);

    add_action('admin_menu', function(){
      if(get_option('wooms_logger_enable')){
        global $submenu;
        // global $menu;
        $permalink = admin_url( 'admin.php?page=wc-status&tab=logs' );
        $submenu['moysklad'][] = array( 'Журнал', 'manage_options', $permalink );
      }
    }, 111);

  }

  /**
   * display_log
   */
  public static function display_log(){

    if(empty($_GET['id'])): ?>
    <div class="wooms-logger-wrapper">

      <h1>Лог синхронизации</h1>
      <?= self::display_ui(); ?>
      <table class="wp-list-table widefat fixed striped posts">
        <thead>
          <tr>
            <th scope="col" id="id" class="manage-column" width="33">
              <span class="wc-image tips">id</span>
            </th>
            <th scope="col" id="type" class="manage-column" width="190">
              <span>type</span>
            </th>
            <th scope="col" id="description" class="manage-column">
              <span>data</span>
            </th>
          </tr>
        </thead>

        <tbody id="the-list">
          <?php self::display_rows_log() ?>

        </tbody>
      </table>
    </div>
    <?php
    else: ?>
      <h1>Данные по записи лога</h1>
      <div class="">
        <?= sprintf('<a href="%s" class="button button-primary">назад</a>', admin_url('admin.php?page=wooms-log')) ?>
        <hr>
      </div>
      <?php self::display_the_row_log(); ?>

    <?php
    endif;
  }

  /**
   * display_ui
   */
  public static function display_ui(){
    $offset = empty($_GET['offset']) ? 0 : (int)$_GET['offset'];
    $page = empty($_GET['page']) ? '' : $_GET['page'];
    $search = empty($_GET['wooms-search']) ? '' : $_GET['wooms-search'];
    ?>
    <div class="log-display-ui">
      <form class="" action="" method="get">
        <div class="form-field">
          <label for="offset">Отступ: </label></br>
          <input id="offset" type="number" name="offset" value="<?= $offset ?>">
        </div>
        <div class="form-field">
          <label for="wooms-search">Поиск: </label></br>
          <input id="wooms-search" type="text" name="wooms-search" value="<?= $search ?>">
        </div>
        <br>

        <div class="form-field">
          <input type="submit" class="button button-primary"  name="" value="Отбор">
        </div>
        <input type="hidden" name="page" value="<?= $page ?>">
      </form>
      <br>
      <br>

    </div>
    <?php
  }

  /**
   * display_the_row_log
   */
  public static function display_the_row_log(){
    global $wpdb;
    if(empty((int)$_GET['id'])){
      return;
    }

    $id = (int)$_GET['id'];

    $log = $wpdb->get_row(
      "
      SELECT * FROM {$wpdb->prefix}wooms_logger as log
      WHERE log.id = {$id}
      ",
      ARRAY_A
    );

    printf('<p>%s</p>', $log['id']);
    printf('<p>%s</p>', $log['type']);
    printf('<p>%s</p>', $log['title']);
    printf('<p><pre>%s</pre></p>', $log['description']);
  }

  /**
   * display_row_log
   */
  public static function display_rows_log(){
    global $wpdb;

    $offset = 0;
    if( ! empty($_GET['offset'])){
      $offset = (int)$_GET['offset'];
    }

    $table_name = "{$wpdb->prefix}wooms_logger";

    $sql = "SELECT * FROM $table_name as log";

    if( ! empty($_GET['wooms-search'])){
      $search = esc_sql( $_GET['wooms-search'] );
      $sql .= " WHERE log.description LIKE '%$search%'";
    }

    $sql .= " ORDER BY log.id DESC LIMIT 10 OFFSET $offset";

    $log = $wpdb->get_results( $sql, ARRAY_A );

    if( ! is_array($log) ){
      echo '<p>Нет данных</p>';
      return;
    }

    foreach ($log as $row): ?>
        <tr id="log-<?= $row['id'] ?>" class="">
          <td class="wooms-log-id" data-colname="id">
              <span><?= $row['id'] ?></span>
          </td>
          <td class="" data-colname="type">
            <span><?= $row["type"] ?></span>
          </td>
          <td class="" data-colname="data">
            <p><?= $row["title"] ?></p>
            <p>
              <?php
                $url = sprintf('<a href="%s">...</a>', admin_url('admin.php?page=wooms-log&id=' . $row['id']));
                echo wp_trim_words( $row["description"], 30, $url );
              ?>
            </p>
          </td>
        </tr>

    <?php  endforeach;
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

    $data = array(
      'type' => $type,
      'title' => $title,
      'description' => $description,
    );

    $logger = wc_get_logger();
    $context = array( 'source' => 'wooms' );
    $logger->info( wc_print_r( $data, true ), $context );
    //
    // global $wpdb;
    //
    // //check if table exists
    // $table_name = $wpdb->base_prefix . self::$table_name;
    // if($table_name != $wpdb->get_var("SHOW TABLES LIKE '$table_name'")){
    //   return;
    // }
    //
    // $wpdb->insert("{$table_name}", array(
    //     'type' => $type,
    //     'title' => $title,
    //     'description' => $description,
    //     'created_at' => gmdate('Y-m-d H:i:s'),
    // ));
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

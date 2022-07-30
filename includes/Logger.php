<?php

namespace WooMS;

/**
 * Logger for WooMS
 *
 * Example: do_action('wooms_logger', $type = '123', $title = '123', $desc = '123');
 */
final class Logger
{

  /**
   * The init
   */
  public static function init()
  {
    add_action('admin_init', array(__CLASS__, 'add_settings'));
    add_action('wooms_logger', array(__CLASS__, 'add_log'), 10, 3);
    add_action('wooms_logger_error', array(__CLASS__, 'add_log_error'), 10, 3);

    // issue https://github.com/wpcraft-ru/wooms/issues/300
    add_filter('wooms_logger_enable', function ($is_enable) {
      return self::is_enable();
    });

    add_action('admin_menu', function () {
      if (self::is_enable()) {
        global $submenu;
        $permalink = admin_url('admin.php?page=wc-status&tab=logs');
        $submenu['moysklad'][] = array('Журнал', 'manage_options', $permalink);
      }
    }, 111);


    add_filter('woocommerce_status_log_items_per_page', function ($per_page) {
      return 100;
    });

  }

  public static function is_enable()
  {
    if (get_option('wooms_logger_enable')) {
      return true;
    }

    return false;
  }


  /**
   * add_log_error
   */
  public static function add_log_error($type = 'wooms', $title = '', $description = '')
  {
    if (!self::is_enable()) {
      return;
    }

    $data = '';

    $data .= strval($title);

    if (!empty($description)) {

      if (is_array($description)) {
        $description = json_encode($description, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      } else {
        $description = wc_print_r($description, true);
      }

      $description = wp_trim_words($description, $num_words = 300, $more = null);
      $data .= ':' . PHP_EOL . $description;
    }

    $source = $type;
    $source = str_replace('\\', '-', $source);

    $logger = wc_get_logger();
    $context = array('source' => $source);
    $logger->error($data, $context);
  }

  /**
   * add log
   */
  public static function add_log($type = 'wooms', $title = '', $description = '')
  {
    if (!self::is_enable()) {
      return;
    }

    $data = '';

    $data .= strval($title);

    if (!empty($description)) {
      if (is_array($description)) {
        $description = json_encode($description, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      } else {
        $description = wc_print_r($description, true);
      }

      $description = wp_trim_words($description, $num_words = 300, $more = null);
      $data .= ':' . PHP_EOL . $description;
    }

    $source = $type;
    $source = str_replace('\\', '-', $source);

    // $source = 'wooms';
    // if( ! empty($type) ){
    //   $type = str_replace('\\', '-', $type);
    //   $source .= '-' . $type;
    // }

    $logger = wc_get_logger();
    $context = array('source' => $source);
    $logger->info($data, $context);
  }

  /**
   * render_settings_page
   */
  public static function add_settings()
  {

    $option_name = 'wooms_logger_enable';

    register_setting('mss-settings', $option_name);
    add_settings_field(
      $id = $option_name,
      $title = 'Логирование',
      $callback = function ($args) {
        printf(
          '<input type="checkbox" name="%s" value="1" %s />',
          $args['key'],
          checked(1, $args['value'], false)
        );
        printf('<p>При включении, ошибки и ключевые изменения данных будут записываться в <a href="%s">журнал WooCommerce</a></p>', admin_url('admin.php?page=wc-status&tab=logs'));
      },
      $page = 'mss-settings',
      $section = 'woomss_section_other',
      $args = [
        'key' => $option_name,
        'value' => get_option($option_name),
      ]
    );
  }
}

Logger::init();

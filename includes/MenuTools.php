<?php

namespace WooMS;
/**
 *  Tool for MoySklad
 */
class MenuTools {

  /**
   * URL action
   */
  public static $url;

  /**
   * The Init
   */
  public static function init(){

    self::$url = $_SERVER['REQUEST_URI'];

    add_action(
      'admin_menu',
      function () {

        if(current_user_can('manage_woocommerce')){
          add_menu_page(
            $page_title = 'МойСклад',
            $menu_title = 'МойСклад',
            $capability = 'manage_woocommerce',
            $menu_slug = 'moysklad',
            $function = array( __CLASS__, 'display_ui' ),
            $icon = 'dashicons-forms',
            '57.5'
          );
        }

      }
    );


  }

  /**
   * Display UI
   */
  public static function display_ui()
  {
    printf('<h1>%s</h1>', 'Управление МойСклад');
    $items = [
      '<a style="color:green;" href="https://github.com/wpcraft-ru/wooms/wiki/GettingStarted" target="_blank">
          <strong>Начало работы</strong>
      </a>',
      sprintf('<a href="%s">Настройки</a>', admin_url("admin.php?page=mss-settings") ),
      '<a href="https://online.moysklad.ru/app/" target="_blank">Вход в МойСклад</a>',
      sprintf('<a href="%s">Диагностика проблем</a>', admin_url("site-health.php") ),
      '<a href="https://wpcraft.ru/hosting-wordpress-woocommerce/" target="_blank">Рекомендуемые хостинги</a>',
      '<a href="https://wpcraft.ru/wooms/" target="_blank">Контакты</a>',
    ];

    printf( '<p>%s</p>', implode('<span> | </span>', $items) );

    if(empty(get_option('woomss_pass'))){
      printf('<p>Укажите логин и пароль на <a href="%s">странице настроек</a></p>', admin_url('admin.php?page=mss-settings'));
    } else {
      if( empty($_GET['a']) ){

        do_action('wooms_tools_sections');

        // deprecated
        do_action('woomss_tool_actions_btns');

      } else {

        printf('<a href="%s">Вернуться...</a>', remove_query_arg( 'a', self::$url));
        do_action('woomss_tool_actions');
        do_action('woomss_tool_actions_' . $_GET['a']);

      }
    }


  }


}

MenuTools::init();

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
  { ?>
<h1>Управление МойСклад</h1>

<p>
    <a style="color:green;" href="https://github.com/wpcraft-ru/wooms/wiki/GettingStarted"
        target="_blank"><strong>Начало
            работы</strong></a>
    <span> | </span>
    <a href="<?php echo admin_url('admin.php?page=mss-settings') ?>">Настройки</a>
    <span> | </span>
    <a href="https://online.moysklad.ru/app/" target="_blank">Вход в МойСклад</a>
    <span> | </span>
    <a href="<?php echo admin_url('site-health.php') ?>">Диагностика проблем</a>
    <span> | </span>
    <a href="https://wpcraft.ru/wordpress/hosting/" target="_blank">Рекомендуемые хостинги</a>
    <span> | </span>
    <a href="https://wpcraft.ru/wordpress-woocommerce-mentoring/" target="_blank">Сопровождение магазинов и консалтинг</a>

</p>

<?php
    if(empty($_GET['a'])){

      do_action('woomss_tool_actions_btns');
      
      do_action('wooms_tools_sections');

    } else {

      printf('<a href="%s">Вернуться...</a>', remove_query_arg( 'a', self::$url));
      do_action('woomss_tool_actions');
      do_action('woomss_tool_actions_' . $_GET['a']);

    }

  }


}

MenuTools::init();
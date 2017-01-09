<?php

/**
 *  Tool for MoySklad
 */

class woomss_tool {

  public $url;

  function __construct(){

    $this->url = $_SERVER['REQUEST_URI'];

    add_action('admin_menu', function(){
        add_management_page(
            $page_title = 'МойСклад',
            $menu_title = 'МойСклад',
            $capability = 'manage_options',
            $menu_slug = 'moysklad',
            $function = array($this, 'ui_management_page_callback')
        );
    });


  }

  function ui_management_page_callback(){

    echo '<h1>Управление МойСклад</h1>';

    printf('<p><a href="%s" target="_blank">Вход в МойСклад</a></p>', 'https://online.moysklad.ru/app/');

    if(empty($_GET['a'])){

      do_action('woomss_tool_actions_btns');

    } else {
      printf('<a href="%s">Вернуться...</a>', remove_query_arg( 'a', $this->url));
    }

    do_action('woomss_tool_actions');
  }


}
new woomss_tool;

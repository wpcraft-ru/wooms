<?php

/**
 *  Tool for MoySklad
 */

class woomss_tool {

  public $url;

  function __construct(){

    $this->url = $_SERVER['REQUEST_URI'];

    add_action('admin_menu', function(){
	    add_submenu_page(
		    'mss-settings',
		    $page_title = 'Управление',
		    $menu_title = 'Управление',
		    $capability = 'manage_options',
		    $menu_slug = 'moysklad',
		    $function = array($this, 'ui_management_page_callback')
	    );
    });


  }

  function ui_management_page_callback(){

    ?>
    <h1>Управление МойСклад</h1>

    <p>
      <a href="<?php echo admin_url('options-general.php?page=mss-settings') ?>">Настройки</a>
      <span> | </span>
      <a href="https://online.moysklad.ru/app/" target="_blank">Вход в МойСклад</a>
    </p>

    <?php
    if(empty($_GET['a'])){

      do_action('woomss_tool_actions_btns');

    } else {

      printf('<a href="%s">Вернуться...</a>', remove_query_arg( 'a', $this->url));
      do_action('woomss_tool_actions');
      do_action('woomss_tool_actions_' . $_GET['a']);

    }

  }


}
new woomss_tool;

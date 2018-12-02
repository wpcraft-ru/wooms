<?php

/**
 *  Tool for MoySklad
 */

class woomss_tool {

  public $url;

  public function __construct(){

    $this->url = $_SERVER['REQUEST_URI'];

	  add_action(
		  'admin_menu',
		  function () {

			  add_menu_page(
				  $page_title = 'МойСклад',
				  $menu_title = 'МойСклад',
				  $capability = 'manage_woocommerce',
				  $menu_slug = 'moysklad',
				  $function = array( $this, 'ui_management_page_callback' ),
				  $icon = 'dashicons-forms',
				  '57.5'
			  );
		  },
		  20
	  );


  }

  function ui_management_page_callback(){

    ?>
    <h1>Управление МойСклад</h1>

    <p>
      <a href="<?php echo admin_url('admin.php?page=mss-settings') ?>">Настройки</a>
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

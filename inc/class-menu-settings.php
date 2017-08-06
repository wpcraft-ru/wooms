<?php

class woomss {
  function __construct(){

    add_action('admin_menu', function () {
        add_options_page(
          $page_title = 'МойСклад',
          $menu_title = "МойСклад",
          $capability = 'manage_options',
          $menu_slug = 'mss-settings',
          $function = array($this, 'mss_settings_callback')
        );
    });

    add_action( 'admin_init', array($this, 'settings_init'), $priority = 10, $accepted_args = 1 );
  }

  function settings_init(){



    add_settings_section(
    	'woomss_section_login',
    	'Данные для доступа МойСклад',
    	null,
    	'mss-settings'
    );

    register_setting('mss-settings', 'woomss_login');
    add_settings_field(
      $id = 'woomss_login',
      $title = 'Логин (admin@...)',
      $callback = [$this, 'woomss_login_display'],
      $page = 'mss-settings',
      $section = 'woomss_section_login'
    );

    register_setting('mss-settings', 'woomss_pass');
    add_settings_field(
      $id = 'woomss_pass',
      $title = 'Пароль',
      $callback = [$this, 'woomss_pass_display'],
      $page = 'mss-settings',
      $section = 'woomss_section_login'
    );



    add_settings_section(
    	'woomss_section_other',
    	'Дополнительные опции',
    	null,
    	'mss-settings'
    );

    register_setting('mss-settings', 'woomss_img');
    add_settings_field(
      $id = 'woomss_img',
      $title = 'Обновлять картинку',
      $callback = [$this, 'woomss_img_display'],
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );

    register_setting('mss-settings', 'woomss_debug');
    add_settings_field(
      $id = 'woomss_debug',
      $title = 'Режим отладки',
      $callback = [$this, 'woomss_debug_display'],
      $page = 'mss-settings',
      $section = 'woomss_section_other'
    );

  }

  function woomss_pass_display(){
    printf('<input type="password" name="woomss_pass" value="%s"/>',get_option('woomss_pass'));
  }

  function woomss_login_display(){
    printf('<input type="text" name="woomss_login" value="%s"/>',get_option('woomss_login'));
  }


  function woomss_img_display(){
    printf('<input type="checkbox" name="woomss_img" value="1" %s />', checked( 1, get_option('woomss_img'), false ));
  }

  function woomss_debug_display(){
    printf('<input type="checkbox" name="woomss_debug" value="1" %s />', checked( 1, get_option('woomss_debug'), false ));
  }


  function mss_settings_callback(){
    ?>

    <form method="POST" action="options.php">
      <h1>Настройки интеграции МойСклад</h1>
      <?php
        settings_fields( 'mss-settings' );
        do_settings_sections( 'mss-settings' );
        submit_button();
      ?>
    </form>


    <?php
    printf('<p><a href="%s">Управление синхронизацией</a></p>', admin_url('tools.php?page=moysklad'));
  }



}
new woomss;
